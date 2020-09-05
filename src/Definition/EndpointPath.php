<?php

namespace API\Definition;

class EndpointPath
{
    public $where;
    
    public $authentication;
    
    public $fill;
    
    public function __construct(array $values = [])
    {
        foreach ($values as $k => $value) {
            if (property_exists($this, $k)) {
                $this->$k = $value;
            }
        }
    }
}