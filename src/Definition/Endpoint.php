<?php

namespace ApiX\Definition;

use ApiX\Facade\ApiX;
use ApiX\DynamoBuilder;
use ApiX\DynamoModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Concerns\ValidatesAttributes;

class Endpoint
{
    public const REQUEST_POST = 'post';
    public const REQUEST_PUT = 'put';

    /**
     * @var array
     */
    public $definition;

    public $name;

    public $model;

    public $db;

    public $timestamps;

    public $soft_deletes;

    public $unique;

    public $authentication;

    public $permission;

    public $identifier = 'id';

    public $per_page;

    public $order_by;

    /**
     * @var Field[]
     */
    public $fields;

    public $searchable;

    /**
     * @var Relation[]
     */
    public $relations;

    public $fields_show;

    public $fields_hidden;

    public $fields_cast;

    /**
     * @var EndpointPath|false
     */
    public $create;

    /**
     * @var EndpointPath|false
     */
    public $update;

    /**
     * @var EndpointPath|false
     */
    public $delete;

    private $api;

    public $sort_key;

    public $secondary_identifier;
    public $secondary_sort_key;

    /**
     * @var EndpointPath|false
     */
    public $index;

    public $condition;

    public $indexes;

    /**
     * @var EndpointPath|false
     */
    public $find;

    /**
     * @var EndpointPath|false
     */
    public $get;
    
    public $namespace;

