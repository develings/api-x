<?php

namespace ApiX;

use ApiX\Definition\Base;
use ApiX\Definition\Endpoint;
use ApiX\Definition\Field;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class ApiX
{
    public const MODE_INDEX = 'index';
    public const MODE_GET = 'get';
    public const MODE_POST = 'post';
    public const MODE_PUT = 'put';
    public const MODE_DELETE = 'delete';

    public $definition;
    public $apis;

    /**
     * @var Database
     */
    public $db;

    /**
     * @var Base
     */
    public  $base;

    private $user;

    /**
     * Can be invalid if running in CLI and the JSON file does not exist
     * @var bool
     */
    private $valid = true;

    public function load(string $path)
    {
        $app = app();
        $app->instance(self::class, $this);
        
        abort_unless(file_exists($path), 501, "ApiX json file not found ($path)");

        $data = file_get_contents($path);
        $data = json_decode($data, 1, 512, JSON_THROW_ON_ERROR);

        abort_unless($data, 505, "ApiX JSON file ($path) is invalid: " . json_last_error_msg());

        $this->definition = $data;

        $this->db = new Database($data);
        $this->base = new Base($data);
        
        return $this;
    }

    /**
     * @return self
     */
    public function getInstance(): self
    {
        return $this;
    }

    public function openApiJson()
    {
        return (new OpenAPI($this->base))->definition();
    }

	public function setRoutes()
    {
        if (!$this->valid) {
            return $this;
        }

        $prefix = $this->base->endpoint ?: '';

        Route::group(['prefix' => $prefix, 'as' => 'api'], static function() {
            Route::get('api.json', ['as' => '.openapi', 'uses' => '\ApiX\Routes@getOpenApiJson']);
            Route::get('swagger', ['as' => '.swagger', 'uses' => '\ApiX\Routes@getSwagger']);
        });

        Route::group(['prefix' => $prefix, 'middleware' => 'api.auth.member', 'as' => 'api'], static function() {
            Route::get('migrate', ['as' => '.migrate', 'uses' => '\ApiX\Routes@migrate']); //
            Route::get('{api}', ['as' => '.index', 'uses' => '\ApiX\Routes@index']);
            Route::post('{api}', ['as' => '.post', 'uses' => '\ApiX\Routes@post']);
            Route::get('{api}/{id}', ['as' => '.get', 'uses' => '\ApiX\Routes@get']);
            Route::put('{api}/{id}', ['as' => '.put', 'uses' => '\ApiX\Routes@put']);
            Route::delete('{api}/{id}', ['as' => '.delete', 'uses' => '\ApiX\Routes@delete']);
        });
        
        return $this;
    }


    public function index($name, Request $request)
    {
        /** @var Endpoint $endpoint */
        $endpoint = $this->getEndpoint($name);
        abort_unless($endpoint, 404);

        $perPage = $request->get('per_page', $endpoint->per_page ?? 25);

        /** @var DynamoBuilder|Builder $query */
        $query = $this->getBuilder($endpoint);

        if ($endpoint->order_by) {
            $query->orderByRaw($endpoint->order_by);
        }

        if ($endpoint->soft_deletes) {
            $query->where(static function($query) {
                $query->whereNull('deleted_at');
            });
        }

        $query = $this->getWhereParameters($query, $endpoint);

        $search = $request->get('search');
        if ($search && $endpoint->searchable) {
            $fields = explode(',', $endpoint->searchable ?: '');
            $fields = $fields ? array_values($fields) : array_keys($endpoint->fields);

            $i = 0;
            foreach ($fields as $field) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $query->$method($field, 'like',  '%' . $search . '%');
                $i++;
            }
        }


        if ($this->base->db === \ApiX\Definition\DB::DRIVER_DYNAMO_DB) {
            $data = $query->paginate($perPage, $total = $query->count(), $endpoint);
        } else {
            $data = $query->paginate($perPage);
        }

        $items = $endpoint->dataHydrateItems($data->items(), $request);
        $items = $endpoint->addRelations($items->toArray(), $request->get('with'), true);

        $this->triggerHydrate(self::MODE_INDEX, $endpoint, $items);

        $output = $data->toArray();
        $output['data'] = $items;

        if (isset($total)) {
            $output['to'] = $total;
        }

        return $output;
    }

    public function getWhereParameters($query, Endpoint $api)
    {
        if (!$api->condition) {
            return $query;
        }

        $where = $api->condition;
        $where = is_array($where) ? $where : [$where];
        $userPlaceholders = $this->getUserPlaceholders();
        $userPlaceholdersKeys = array_keys($userPlaceholders);
        $userPlaceholdersValues = array_values($userPlaceholders);

        foreach ($where as $item) {
            [$type, $condition] = explode(':', $item);
            $condition = str_replace($userPlaceholdersKeys, $userPlaceholdersValues, $condition);
            $e = explode(',', $condition);

            $query->$type(...$e);
        }
        
        return $query;
    }

    public function find(Endpoint $endpoint, $id, $identifierKey = null, $source = null)
    {
        $identifierKey = $identifierKey ?: $endpoint->getIdentifier();
        if ($this->base->db->driver === \ApiX\Definition\DB::DRIVER_MYSQL) {
            $model = DynamicModel::createInstance($this->base->getTableName($endpoint));
            $model->fillable(array_keys($endpoint->fields));
        } else {
            $model = $this->getBuilder($endpoint);
        }

        if ($model) {
            $query = $model->where($identifierKey, $id);
        } else {
            $query = DB::table($endpoint->getTableName())->where($identifierKey, $id);
        }

        if ($source !== 'auth') {
            $query = $this->getWhereParameters($query, $endpoint);
        }
        $this->buildQuery($endpoint, $query, $source);

        if ( $this->base->db->driver === \ApiX\Definition\DB::DRIVER_MYSQL ) {
            if ($endpoint->soft_deletes) {
                $query->whereNull('deleted_at');
            }
            $first = $query->first();
        } else {
            $first = $query->get()->first();
        }

        if ( $first && $this->base->db->driver === \ApiX\Definition\DB::DRIVER_DYNAMO_DB && $endpoint->soft_deletes && $first->deleted_at ) {
            return null;
        }

        return $first;
    }

    public function getUserPlaceholders()
    {
        $replacements = [];
        if($user = $this->getUser()) {
            $user = is_object($user) ? $user->toArray() : (array)$user;
            foreach ($user as $key => $val) {
                $replacements['$user.' . $key] = $val;
            }
        }

        return $replacements;
    }

    /**
     * @param Endpoint $endpoint
     * @param Builder $builder
     */
    public function buildQuery(Endpoint $endpoint, $builder, $source)
    {
        if (!$endpoint->find || $source === Endpoint::REQUEST_POST) {
            return;
        }

        $find = is_array($endpoint->find) ? $endpoint->find : [$endpoint->find];

        foreach ($find as $item) {
            $parts = explode(':', $item);
            $method = array_shift($parts);

            // user
            $replacements = $this->getUserPlaceholders();

            $parts = str_replace(array_keys($replacements), array_values($replacements), $parts[0]);
            $params = explode(',', $parts);

            $builder->$method(...$params);
        }
    }

    public function get($name, $id, Request $request)
    {
        /** @var Endpoint $endpoint */
        $endpoint = $this->getEndpoint($name);
        abort_unless($endpoint, 404);

        $result = $this->findOne($endpoint, $id, $request);

        $this->triggerHydrate(self::MODE_GET, $endpoint, $result);

        return $result;
    }

    public function post($name, Request $request)
    {
        // check if authentication is required
        /** @var Endpoint $endpoint */
        $endpoint = $this->getEndpoint($name);
        abort_unless($endpoint, 404);
        
        // Check permission if enabled

        // Validate the data from within api fields
        $rules = $endpoint->getValidationRules(Endpoint::REQUEST_POST);
        
        $requestData = $request->post();

        $data = $endpoint->fillDefaultValues($requestData, [], Endpoint::REQUEST_POST);
        
        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            $response = [
                'errors' => $validation->errors()
            ];

            // distinguish between unique errors and normal errors and change to 409 if necessary
            return response($response, 400);
        }

        $data = $validation->validated();
        
        // Check if a field is unique and then perform a query in the db
        if ($this->base->db->driver === \ApiX\Definition\DB::DRIVER_DYNAMO_DB) {
            /** @var Field[] $fields */
            collect($endpoint->fields)->filter(static function(Field $item) {
                return $item->isUnique();
            })->each(function($field) use($data, $endpoint) {
                abort_if(
                    isset($data[$field->key]) && $this->find($endpoint, $data[$field->key], $field->key),
                    409,
                    sprintf('Entity with given field (%s) value already exists.', $field->key)
                );
            });
        }

        //$model = $this->getBuilder($api);
        $model = $this->createModelInstance($endpoint);

        if ($model && $endpoint->unique) {
            $query = $model->newQuery();
            foreach ($endpoint->unique as $item) {
                $query->where($item, $data[$item]);
            }
            if ($endpoint->soft_deletes) {
                $query->whereNull('deleted_at');
            }
            $exists = $query->first();
            abort_if($exists, 409, 'Entity exists');
        }

        if ($model) {
            $fillables = $model->getFillable();
            $data = $fillables ? array_intersect_key($data, array_flip($fillables)) : $data;
            $model->fill($data);
            
            $model->saveOrFail();
            $id = $model->{$endpoint->getIdentifier()};

            if (isset($endpoint->create->after)) {
                $endpoint->create->triggerAfter($model, $data);
            }

        } else {
            $id = DB::table($endpoint->getTableName())->insertGetId(
                $data
            );
        }

        // TODO create method that finds an entity
        // TODO create a 'get' method to retrieve an entity

        $result = $this->findOne($endpoint, $id, $request, Endpoint::REQUEST_POST);

        $this->triggerHydrate(self::MODE_POST, $endpoint, $result);

        return $result;
    }

    public function put($name, $id, Request $request)
    {
        // check if authentication is required

        /** @var Endpoint $endpoint */
        $endpoint = $this->getEndpoint($name);
        abort_unless($endpoint, 404);

        $entity = $this->find($endpoint, $id);
        abort_unless($entity, 404);

        // Check permission if enabled

        // Validate the data from within api fields
        
        $rules = $endpoint->getValidationRules(Endpoint::REQUEST_PUT);

        // go through all the columns and also validate the data

        $validation = Validator::make($request->post(), $rules);
        if ($validation->fails()) {
            $response = [
                'errors' => $validation->errors()
            ];

            // distinguish between unique errors and normal errors and change to 409 if necessary
            return response($response, 400);
        }

        $data = $validation->validated();

        abort_unless($data, 400, 'No data set');

        $model = $this->createModelInstance($endpoint);

        if (method_exists($entity, 'toArray')) {
            $entityData = $entity->toArray();
        } else {
            $entityData = (array) $entity;
        }
        
        // compare and only fill data that is empty
        $data = $endpoint->fillDefaultValues($data, $entityData, Endpoint::REQUEST_PUT);

        // Unset values that should be unchangeable like ID

        if ($endpoint->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($model) {
            $entity->fillable($endpoint->getFieldNames());
            $entity->update($data);
        } else {
            DB::table($endpoint->getTableName())
                ->where($endpoint->getIdentifier(), $id)
                ->update($data);
        }

        if (isset($endpoint->update->after)) {
            $endpoint->update->triggerAfter($model, $data);
        }

        $result = $this->findOne($endpoint, $id, $request);

        $this->triggerHydrate(self::MODE_PUT, $endpoint, $result);

        return $result;
    }

    public function delete($name, $id, Request $request)
    {
        /** @var Endpoint $endpoint */
        $endpoint = $this->getEndpoint($name);
        abort_unless($endpoint, 404);

        $model = $this->find($endpoint, $id);
        abort_unless($model, 404);

        // Distinguish between model and normal db


        // compare and only fill data that is empty
        //$data = $api->fillDefaultValues($data);
        // Unset values that should be changeable like ID

        $method = $endpoint->soft_deletes ? 'update' : 'delete';

        $data = ['deleted_at' => date('Y-m-d H:i:s')];
        $entityArray = $model->toArray();

        if ($model) {
            $model->fillable($endpoint->getFieldNames());
            $affected = $model->$method($data);
        } else {
            $query = DB::table($endpoint->getTableName())
                       ->where($endpoint->getIdentifier(), $id);

            if ($endpoint->soft_deletes) {
                $affected = $query->update($data);
            } else {
                $affected = $query->delete();
            }
        }

        if (isset($endpoint->delete->after)) {
            $endpoint->delete->triggerAfter($model, $entityArray);
        }

        if ($affected) {
            $result = [];

            $this->triggerHydrate(self::MODE_DELETE, $endpoint, $result);

            return response(empty($result) ? '' : $result, 204);
        }

        abort(400, 'Unable to delete entity');
    }

    /**
     * @param string $name
     *
     * @return Endpoint
     */
    public function getEndpoint(string $name)
    {
        return $this->getApis()[str_replace('-', '_', $name)] ?? null;
    }

    /**
     * @return \Illuminate\Support\Collection|null
     */
    public function getApis()
    {
        return $this->base->api;
    }

    public function getUser()
    {
        return $this->user;
    }

    private function dataHydrate(Endpoint $api, $data)
    {
        $hasSoftDeletes = $api->soft_deletes ?? false;
        $fieldsToShow = $api->fields_show ?? [];
        $fieldsToHide = $api->fields_hidden ?? [];

        if ($fieldsToShow) {
            $fieldsToShow = explode(',', $fieldsToShow);
        }

        if ($fieldsToHide) {
            $fieldsToHide = explode(',', $fieldsToHide);
        }

        $items = [];
        foreach ($data as $datum) {
            $item = clone $datum;

            if ($hasSoftDeletes) {
                unset($item->deleted_at);
            }

            $item = (array) $item;
            if ($fieldsToHide) {
                $item = array_diff_key($item, array_flip($fieldsToHide));
            }

            if ($fieldsToShow) {
                $item = array_intersect_key($item, array_flip($fieldsToShow));
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param string $mode
     * @param Endpoint $endpoint
     * @param array $array
     * @return array
     */
    private function triggerHydrate($mode, $endpoint, &$array)
    {
        if (isset($endpoint->$mode->hydrate)) {
            $array = $endpoint->$mode->triggerHydrate($array);
        }

        return $array;
    }

    public function getBuilder(Endpoint $endpoint)
    {
        $tableName = $this->base->getTableName($endpoint);

        if ($this->base->db->driver === 'dynamoDB') {
            $model = DynamoModel::createInstance($tableName);
            $endpoint->setDynamoIndexes($model);
            $model = new DynamoBuilder($model);
        } else {
            $model = DB::table($tableName);
        }

        return $model;
    }

    public function createModelInstance(Endpoint $endpoint)
    {
        $tableName = $this->base->getTableName($endpoint);

        if ($this->base->db->driver === \ApiX\Definition\DB::DRIVER_DYNAMO_DB) {
            $model = DynamoModel::createInstance($tableName);
            $model->setKeyName($endpoint->getIdentifier());
            //$model = new DynamoBuilder($model);
        } elseif (class_exists($endpoint->getModelClassNamespace())) {
            $model = $endpoint->createModelInstance();
        } else {
            $model = DynamicModel::createInstance($tableName);
            $model->fillable($endpoint->getFieldNames());
            //$model->timestamps = false;
        }

        return $model;
    }

    /**
     * @param Endpoint $api
     * @param $id
     * @param Request $request
     *
     * @return array|mixed|null
     */
    private function findOne(Endpoint $api, $id, Request $request, $source = null)
    {
        $entity = $this->find($api, $id, null, $source);
        abort_unless($entity, 404);

        $data = $entity;
        if ($data instanceof Model) {
            $data = $data->toArray();
        }

        if (is_object($data)) {
            $data = (array) $data;
        }
        
        $data = $api->dataHydrate($data, $request);

        return $api->addRelations($data, $request->get('with'));
    }

    /**
     * @param $name
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function findOrFail($name, $id)
    {
        /** @var Endpoint $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);

        $entity = $this->find($api, $id);
        abort_unless($entity, 404);

        return $entity;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }
}
