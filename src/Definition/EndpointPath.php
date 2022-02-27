<?php

namespace ApiX\Definition;

class EndpointPath
{
    public $where;

    public $authentication;

    public $fill;

    public $after;
    
    public $before;

    public $hydrate;

    public function __construct(array $values = [])
    {
        foreach ($values as $k => $value) {
            if (property_exists($this, $k)) {
                $this->$k = $value;
            }
        }
    }
    
    public function triggerBefore()
    {
        return $this->trigger($this->before, func_get_args());
    }

    public function triggerAfter()
    {
        return $this->trigger($this->after, func_get_args());
    }

    public function triggerHydrate(&$array)
    {
        return $this->trigger($this->hydrate, func_get_args());
    }

    public function trigger($expression, $args)
    {
        if (!$expression) {
            return null;
        }

        if ( !str_contains($expression, '@') ) {
            abort(501, sprintf('Method is missing in %s', $expression));
        }

        [$class, $method] = explode('@', $expression);

        abort_unless(class_exists($class), 501, sprintf('Class not found (%s)', $class));

        $instance = app()->make($class);
        abort_unless(method_exists($instance, $method), 501, sprintf('Method not found (%s@%s)', $class, $method));

        return $instance->$method(...$args);
    }
}
