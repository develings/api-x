<?php

namespace ApiX\Definition;

use ApiX\ApiX;

trait RuleTrait
{
    public $rules = [];

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
    public function getDefault()
    {
        return $this->rules['default'] ?? null;
    }

    public function isHidden(): bool
    {
        return (bool)($this->rules['hidden'] ?? false);
    }

    public function has(string $name): bool
    {
        return (bool)($this->rules[$name] ?? false);
    }

    public function isNullable(): bool
    {
        return $this->has('nullable');
    }

    public function isUnique(): bool
    {
        return $this->has('unique');
    }

    public function getValidationRules(Endpoint $endpoint, array $validators = null)
    {
        $rules = [];
        foreach ($this->rules as $name => $rule) {
            if (!in_array($name, $validators, true)) {
                continue;
            }

            if ($name === 'unique') {
                $api = \ApiX\ApiX::getInstance();
                $rules[] = 'unique:' . $api->base->getTableName($endpoint) . ',creator_id';
                continue;
            }

            $rules[] = $name;
        }

        return $rules;
    }

    public function cast($value, $data)
    {
        $cast = $this->rules['cast'] ?? null;
        if ($value && $cast) {
            $type = $cast->parameters[0] ?? null;
            if ($type === 'timestamp') {
                $value = strtotime($value);
            } else if(strpos($type, '@') !== false) {
                $parts = explode('@', $type);

                if (count($parts) === 2) {
                    abort_unless(class_exists($parts[0]), 501, 'Class does not exist');
                    $class = new $parts[0]();
                    abort_unless(method_exists($class, $parts[1]), 500, sprintf('Method does not exist (%s)', $type));
                    $method = $parts[1];
                    $value = $class->$method($value, $data);
                }
            }
        }

        return $value;
    }
}
