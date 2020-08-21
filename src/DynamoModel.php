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
        $items = $this->get();
        dd('items', $items);
    }
}