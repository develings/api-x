<?php

namespace API\Definition;

class DB
{
    public $driver = 'mysql';
    
    public $prefix = '';
    
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}