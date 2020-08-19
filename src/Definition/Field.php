<?php

namespace App\API\Definition;

class Field
{
	public $key;

	public $type;

	public $definition;

	public $rules = [];

    /**
     * Field constructor.
     *
     * @param $key
     * @param $definition
     */
    public function __construct($key, $definition)
    {
        $this->key = $key;
        $this->definition = $definition;

        $this->parse();
    }

    public function parse()
    {
        $parts = explode('|', $this->definition);

        $rules = [];
        foreach ($parts as $raw) {
            $rule = explode(':', $raw);
            $name = array_shift($rule);

            $parameters = [];
            if ($rule) {
                $parameters = explode(',', array_shift($rule));
            }

            $rules[$name] = new Rule($name, $raw, $parameters);
        }

        $this->rules = $rules;
    }

    public function getType()
	{

	}

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
    public function getDefault(): ?Rule
    {
        return $this->rules['default'] ?? null;
    }

    public function isHidden(): bool
    {
        return (bool)($this->rules['hidden'] ?? false);
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
