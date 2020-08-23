<?php

namespace API;

use API\Definition\Base;
use API\Definition\Endpoint;
use BaoPham\DynamoDb\Facades\DynamoDb;
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
    public $base;

    public function __construct(string $path)
    {
        \abort_unless(file_exists($path), 500, "Path given not found");
        
        $data = file_get_contents($path);
        $data = json_decode($data, 1);
        
        \abort_unless($data, 505, "JSON file is empty or not valid");

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
        if ($this->base->db->driver === \API\Definition\DB::DRIVER_DYNAMO_DB) {
            $migration = new \API\DynamoDB\Migration();
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

        Route::get('api.json', '\API\Routes@getOpenApiJson');
        Route::get('migrate', '\API\Routes@migrate');

        Route::group(['prefix' => $prefix], function() {
            Route::get('{api}', ['as' => 'api.index', 'uses' => '\API\Routes@index']);
            Route::post('{api}', ['as' => 'api.post', 'uses' => '\API\Routes@post']);
            Route::get('{api}/{id}', ['as' => 'api.get', 'uses' => '\API\Routes@get']);
            Route::put('{api}/{id}', ['as' => 'api.put', 'uses' => '\API\Routes@put']);
            Route::delete('{api}/{id}', ['as' => 'api.delete', 'uses' => '\API\Routes@delete']);
        });
    }


    public function index($name, Request $request)
    {
        /** @var API $api */
        $api = $this->getEndpoint($name);
        abort_unless($api, 404);

        /** @var Endpoint $api */
        //$page = $request->get('page', 1);
        $perPage = $request->get('per_page', $api->per_page ?? 25);

        $query = $this->getBuilder($api);
        //$query = DB::table($tableName);

        if ($api->order_by) {
            $query->orderByRaw($api->order_by);
        }

        if ($api->soft_deletes ?? false) {
            $query->whereNull('deleted_at');
        }

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

        $data = $query->paginate($perPage);
        
        $items = $api->dataHydrateItems($data->items());
        $items = $api->addRelations($items->toArray(), $request->get('with'), true);

        $output = $data->toArray();
        $output['data'] = $items;

        return $output;
    }

    public function find(Endpoint $api, $id)
    {
        $model = $this->getBuilder($api);
        if ($model) {
            $query = $model->where($api->getIdentifier(), $id);
        } else {
            $query = DB::table($api->getTableName())->where($api->getIdentifier(), $id);
        }

        if ($api->soft_deletes) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
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
        $rules = $api->getValidationRules();

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
        //$model = $this->getBuilder($api);
        $model = $this->createModelInstance($api);

        $data = $api->fillDefaultValues($data);

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

        dd($rules, $inserted, $model->toArray());
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
        $rules = $api->getValidationRules();

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

        $model = $api->createModelInstance();

        // compare and only fill data that is empty
        //$data = $api->fillDefaultValues($data);
        // Unset values that should be changeable like ID
        unset($data[$api->getIdentifier()]);

        if ($api->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($model) {
            $data = array_intersect_key($data, array_flip($model->getFillable()));
            $model->fill($data);

            $model->update();
        } else {
            $affected = DB::table($api->getTableName())
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

        $model = $api->createModelInstance();

        // compare and only fill data that is empty
        //$data = $api->fillDefaultValues($data);
        // Unset values that should be changeable like ID

        $method = $api->soft_deletes ? 'update' : 'delete';

        $data = ['deleted_at' => date('Y-m-d H:i:s')];

        if ($model) {
            //$data = array_intersect_key($data, array_flip($model->getFillable()));
            //$model->fill($data);
            //
            //$model->update();
            //$modelId = $model->{$api->identifier};
            dd('missing');
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
}
