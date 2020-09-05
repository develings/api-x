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

        $this->target = $parameters[0] ?? null;
        $this->foreign_key = $parameters[1] ?? $this->name .  '_id';
        $this->owner_key = $parameters[2] ?? 'id';
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
