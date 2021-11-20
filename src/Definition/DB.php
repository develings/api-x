<?php

namespace ApiX\Definition;

class DB
{
    public $driver = self::DRIVER_MYSQL;
    
    public $prefix = '';
    
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_DYNAMO_DB = 'dynamoDB';
    
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
