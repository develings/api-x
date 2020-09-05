<?php

namespace API\Definition;

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
    
    public function getValidationRules()
    {
        $rules = [];
        if ($this->isUnique()) {
            $rules[] = 'unique';
        }
        if ($this->isNullable()) {
            $rules[] = 'nullable';
        }
        
        return $rules ? implode('|', $rules) : '';
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