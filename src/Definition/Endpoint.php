<?php

namespace API\Definition;

use API\API;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Concerns\ValidatesAttributes;

class Endpoint
{
    const REQUEST_POST = 'post';
    const REQUEST_PUT = 'put';
    
    public $name;

    public $model;

    public $db;

    public $timestamps;

    public $soft_deletes;

    public $authenticate;

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

    public $create;

    public $update;

    private $api;

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key === 'fields') {
                    foreach ($value as $field => $v) {
                        $value[$field] = new Field($field, $v);
                    }
                }

                if ($key === 'relations') {
                    foreach ($value as $field => $v) {
                        $value[$field] = new Relation($field, $v);
                    }
                }
                $this->$key = $value;
            }
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
        
        /** @var API $base */
        $api = app()->get(API::class);
        
        if ($api->base->db->driver === DB::DRIVER_DYNAMO_DB) {
            $validators = array_values(array_diff($validators, ([
                'unique', 'exists', 'get_query_column', 'guess_column_for_query'
            ])));
        }
        
        $definitions = [];
        foreach ($this->fields as $key => $field) {
            $rules = $field->getRules();
            $def = '';

            foreach ($rules as $rule) {
                $name = $rule->name;

                if (!in_array($name, $validators, true)) {
                    continue;
                }

                $path = $rule->name;
                if ($name === 'unique' && !$rule->parameters) {
                    $path .= ':' . $this->db;
                }
                
                if ($name === 'create_ignore' && $request === self::REQUEST_POST) {
                    continue;
                }

                $def .= ($def ? '|' : '') . $path;
            }
            
            if (!$def) {
                continue;
            }

            $definitions[$key] = $def;
        }

        return $definitions;

        dd($rules, $this->fields);
    }

    public function getTableName(): string
    {
        $table = $this->db ?: $this->name;
        if ($this->model) {
            /** @var Model $class */
            $class = new $this->model;

            $table = $class->getTable() ?: $table;
        }

        return $table;
    }

    /**
     * @return Model|null
     */
    public function createModelInstance()
    {
        if ($this->model && class_exists($this->model)) {
            return new $this->model;
        }

        return null;
    }

    public function fillDefaultValues(array $data, $request = self::REQUEST_POST)
    {
        if ($this->timestamps && $request === self::REQUEST_POST) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = "";
        }
    
        if ($this->soft_deletes && $request === self::REQUEST_POST) {
            $data['deleted_at'] = "";
        }

        foreach ($this->fields as $key => $field) {
            if ( isset($field->rules['id'], $data[ $field->key ]) ) {
                unset($data[$field->key]);
                continue;
            }

            if (isset($field->rules['password'], $data[ $field->key ]) || isset($field->rules['hash'], $data[ $field->key ])) {
                $data[$key] = Hash::make($data[$key]);
                continue;
            }

            $default = $field->getDefault();
            

            if (!$default) {
                continue;
            }

            if ($request === self::REQUEST_PUT && isset($data[$key])) {
                continue;
            }
            
            $parameters = $default->parameters;
            $parameterMethod = $parameters[0] ?? null;
            if ($parameterMethod === 'alphanumeric') {
                $data[$key] = Str::random($parameters[1] ?? 12);
            } else if ($parameterMethod === 'uuid') {
                $data[$key] = Str::uuid()->toString();
            } else {
                $data[$key] = $parameterMethod;
            }
        }

        return $data;
    }

    public function getIdentifier()
    {
        return $this->identifier ?: 'id';
    }

    public function dataHydrate($data)
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

        $output = [];
        foreach ($this->fields as $field) {
            if (!isset($data[$field->key])) {
                continue;
            }

            if ($field->isHidden()) {
                continue;
            }

            $output[$field->key] = $field->cast($data[$field->key]);
        }

        if ($data && $this->timestamps) {
            $output['created_at'] = $output['created_at'] ?? $data['created_at'] ?? null;
            $output['updated_at'] = $output['updated_at'] ?? $data['updated_at'] ?? null;
        }

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
            $data = $multiple ? $data[0] : $data;

            //$data = array_merge($data, $relationData);
        }

        return $data;
    }

    public function dataHydrateItems($items)
    {
        $items = $items instanceof Collection ? $items : collect($items);
        return $items->map(function($item) {
            return $this->dataHydrate((array) $item);
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
}
