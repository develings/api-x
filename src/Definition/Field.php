<?php

namespace API\Definition;

/**
 * Class Field
 * @package API\Definition
 * @property Rule[] $rules
 */
class Field
{
    use RuleTrait;

	public $key;

	public $type;

	public $definition;


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
            
            if (!$this->type) {
                $this->type = $name;
            }

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

	public function getPossibleTypes()
    {
        return [
            ''
        ];
    }

	public function getPhpType()
    {
        $types = ['string', 'int', 'boolean', 'float', 'array', 'object', 'null'];

        return 'string';
    }

}
