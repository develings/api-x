<?php

namespace ApiX\Definition;

class Base
{
    public $name;

    public $version;

    public $description;

    public $endpoint;

    public $db_prefix;
    
    /**
     * Possible values: token|guest|bearer
     * @var string
     */
    public $authentication = 'token:user:api_key';

    public $events;

    /**
     * @var Endpoint[]
     */
    public $api;

    public $throttle;

    public $rate_limit;

    /**
     * @var DB
     */
    public $db;

    public $servers;
    
    public $hash_salt = 'env:APP_HASH_SALT';
    
    public $key = 'env:APP_KEY';

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key === 'api') {
                    $apis = [];
                    foreach ($value as $field => $v) {
                        $apis[$v['name']] = new Endpoint($v);
                    }
                    $value = $apis;
                }

                if ($key === 'db') {
                    $value = new DB($value);
                }

                $this->$key = $value;
            }
        }

        if (!$this->db) {
            $this->db = new DB();
        }
    }

    public function getTableName(Endpoint $endpoint)
    {
        return $endpoint->getTableName();

        $prefix = $this->db->prefix ?? '';

        return $prefix . ($endpoint->getTableName());
    }
}
