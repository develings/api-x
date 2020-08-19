<?php

namespace App\API\Definition;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\API\Definition\Base;

class Relation
{
    public $key;

    public $definition;

    /**
     * @var RelationRule[]
     */
    public $rules = [];

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

    public function getData($api, $data, $multiple = false)
    {
        // differentiate between an array of data or one item of data
        $ids = [];
        $items = $multiple ? $data : [$data];

        $relationTypes = [
            'hasOne',
            'belongsTo',
            'belongsToMany',
            'haeOneThrough',
            'morphTo',
            'morphToMany',
            'morphedToMany',
        ];

        $relationTypesMany = [
            'hasMany',
            'hasManyThrough'
        ];

        $relationTypesAll = array_merge($relationTypes, $relationTypesMany);
        $base = resolve(Base::class);

        $collection = collect($items);

        foreach ($this->rules as $methodName => $rule) {
            if (!in_array($methodName, $relationTypesAll, true)) {
                continue;
            }

            if (!$rule->target) {
                continue;
            }

            /** @var Endpoint $relation */
            $relation = $base->getEndpoint($rule->target);

            abort_unless($relation, 500, 'Relation not found');

            $collectionKeyed = $collection->keyBy($rule->foreign_key);
            $ids = $collectionKeyed->filter()->toArray();

            $result = $this->$methodName($api, $rule, $relation, $data, $ids);

            if (!$result) {
                continue;
            }

            $collectionArray = $collectionKeyed->toArray();
            // dump($collectionArray, $result);
            $isMany = in_array($methodName, $relationTypesMany, true);

            foreach ($result as $id => $item) {
                $item = (array) $item;
                $dataItem = $collectionArray[$item[$rule->owner_key]] ?? null;
                if (!$dataItem) {
                    // item reference not found
                    continue;
                }

                $itemFormatted = $relation->dataHydrate($item);

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

        $rules = [];
        foreach ($parts as $raw) {
            $relation = new RelationRule($this->key, $raw);
            $rules[$relation->type] = $relation;
        }

        $this->rules = $rules;
    }

    public function belongsTo(Endpoint $api, RelationRule $rule, Endpoint $relation, $data, $ids)
    {
        $builder = DB::table($relation->getTableName());

        $relationId = $rule->parameters[2] ?? 'id';
        $builder->whereIn($relationId, $ids);

        if ($relation->soft_deletes) {
            $builder->whereNull('deleted_at');
        }

        $result = $builder->get();

        if (!$result) {
            return $result;
        }

        return $result->keyBy($relationId);
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

    public function hasMany(Endpoint $api, RelationRule $rule, Endpoint $relation, $data, $ids)
    {
        $builder = DB::table($relation->getTableName());
        //dd($api, $rule, $relation, $data, $ids);

        if (!$ids) {
            return null;
        }

        $foreignKey = $rule->parameters[1] ?? 'id';
        $ownerKey = $rule->parameters[2] ?? 'id';
        $builder->whereIn($ownerKey, $ids);

        if ($relation->soft_deletes) {
            $builder->whereNull('deleted_at');
        }

        $builder->limit(25);

        $result = $builder->get();

        if (!$result) {
            return $result;
        }

        //dd($result, $foreignKey, $ownerKey, $relation, $rule, $ids, $builder->toSql());

        return $result->keyBy($foreignKey);
    }
}
