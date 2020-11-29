<?php

namespace API\Definition;

class RelationRule
{
    public $type;

    public $raw;

    public $parameters;

    public $target;

    public $owner_key;

    public $foreign_key;

    public $name;

    /**
     * Rule constructor.
     *
     * @param $raw
     */
    public function __construct($relationName, $raw)
    {
        $this->raw = $raw;
        $this->name = $relationName;

        $rule = explode(':', $raw);
        $type = array_shift($rule);
        $this->type = $type;

        $parameters = [];
        if ($rule) {
            $parameters = explode(',', array_shift($rule));
        }

        $this->parameters = $parameters;
        
        $foreignKey = $this->name . '_id';
        $ownerKey = 'id';
    
        //if ($this->type === 'hasMany') {
        //    $ownerKey = $foreignKey;
        //    $foreignKey = $ownerKey;
        //}

        $this->target = $parameters[0] ?? null;
        $this->foreign_key = $parameters[1] ?? $foreignKey;
        $this->owner_key = $parameters[2] ?? $ownerKey;
    }
    
    public function setRelationKeyDefaults($origin)
    {
        $foreignKey = $this->name . '_id';
        $ownerKey = 'id';
    
        if ($this->type === 'hasMany') {
            $foreignKey = 'id';
            $ownerKey = "{$origin}_id";
        }
    
        $this->foreign_key = $this->parameters[1] ?? $foreignKey;
        $this->owner_key = $this->parameters[2] ?? $ownerKey;
    }

    public function getDefault()
    {
    }
    
    public function toArray()
    {
        return [
            'name' => $this->name,
            'foreign_key' => $this->foreign_key,
            'owner_key' => $this->owner_key,
        ];
    }
}
