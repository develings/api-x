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
    
    public function has(string $name): bool
    {
        return (bool)($this->rules[$name] ?? false);
    }
    
    public function isNullable(): bool
    {
        return $this->has('nullable');
    }
    
    public function isUnique(): bool
    {
        return $this->has('unique');
    }
    
    public function getValidationRules(Endpoint $endpoint, array $validators = null)
    {
        $rules = [];
        foreach ($this->rules as $name => $rule) {
            if (!in_array($name, $validators, true)) {
                continue;
            }
            
            if ($name === 'unique') {
                $api = \API\API::getInstance();
                $rules[] = 'unique:' . $api->base->getTableName($endpoint) . ',creator_id';
                continue;
            }
            
            $rules[] = $name;
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