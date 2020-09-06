<?php

namespace API\Definition;

use API\API;

trait RuleTrait
{
    public $rules = [];
    
    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
    
    /**
     * @return Rule|null
     */
    public function getDefault()
    {
        return $this->rules['default'] ?? null;
    }
    
    public function isHidden(): bool
    {
        return (bool)($this->rules['hidden'] ?? false);
    }
    
    public function isNullable(): bool
    {
        return (bool)($this->rules['nullable'] ?? false);
    }
    
    public function isUnique(): bool
    {
        return (bool)($this->rules['unique'] ?? false);
    }
    
    public function getValidationRules(Endpoint $endpoint)
    {
        $rules = [];
        if ($this->isUnique()) {
            $api = \API\API::getInstance();
            $rules[] = 'unique:' . $api->base->getTableName($endpoint) . ',creator_id';
        } else if ($this->isNullable()) {
            $rules[] = 'nullable';
        }
        
        return $rules;
    }
    
    public function cast($value)
    {
        $cast = $this->rules['cast'] ?? null;
        if ($value && $cast && $cast->parameters[0] === 'timestamp') {
            $value = strtotime($value);
        }
        
        return $value;
    }
}