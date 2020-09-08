<?php

namespace API;

use API\Definition\Base;
use API\Definition\Endpoint;
use API\Definition\Field;
use API\DynamoDB\Migrator;
use BaoPham\DynamoDb\DynamoDbQueryBuilder;
use BaoPham\DynamoDb\Facades\DynamoDb;
use BaoPham\DynamoDb\RawDynamoDbQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class API
{
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
    
    public function __construct(string $path)
    {
        abort_unless(file_exists($path), 500, "Path given not found");
        
        $data = file_get_contents($path);
        $data = json_decode($data, 1);
        
        abort_unless($data, 505, "JSON file is empty or not valid");

        $this->definition = $data;

        $this->db = new Database($data);
        $this->base = new Base($data);

        app()->instance(self::class, $this);
    }
    
    /**
     * @return self
     */
    public static function getInstance(): self
    {
        return app()->get(self::class);
    }

    public function do()
	{
		// Read the json files and extract every section of it and add to the laravel application
		// Read database stuff
		//$data = file_get_contents(base_path('api/app.json'));
		//$data = json_decode($data, 1);
        //
		//$this->definition = $data;

		//$db = new Database($data);
		//$db->do();

		//dd($data, json_last_error(), json_last_error_msg());
	}
	
	public function migrate()
    {
        if ($this->base->db->driver === Definition\DB::DRIVER_DYNAMO_DB) {
            $migration = new Migrator();
            dd($migration->migrate($this->base));
        }
        
        dd('mysq');
    }

	//public function setModels()
    //{
    //    $model = new Model($this->definition);
    //    $model->createModel($this->definition['api'][0]);
    //}
    
    public function openApiJson()
    {
        $openAPI = new OpenAPI($this->base);
        return $openAPI->definition();
    }

	public function setRoutes()
    {
        $prefix = $this->base->endpoint ?: '';
    
        app()->routeMiddleware([
            'api.auth' => \API\Auth\Authenticate::class
        ]);

        Route::group(['prefix' => $prefix, 'middleware' => 'api.auth', 'as' => 'api'], static function() {
            Route::get('api.json',['as' => 'openapi', 'uses' => '\API\Routes@getOpenApiJson']);
            Route::get('migrate',['as' => 'migrate', 'uses' => '\API\Routes@migrate']); //
            
            Route::get('{api}', ['as' => 'index', 'uses' => '\API\Routes@index']);
            Route::post('{api}', ['as' => 'post', 'uses' => '\API\Routes@post']);
            Route::get('{api}/{id}', ['as' => 'get', 'uses' => '\API\Routes@get']);
            Route::put('{api}/{id}', ['as' => 'put', 'uses' => '\API\Routes@put']);
            Route::delete('{api}/{id}', ['as' => 'delete', 'uses' => '\API\Routes@delete']);
        });
    }


    public function index($name, Request $request)
    {
        /** @var Endpoint $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);

        $perPage = $request->get('per_page', $api->per_page ?? 25);
        $offset = $request->get('offset');

        /** @var DynamoBuilder|Builder $query */
        $query = $this->getBuilder($api);

        if ($api->order_by) {
            $query->orderByRaw($api->order_by);
        }

        if ($api->soft_deletes) {
            $query->where(static function($query) {
                $query->whereNull('deleted_at');
                //$query->orWhereNull('deleted_at');
            });
        }
        
        $query = $this->getWhereParameters($query, $api);
        
        $search = $request->get('search');
        if ($search) {
            $fields = explode(',', $api->searchable ?: '');
            $fields = $fields ? array_values($fields) : array_keys($api->fields);

            $i = 0;
            foreach ($fields as $field) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $query->$method($field, 'like',  '%' . $search . '%');
                $i++;
            }
        }
    
        if ($offset) {
            //$query->afterKey(['uuid' => $offset, 'created_at' => '2020-08-17 13:50:19']);
            //dd($query->toDynamoDbQuery(), $query->get());
        }
        
        
        if ($this->base->db === \API\Definition\DB::DRIVER_DYNAMO_DB) {
            $data = $query->paginate($perPage, $total = $query->count(), $api);
        } else {
            $data = $query->paginate($perPage);
        }
        
        $items = $api->dataHydrateItems($data->items(), $request);
        $items = $api->addRelations($items->toArray(), $request->get('with'), true);

        $output = $data->toArray();
        $output['data'] = $items;
        
        if (isset($total)) {
            $output['to'] = $total;
        }

        return $output;
    }
    
    public function getWhereParameters($query, Endpoint $api)
    {
        //dd($query, $query->toDynamoDbQuery(), $query->get());
        if (!$api->condition) {
            return $query;
        }
        
        //return $query;
        
        //$model = $query->getModel();
        //$query->setModel($model);
        //$result = DynamoDb::table($this->base->getTableName($api))
        //    ->setIndexName('device_user_uuid_index')
        //    // call set<key_name> to build the query body to be sent to AWS
        //                  ->setKeyConditionExpression('#name = :name')
        //                    //->setProjectionExpression('device_user_uuid, created_at')
        //                  ->setExpressionAttributeNames(['#name' => 'device_user_uuid'])
        //                  ->setExpressionAttributeValues([':name' => DynamoDb::marshalValue('019e98c9-384f-396c-8633-4c473c84a743')])
        //                  ->prepare()
        //    // the query body will be sent upon calling this.
        //                  ->query(); // supports any DynamoDbClient methods (e.g. batchWriteItem, batchGetItem, etc.)
        //
        //dd($result);
        //
        ////dd($this->base->getTableName($api));
        //$result = DynamoDb::table($this->base->getTableName($api))
        //                  ->setIndex('device_user_uuid_index')
        //        ->setKeyConditionExpression('#name = :name')
        //        ->setProjectionExpression('id, author_name')
        //    //// Can set the attribute mapping one by one instead
        //    //    ->setExpressionAttributeName('#name', 'author_name')
        //    //    ->setExpressionAttributeValue(':name', DynamoDb::marshalValue('Bao'))
        //                  ->prepare()
        //                  ->query();
        //
        //dd($this->base->getTableName($api), $result);
        
        
        $where = $api->condition;
        $where = is_array($where) ? $where : [$where];
        $userPlaceholders = $this->getUserPlaceholders();
        $userPlaceholdersKeys = array_keys($userPlaceholders);
        $userPlaceholdersValues = array_values($userPlaceholders);
    
        foreach ($where as $item) {
            [$type, $condition] = explode(':', $item);
            //$userInfo = $this->user->toArray();
            //$condition = str_replace('user.uuid', $this->user->uuid, $condition);
            $condition = str_replace($userPlaceholdersKeys, $userPlaceholdersValues, $condition);
            $e = explode(',', $condition);
            
            $query->$type(...$e);
        }
        //$query->decorate(function (RawDynamoDbQuery $raw) {
        //    $raw->op = 'Query';
        //    //$raw->query['KeyConditionExpression'] = $raw->query['FilterExpression'];
        //    //unset($raw->query['FilterExpression']);
        //});
        /** @var DynamoDbQueryBuilder $query */
        //$query->withIndex('device_user_uuid_index');
        
        
        //dd($query->get());
        //dd($query, $model, $query->toDynamoDbQuery(), $query->get());
        
        //
    
        
        
        return $query;
        
        //$where = str_replace(':user.uuid:', ':user')
        
        //return [
        //    $where,
        //    ['user.id' => $this->user->uuid]
        //];
    }

    public function find(Endpoint $endpoint, $id, $identifierKey = null, $source = null)
    {
        $identifierKey = $identifierKey ?: $endpoint->getIdentifier();
        if ($this->base->db->driver === \API\Definition\DB::DRIVER_MYSQL) {
            $model = DynamicModel::createInstance($this->base->getTableName($endpoint));
            $model->fillable(array_keys($endpoint->fields));
            //dd($model);
        } else {
            $model = $this->getBuilder($endpoint);
        }
        
        if ($model) {
            $query = $model->where($identifierKey, $id);
        } else {
            $query = DB::table($endpoint->getTableName())->where($identifierKey, $id);
        }
        
        $this->buildQuery($endpoint, $query, $source);

        if ( $this->base->db->driver === \API\Definition\DB::DRIVER_MYSQL ) {
            if ($endpoint->soft_deletes) {
                $query->whereNull('deleted_at');
            }
            $first = $query->first();
        } else {
            $first = $query->get()->first();
        }
        //dd($endpoint, $id, $identifierKey, $query->getQuery(), $first);
        
        //dd($query->toDynamoDbQuery(), $api);

        if ( $first && $this->base->db->driver === \API\Definition\DB::DRIVER_DYNAMO_DB && $endpoint->soft_deletes && $first->deleted_at ) {
            return null;
        }
        
        //if (!$first) {
        //    dd($first, $id, $identifierKey, $source, $query->getQuery()->toSql());
        //}
        
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
        /** @var Endpoint $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);
    
        return $this->findOne($api, $id, $request);
    }

    public function post($name, Request $request)
    {
        // check if authentication is required
        /** @var Endpoint $endpoint */
        $endpoint = $this->getEndpoint($name);
        abort_unless($endpoint, 404);
        
        //$endpoint->create->triggerAfter();

        // Check permission if enabled

        // Validate the data from within api fields
        $rules = $endpoint->getValidationRules(Endpoint::REQUEST_POST);

        //dd($api, $rules);
        // go through all the columns and also validate the data
        //$rules = ['name' => 'required', 'uid' => 'uuid']; // get the rules from the api definition
        //$rules = $api->fields;
    
    
        $requestData = $request->post();
        
        $data = $endpoint->fillDefaultValues($requestData, [], Endpoint::REQUEST_POST);
        
        //dd($data, $rules);
        
        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            $response = [
                'errors' => $validation->errors()
            ];

            // distinguish between unique errors and normal errors and change to 409 if necessary
            return response($response, 400);
        }

        $data = $validation->validated();
        
        if ($endpoint->unique) {
            //
        }
        
        //dd($data, $rules);
        
        // Check if a field is unique and then perform a query in the db
        if ($this->base->db->driver === \API\Definition\DB::DRIVER_DYNAMO_DB) {
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
            
            //dd($model, $data, $fillables);
            
            $model->saveOrFail();
            $id = $model->{$endpoint->getIdentifier()};
            
            if ($endpoint->create && $endpoint->create->after) {
                $endpoint->create->triggerAfter($model);
            }
            
        } else {
            $id = DB::table($endpoint->getTableName())->insertGetId(
                $data
            );
        }
        
        // TODO create method that finds an entity
        // TODO create a 'get' method to retrieve an entity
    
        return $this->findOne($endpoint, $id, $request, Endpoint::REQUEST_POST);
    }

    public function put($name, $id, Request $request)
    {
        // check if authentication is required

        /** @var Endpoint $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);
    
        $entity = $this->find($api, $id);
        abort_unless($entity, 404);
    
        // Check permission if enabled

        // Validate the data from within api fields
    
        //$instance = $this->createModelInstance($this->getEndpoint('device_user'));
        //$foreignKey = 'id';
        //$localKey = 'device_user_id';
        //$r = new BelongsTo($instance->newQuery(), $entity, 'device_user_id', 'id', 'device_user');
        //dd($r->getQuery()->toSql(), $r->get());
        $rules = $api->getValidationRules(Endpoint::REQUEST_PUT);
        //dd($rules);

        // go through all the columns and also validate the data
        //$rules = ['name' => 'required', 'uid' => 'uuid']; // get the rules from the api definition
        //$rules = $api->fields;
        
        $validation = Validator::make($request->post(), $rules);
        if ($validation->fails()) {
            $response = [
                'errors' => $validation->errors()
            ];

            // distinguish between unique errors and normal errors and change to 409 if necessary
            return response($response, 400);
        }

        $data = $validation->validated();
        
        //dd($data, $rules);
        abort_unless($data, 400, 'No data set');

        $model = $this->createModelInstance($api);
        
        
        if (method_exists($entity, 'toArray')) {
            $entityData = $entity->toArray();
        } else {
            $entityData = (array) $entity;
        }
        //$data = array_merge($entityData, $data);
        // compare and only fill data that is empty
        $data = $api->fillDefaultValues($data, $entityData, Endpoint::REQUEST_PUT);
        
        // Unset values that should be unchangeable like ID
        //unset($data[$api->getIdentifier()]);
        
        if ($api->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($model) {
            $entity->fillable($api->getFieldNames());
            if ($model->getFillable()) {
                //$data = array_intersect_key($data, array_flip($model->getFillable()));
            }
            //dd($entity, $data);
            //dd($data);
            $entity->update($data);
        } else {
            DB::table($api->getTableName())
                ->where($api->getIdentifier(), $id)
                ->update($data);
        }
    
        return $this->findOne($api, $id, $request);
    }

    public function delete($name, $id, Request $request)
    {
        /** @var Endpoint $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);
    
        $entity = $this->find($api, $id);
        abort_unless($entity, 404);
    
        // Distinguish between model and normal db

        //$model = $api->createModelInstance();
        //dd($entity);

        // compare and only fill data that is empty
        //$data = $api->fillDefaultValues($data);
        // Unset values that should be changeable like ID

        $method = $api->soft_deletes ? 'update' : 'delete';

        $data = ['deleted_at' => date('Y-m-d H:i:s')];

        if ($entity) {
            //$data = array_intersect_key($data, array_flip($model->getFillable()));
            //$model->fill($data);
            //
            $entity->fillable($api->getFieldNames());
            //dd($entity, $method, $data);
            $affected = $entity->$method($data);
            //$modelId = $model->{$api->identifier};
            //dd('missing');
        } else {
            $query = DB::table($api->getTableName())
                       ->where($api->getIdentifier(), $id);

            if ($api->soft_deletes) {
                $affected = $query->update($data);
            } else {
                $affected = $query->delete();
            }
        }

        if ($affected) {
            return response('', 204);
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
                //die(var_dump($fieldsToShow));
            }

            $items[] = $item;
        }

        return $items;
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
    
        if ($this->base->db->driver === \API\Definition\DB::DRIVER_DYNAMO_DB) {
            $model = DynamoModel::createInstance($tableName);
            $model->setKeyName($endpoint->getIdentifier());
            //$model = new DynamoBuilder($model);
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
    
        //$data = $api->dataHydrate($entity);
    
        $data = $api->dataHydrate($data, $request);
        
        return $api->addRelations($data, $request->get('with'));
        //$data = $api->dataHydrate($entity);
        //
        //return $api->addRelations($data, $request->get('with'));
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
