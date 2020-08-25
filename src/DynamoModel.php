<?php

namespace API;

class DynamoModel extends \BaoPham\DynamoDb\DynamoDbModel
{
    public static function createInstance($table, array $attributes = [])
    {
        $instance = new self($attributes);
        $instance->setTable($table);
        
        return $instance;
    }
    
    public function items()
    {
        return $this->get();
    }
    
    public function fill(array $data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        
        return $this;
    }
}