    public function __construct(array $data)
    {
        $paths = ['index', 'create', 'update', 'delete', 'get'];
        $this->definition = $data;

        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            if ($key === 'fields') {
                foreach ($value as $field => $v) {
                    $value[$field] = new Field($field, $v);
                }
            }

            if (in_array($key, $paths, true) && $value !== false) {
                $value = new EndpointPath($value);
            }

            if ($key === 'relations') {
                foreach ($value as $field => $v) {
                    $value[$field] = new Relation($field, $v);
                }
            }

            $this->$key = $value;
        }

    }

    public function getValidationRules($request = self::REQUEST_POST)
    {
        $validationMethods = get_class_methods(ValidatesAttributes::class);

        $validators = [];
        foreach ($validationMethods as $validationMethod) {
            if (strpos($validationMethod, 'validate') === false) {
                continue;
            }
            $validators[] = Str::replaceFirst('validate_', '', Str::snake($validationMethod));
        }

        $api = ApiX::getInstance();

        if ($api->base->db->driver === DB::DRIVER_DYNAMO_DB) {
            $validators = array_values(array_diff($validators, ([
                'unique', 'exists', 'get_query_column', 'guess_column_for_query'
            ])));
        }

        $fields = $this->fields;

        $definitions = [];
        foreach ($fields as $key => $field) {
            $rules = $field->getRules();
            $def = '';

            //if ($key === 'country') {
            //    //dd($rules);
            //}

            foreach ($rules as $rule) {
                $name = $rule->name;

                if ($name === 'tinyInteger') {
                    //dd($name);
                    $name = 'number';
                }

                if (!in_array($name, $validators, true)) {
                    continue;
                }

                $path = $rule->raw;
                if ($rule->name === 'unique' && !$rule->parameters) {
                    $path = 'unique:' . $api->base->getTableName($this);
                }

                if ($rule->name === 'create_ignore' && $request === self::REQUEST_POST) {
                    continue;
                }

                $def .= ($def ? '|' : '') . $path;
            }

            if (!$def) {
                //continue;
            }

            $definitions[$key] = $def;
        }

        if ($this->relations) {
            foreach ($this->relations as $relation) {
                $relationInfo = $relation->getInfo();
                if (!$relationInfo || !in_array($relation->relationType, ['hasOne', 'belongsTo'], true)) {
                    continue;
                }
                $definitions[$relationInfo['foreign_key']] = $relation->getValidationRules($this, $validators);
            }
        }

        return $definitions;

        dd($rules, $this->fields);
    }

    public function getFieldNames()
    {
        $fields = array_keys($this->fields);

        if ($this->timestamps) {
            $fields[] = 'created_at';
            $fields[] = 'updated_at';
        }

        if ($this->soft_deletes) {
            $fields[] = 'deleted_at';
        }

        if ($this->relations) {
            foreach ($this->relations as $relation) {
                $relationInfo = $relation->getInfo();
                if (!$relationInfo || !in_array($relation->relationType, ['hasOne', 'belongsTo'], true)) {
                    continue;
                }
                $fields[] = $relationInfo['foreign_key'];
            }
        }

        return $fields;
    }

    public function getTableName(): string
    {
        $api = ApiX::getInstance();
        $prefix = $api->base->db->prefix;

        $table = $this->db ?: $this->name;
        if ($this->model) {
            /** @var Model $class */
            $class = new $this->model;

            $table = $class->getTable() ?: $table;
        }

        return $prefix . $table;
    }

    /**
     * @return string
     */
    public function getModelClassNamespace()
    {
        if ($this->namespace) {
            throw_unless(class_exists($this->namespace), 'RuntimeException', 'Namespace not found: ' . $this->namespace);
            
            return $this->namespace;
        }
        
        return "App\\Models\\" . ucfirst(Str::camel($this->name));
    }

    /**
     * @return Model|null
     */
    public function createModelInstance()
    {
        if ($this->model && class_exists($this->model)) {
            return new $this->model;
        }

        if (class_exists($this->getModelClassNamespace())) {
            return new ($this->getModelClassNamespace())();
        }

        return null;
    }

    public function fillDefaultValues(array $data, $originalData = [], $request = self::REQUEST_POST)
    {
        $api = ApiX::getInstance();
        $isDynamoDb = $api->base->db->driver === DB::DRIVER_DYNAMO_DB;
        if ($this->timestamps && $request === self::REQUEST_POST) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = $isDynamoDb ? '' : null;
        }

        if ($this->soft_deletes && $request === self::REQUEST_POST) {
            $data['deleted_at'] = $isDynamoDb ? '' : null;
        }

        $userPlaceholders = $api->getUserPlaceholders();
        $userPlaceholderKeys = $userPlaceholders ? array_keys($userPlaceholders) : [];

        foreach ($this->fields as $key => $field) {
            if ( isset($field->rules['id'], $data[ $field->key ]) ) {
                unset($data[$field->key]);
                continue;
            }

            if (isset($field->rules['password'], $data[ $field->key ]) || isset($field->rules['hash'], $data[ $field->key ])) {
                $data[$key] = Hash::make($data[$key]);
                continue;
            }

            if ($request === self::REQUEST_PUT && isset($originalData[$key])) {
                continue;
            }


            $data = $this->setDefaultValue($field, $data, $key, $userPlaceholderKeys, $userPlaceholders);
        }

        if ($this->relations) {
            foreach ($this->relations as $key => $relation) {
                if ($relation->relationType !== 'hasOne' && $relation->relationType !== 'belongsTo') {
                    continue;
                }

                $data = $this->setDefaultValue($relation, $data, $relation->getInfo()['foreign_key'], $userPlaceholderKeys, $userPlaceholders);
            }
        }

        return $data;
    }

    public function getIdentifier()
    {
        return $this->identifier ?: 'id';
    }

    public function dataHydrate($data, Request $request = null)
    {
        if ($data instanceof Model) {
            $data = $data->toArray();
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if ($this->soft_deletes && $data && array_key_exists('deleted_at', $data)) {
            unset($data['deleted_at']);
        }

        $selectedFields = [];
        if ($request && ($sentFields = $request->get('fields'))) {
            $selectedFields = explode(',', $sentFields);
        }
        //dd($selectedFields, $request);

        $output = [];
        foreach ($this->fields as $field) {
            if (!isset($data[$field->key])) {
                continue;
            }

            if ($field->isHidden()) {
                continue;
            }

            if ($selectedFields && !in_array($field->key, $selectedFields, true)) {
                continue;
            }

            $output[$field->key] = $field->cast($data[$field->key], $data);
        }

        if ($this->relations) {
            foreach ($this->relations as $relation) {
                $key = $relation->getRelationRule()->foreign_key;
                if (!isset($data[$key])) {
                    continue;
                }

                if ($relation->isHidden()) {
                    continue;
                }

                $output[$key] = $relation->cast($data[$key], $data);
            }
        }

        if ($data && $this->timestamps) {
            if (!$selectedFields || in_array('created_at', $selectedFields, true)) {
                $output['created_at'] = $output['created_at'] ?? $data['created_at'] ?? null;
            }
            if (!$selectedFields || in_array('updated_at', $selectedFields, true)) {
                $output['updated_at'] = $output['updated_at'] ?? $data['updated_at'] ?? null;
            }
        }

        //dd($data, $output, $this->relations);

        return $output;
    }

    public function addRelations($data, $relations = null, $multiple = false)
    {
        if (!$this->relations) {
            return $data;
        }

        $relations = $relations ? explode(',', $relations) : $relations;

        if (!$relations) {
            return $data;
        }

        // Relations look like this:
        // user:80,desc,search value

        /** @var Relation[] $relationsAll */
        $relationsAll = collect($this->relations)->keyBy('key');

        foreach ($relations as $relation) {
            $relation = explode(':', $relation);
            $name = array_shift($relation);

            $relationGiven = $relationsAll[$name] ?? null;
            abort_unless($relationGiven, 404, 'Relation not found');

            $data = $relationGiven->getData($this, $data, $multiple);
            $data = !$multiple ? $data[0] : $data;

            //$data = array_merge($data, $relationData);
        }

        return $data;
    }

    public function dataHydrateItems($items, Request $request = null)
    {
        $items = $items instanceof Collection ? $items : collect($items);
        return $items->map(function($item) use($request){
            return $this->dataHydrate((array) $item, $request);
        });
    }

    public function getRelationKeys()
    {
        return $this->relations ? array_keys($this->relations) : null;
    }

    public function getField(string $name)
    {
        return $this->fields[$name];
    }

    public function setDynamoIndexes(DynamoModel $model)
    {
        $model->setKeyName($this->getIdentifier());
        if (!$this->indexes) {
            return false;
        }

        $indexes = [];
        foreach ($this->indexes as $index => $values) {
            $data = explode(',', $values);
            $indexes[$index] = [
                'hash' => $data[0]
            ];

            if (isset($data[1])) {
                $indexes[$index]['range'] = $data[1];
            }
        }

        $model->setDynamoDbIndexKeys($indexes);
    }

    public function getBuilder()
    {
        $api = app()->get(ApiX::class);
        $tableName = $api->base->getTableName($this);

        if ($api->base->db->driver === 'dynamoDB') {
            $model = DynamoModel::createInstance($tableName);
            $this->setDynamoIndexes($model);
            $model = new DynamoBuilder($model);
        } else {
            $model = \Illuminate\Support\Facades\DB::table($tableName);
        }

        return $model;
    }

    /**
     * @param Field|Relation $field
     * @param array $data
     * @param int $key
     * @param array $userPlaceholderKeys
     * @param array $userPlaceholders
     *
     * @return array
     */
    private function setDefaultValue($field, array $data, string $key, array $userPlaceholderKeys, array $userPlaceholders): array
    {
        $default = $field->getDefault();

        if( !$default ) {
            return $data;
        }

        $parameters = $default->parameters;
        $parameterMethod = $parameters[0] ?? null;
        if( $parameterMethod === 'alphanumeric' ) {
            $data[ $key ] = Str::random($parameters[1] ?? 12);
        } else if( $parameterMethod === 'uuid' ) {
            $data[ $key ] = Str::uuid()->toString();
        // } else if( $parameterMethod === 'hashid' ) {
        //     $hashId = new \Hashids\Hashids();
        //     $data[ $key ] = $hashId->encode($id);
        } else if( $userPlaceholderKeys && in_array($parameterMethod, $userPlaceholderKeys, true) ) {
            $data[ $key ] = $userPlaceholders[ $parameterMethod ];
        } else {
            $data[ $key ] = $parameterMethod;
        }

        return $data;
    }
}
