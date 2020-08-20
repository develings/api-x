<?php

namespace API\Definition;

class Rule
{
    public $name;

    public $raw;

    public $parameters;

    /**
     * Rule constructor.
     *
     * @param $name
     * @param $raw
     * @param $parameters
     */
    public function __construct($name, $raw, $parameters)
    {
        $this->name = $name;
        $this->raw = $raw;
        $this->parameters = $parameters;
    }

    public function getDefault()
    {
    }
}
