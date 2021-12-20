<?php

namespace ApiX;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Database
{
    public $definition;
    
    public function __construct($definition)
    {
        $this->definition = $definition;
    }
    
    public function getTableName(string $tableName)
    {
        $prefix = $this->definition['db_prefix'] ?? '';
        
        return $prefix . $tableName;
    }
    
    /**
     * @param $field
     * @param Blueprint $t
     * @param $key
     *
     * @return false|string[]
     */
    public function parseColumnDefinition($field, Blueprint $t, $key)
    {
        $parts = explode('|', $field);
        $column = null;
        foreach ($parts as $part) {
            $parameters = explode(':', $part);
            $method = array_shift($parameters);
            
            //dump($key . '-'. $field, $parameters);
            $column = $column ?: $t;
            if( !method_exists($column, $method) ) {
                dump('method does not exist: ' . $method);
                continue;
            }
            
            if( $parameters ) {
                $parameters = explode(',', $parameters[0]);
            }
            
            $column->$method($key, ...$parameters);
        }
        
        return $parameters;
    }
}
