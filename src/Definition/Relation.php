<?php

namespace ApiX\Definition;

use ApiX\ApiX;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use ApiX\Definition\Base;

/**
 * Class Relation
 * @package ApiX\Definition
 * @property RelationRule[] $rules
 */
class Relation
{
    use RuleTrait;

    public $key;

    public $definition;

    public $relationType;

    /**
     * Field constructor.
     *
     * @param $key
     * @param $definition
     */
    public function __construct($key, $definition)
    {
        $this->key = $key;
        $this->definition = $definition;

        $this->parse();
    }

    public function getRelationTypesSingle()
    {
        return [
            'hasOne',
            'belongsTo',
            'belongsToMany',
            'hasOneThrough',
            'morphTo',
            'morphToMany',
            'morphedToMany',
        ];
    }

    public function getRelationTypesMultiple()
    {
        return [
            'hasMany',
            'hasManyThrough'
        ];
    }
    
    public function getRelationRule(): ?RelationRule
    {
        return $this->rules[$this->relationType] ?? null;
    }
    
    /**
     * @param Endpoint $api
     * @param $data
     * @param false $multiple
     *
     * @return array|null
     */
    public function getData($api, $data, $multiple = false)
    {
        // differentiate between an array of data or one item of data
        $ids = [];
        $items = $multiple ? $data : [$data];

        $relationTypes = $this->getRelationTypesSingle();
        $relationTypesMany = $this->getRelationTypesMultiple();

        $relationTypesAll = array_merge($relationTypes, $relationTypesMany);
        /** @var ApiX $apiClass */
        $apiClass = app()->get(ApiX::class);

        $collection = collect($items);
        //dd($collection);

        foreach ($this->rules as $methodName => $rule) {
            if (!in_array($methodName, $relationTypesAll, true)) {
                continue;
            }

            if (!$rule->target) {
                continue;
            }

            /** @var Endpoint $relation */
            $relation = $apiClass->getEndpoint($rule->target);

            abort_unless($relation, 501, 'Relation not found');
            
            $rule->setRelationKeyDefaults($api->name);

            //$collectionKeyed = $collection->keyBy($rule->type === 'belongsTo' ? $rule->owner_key : $rule->foreign_key);
            $collectionKeyed = $collection->keyBy($rule->foreign_key);
            //$collectionKeyed = $collection->keyBy($rule->type === 'hasMany' ? $rule->owner_key : $rule->foreign_key);
            $ids = $collectionKeyed->filter()->toArray();
            //dd($ids, $collectionKeyed);

            $result = $this->$methodName($api, $rule, $relation, $data, array_keys($ids));
            //dd($methodName, $rule, $result, $collection);

            if (!$result) {
                continue;
            }

            $collectionArray = $collectionKeyed->toArray();
            $isMany = in_array($methodName, $relationTypesMany, true);

            //dd($result);
            foreach ($result as $id => $item) {
                if (is_object($item)) {
                    if (method_exists($item, 'toArray')) {
                        $item = $item->toArray();
                    } else {
                        $item = (array) $item;
                    }
                }
                $item = is_array($item) ? $item : $item->toArray();
                //dd($item, $items);
                $dataItem = $collectionArray[$item[$rule->owner_key]] ?? null;
                if (!$dataItem) {
                    // item reference not found
                    continue;
                }

                $itemFormatted = $relation->dataHydrate($item);
                //dd($itemFormatted, $dataItem);

                if (!isset($dataItem[$this->key])) {
                    $collectionArray[$item[$rule->owner_key]][$this->key] = $isMany ? [] : $itemFormatted;
                }
                
                if ($isMany) {
                    $collectionArray[$item[$rule->owner_key]][$this->key][] = $itemFormatted;
                }
            }
        }

        // dd($data, $ids, $collectionArray, $isMany, $result, $this->rules);

        return ($collectionArray ?? null) ? array_values($collectionArray) : null;
    }

    public function parse()
    {
        $parts = explode('|', $this->definition);

        $types = array_merge($this->getRelationTypesSingle(), $this->getRelationTypesMultiple());
        $rules = [];
        foreach ($parts as $raw) {
            $relation = new RelationRule($this->key, $raw);
            $rules[$relation->type] = $relation;

            if (in_array($relation->type, $types, true)) {
                $this->relationType = $relation->type;
            }
        }

        $this->rules = $rules;
    }

    public function belongsTo(Endpoint $api, RelationRule $rule, Endpoint $relation, $data, $ids)
    {
        if (!$ids ){
            return null;
        }

        $builder = DB::table($this->getAPI()->base->getTableName($relation));

        $builder->whereIn($rule->owner_key, $ids);

        if ($relation->soft_deletes) {
            $builder->whereNull('deleted_at');
        }

        $result = $builder->get();

        if (!$result) {
            return $result;
        }

        return $result->keyBy($rule->owner_key);
    }

    public function hasOne(Endpoint $api, RelationRule $rule, Endpoint $relation, $data, $ids)
    {
        $builder = DB::table($relation->getTableName());

        $foreignKey = $rule->parameters[1] ?? 'id';
        $ownerKey = $rule->parameters[2] ?? 'id';
        $builder->whereIn($ownerKey, $ids);

        if ($relation->soft_deletes) {
            $builder->whereNull('deleted_at');
        }

        $result = $builder->get();

        if (!$result) {
            return $result;
        }

        //dd($result, $foreignKey, $ownerKey, $relation, $rule, $ids, $builder->toSql());

        return $result->keyBy($ownerKey);
    }

    public function getInfo()
    {
        $rule = reset($this->rules);
        if (!$rule) {
            return [];
        }

        return $rule->toArray();
    }

    /**
     * @return ApiX
     */
    public function getAPI()
    {
        return app()->get(ApiX::class);
    }

    public function hasMany(Endpoint $api, RelationRule $rule, Endpoint $relation, $data, $ids)
    {
        //$builder = DB::table($relation->getTableName());
        $builder = $this->getAPI()->getBuilder($relation);
        //dd($api, $rule, $relation, $data, $ids);

        if (!$ids) {
            return null;
        }

        $foreignKey = $rule->parameters[1] ?? $rule->foreign_key;
        $ownerKey = $rule->parameters[2] ?? $rule->owner_key;
        $builder->whereIn($ownerKey, $ids);
        //$builder->where('company_uuid', 'ddd8e28a-4ce9-45ce-b699-828b0bbfd67f');

        //dd($ownerKey, $ids);
        //dd($ids, 'asdfa', $ownerKey, $foreignKey);
        if ($relation->soft_deletes) {
            $builder->where(static function($query) {
                $query->where('deleted_at', '');
                $query->orWhereNull('deleted_at');
            });
        }

        //$builder->limit(25);

        $result = $builder->get();

        //dd($result, $ownerKey, $ids);

        if (!$result) {
            return $result;
        }

        //dd($result, $foreignKey, $ownerKey, $relation, $rule, $ids, $builder->toSql());

        return $result->keyBy($foreignKey);
    }
}
