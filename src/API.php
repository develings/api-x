<?php

namespace API;

use API\Definition\Base;
use API\Definition\Endpoint;
use API\Definition\Field;
use API\DynamoDB\Migrator;
use BaoPham\DynamoDb\DynamoDbQueryBuilder;
use BaoPham\DynamoDb\Facades\DynamoDb;
use BaoPham\DynamoDb\RawDynamoDbQuery;
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

	public function setModels()
    {
        $model = new Model($this->definition);
        $model->createModel($this->definition['api'][0]);
    }
    
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

        Route::group(['prefix' => $prefix, 'middleware' => 'api.auth', 'as' => 'api'], function() {
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

        /** @var DynamoBuilder|Builder $query */
        $query = $this->getBuilder($api);

        if ($api->order_by) {
            $query->orderByRaw($api->order_by);
        }

        if ($api->soft_deletes) {
            $query->where(static function($query) {
                $query->where('deleted_at', '');
                $query->orWhereNull('deleted_at');
            });
        }
        
        //if ($api->condition) {
            $query = $this->getWhereParameters($query, $api);
        //}
    
        //$query->where('device_user_uuid', '0a34b211-9ca4-3092-9638-25c0290d30ef');
    
        //$query->toDynamoDbQuery()->op = 'Query';
        //dd($query);
        
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
        
        $data = $query->paginate($perPage, $total = $query->count(), $api);
        
        $items = $api->dataHydrateItems($data->items());
        $items = $api->addRelations($items->toArray(), $request->get('with'), true);

        $output = $data->toArray();
        $output['data'] = $items;
        $output['to'] = $total;

        return $output;
    }
    
    public function getWhereParameters($query, Endpoint $api)
    {
        //dd($query, $query->toDynamoDbQuery(), $query->get());
        if (!$api->condition) {
            return $query;
        }
        
        $model = $query->getModel();
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
    
        foreach ($where as $item) {
            [$type, $condition] = explode(':', $item);
            //$userInfo = $this->user->toArray();
            $condition = str_replace('user.uuid', $this->user->uuid, $condition);
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

    public function find(Endpoint $api, $id, $identifierKey = null)
    {
        $identifierKey = $identifierKey ?: $api->getIdentifier();
        $model = $this->getBuilder($api);
        
        if ($model) {
            $query = $model->where($identifierKey, $id);
        } else {
            $query = DB::table($api->getTableName())->where($identifierKey, $id);
        }

        if ($api->soft_deletes) {
            if ($this->base->db->driver === Definition\DB::DRIVER_DYNAMO_DB) {
                $query->where(static function($query) {
                    $query->where('deleted_at', '');
                    $query->orWhereNull('deleted_at');
                });
            } else {
                $query->whereNull('deleted_at');
            }
        }
        
        //dd($query->toDynamoDbQuery());
        
        return $query->get()->first();
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
        /** @var Endpoint $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);

        // Check permission if enabled

        // Validate the data from within api fields
        $rules = $api->getValidationRules(Endpoint::REQUEST_POST);

        // go through all the columns and also validate the data
        //$rules = ['name' => 'required', 'uid' => 'uuid']; // get the rules from the api definition
        //$rules = $api->fields;

        $validation = Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            $response = [
                'errors' => $validation->errors()
            ];

            // distinguish between unique errors and normal errors and change to 409 if necessary
            return response($response, 400);
        }

        $data = $validation->validated();
        
        // Check if a field is unique and then perform a query in the db
        if ($this->base->db->driver === \API\Definition\DB::DRIVER_DYNAMO_DB) {
            /** @var Field[] $fields */
            collect($api->fields)->filter(static function(Field $item) {
                return $item->isUnique();
            })->each(function($field) use($data, $api) {
                abort_if(
                    isset($data[$field->key]) && $this->find($api, $data[$field->key], $field->key),
                    409,
                    sprintf('Entity with given field (%s) value already exists.', $field->key)
                );
            });
        }
        
        //$model = $this->getBuilder($api);
        $model = $this->createModelInstance($api);

        $data = $api->fillDefaultValues($data, Endpoint::REQUEST_POST);
        
        if ($model) {
            $fillables = $model->getFillable();
            $data = $fillables ? array_intersect_key($data, array_flip($fillables)) : $data;
            $model->fill($data);
            
            $model->saveOrFail();
            $id = $model->{$api->getIdentifier()};
        } else {
            $id = DB::table($api->getTableName())->insertGetId(
                $data
            );
        }
        
        // TODO create method that finds an entity
        // TODO create a 'get' method to retrieve an entity
    
        return $this->findOne($api, $id, $request);
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
        $rules = $api->getValidationRules(Endpoint::REQUEST_PUT);

        // go through all the columns and also validate the data
        //$rules = ['name' => 'required', 'uid' => 'uuid']; // get the rules from the api definition
        //$rules = $api->fields;

        $validation = Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            $response = [
                'errors' => $validation->errors()
            ];

            // distinguish between unique errors and normal errors and change to 409 if necessary
            return response($response, 400);
        }

        $data = $validation->validated();
        abort_unless($data, 400, 'No data set');

        $model = $this->createModelInstance($api);
        
        $data = array_merge($entity->toArray(), $data);
        // compare and only fill data that is empty
        $data = $api->fillDefaultValues($data, Endpoint::REQUEST_PUT);
        
        // Unset values that should be unchangeable like ID
        //unset($data[$api->getIdentifier()]);
        
        if ($api->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        //dd($data);

        if ($model) {
            if ($model->getFillable()) {
                $data = array_intersect_key($data, array_flip($model->getFillable()));
            }
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
        return $this->getApis()[$name] ?? null;
    }

    /**
     * @return \Illuminate\Support\Collection|null
     */
    public function getApis()
    {
        return $this->base->api;
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
    
        if ($this->base->db->driver === 'dynamoDB') {
            $model = DynamoModel::createInstance($tableName);
            $model->setKeyName($endpoint->getIdentifier());
            //$model = new DynamoBuilder($model);
        } else {
            $model = DB::table($tableName);
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
    private function findOne(Endpoint $api, $id, Request $request)
    {
        $entity = $this->find($api, $id);
        abort_unless($entity, 404);
        
        $data = $api->dataHydrate($entity);
        
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
