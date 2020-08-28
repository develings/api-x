<?php

namespace API;

class DynamicModel extends \Illuminate\Database\Eloquent\Model
{
    public function __construct(string $table, array $attributes = [])
    {
        $this->table = $table;

        parent::__construct($attributes);
    }

    /**
     * Store the relations
     *
     * @var array
     */
    //private $dynamic_relations = [];

    ///**
    // * Add a new relation
    // *
    // * @param $name
    // * @param $closure
    // */
    //public function addDynamicRelation($name, $closure)
    //{
    //    $this->$dynamic_relations[$name] = $closure;
    //}
    //
    ///**
    // * Determine if a relation exists in dynamic relationships list
    // *
    // * @param $name
    // *
    // * @return bool
    // */
    //public function hasDynamicRelation($name)
    //{
    //    return array_key_exists($name, $this->$dynamic_relations);
    //}
    //
    ///**
    // * If the key exists in relations then
    // * return call to relation or else
    // * return the call to the parent
    // *
    // * @param $name
    // *
    // * @return mixed
    // */
    //public function __get($name)
    //{
    //    if ($this->hasDynamicRelation($name)) {
    //        // check the cache first
    //        if ($this->relationLoaded($name)) {
    //            return $this->relations[$name];
    //        }
    //
    //        // load the relationship
    //        return $this->getRelationshipFromMethod($name);
    //    }
    //
    //    return parent::__get($name);
    //}
    //
    ///**
    // * If the method exists in relations then
    // * return the relation or else
    // * return the call to the parent
    // *
    // * @param $name
    // * @param $arguments
    // *
    // * @return mixed
    // */
    //public function __call($name, $arguments)
    //{
    //    if ($this->hasDynamicRelation($name)) {
    //        return call_user_func($this->$dynamic_relations[$name], $this);
    //    }
    //
    //    return parent::__call($name, $arguments);
    //}
}
