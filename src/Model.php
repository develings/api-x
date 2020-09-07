<?php

namespace API;

use API\Definition\Endpoint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Model
{
    public function createModel(Endpoint $endpoint, bool $force = false)
    {
        $definition = $endpoint->definition;
        $api = API::getInstance();

        $path = base_path('app/' . ucfirst(Str::camel($endpoint->name)) . '.php');

        if (!$force && File::exists($path)) {
            //return false;
        }

        $stub = File::get(__DIR__ . '/stubs/Model.stub');

        $fields = '';
        foreach ($endpoint->fields as $key => $field) {
            $tab = '    ';
            $def = $tab .'/**' . PHP_EOL;
            $def .= $tab . ' * @var ' . $field->getPhpType() . PHP_EOL;
            $def .= $tab . ' *' . PHP_EOL;
            $def .= $tab . ' * Definition: ' . $field->definition . PHP_EOL;
            $def .= $tab . ' */' . PHP_EOL;
            $def .= $tab . 'public $' . $key . ';' . PHP_EOL . PHP_EOL;

            $fields .= $def;
        }

        $methods = [];
        if ($endpoint->relations) {
            $relationTemplate = "
    public function relationName()
    {
        return \$this->methodName(relationTable foreignKey ownerKey);
    }";
            foreach ($endpoint->relations as $name => $relation) {
                $rule = $relation->getRelationRule();

                $relationEndpoint = $api->getEndpoint($rule->target);

                $relationData = [
                    'relationName' => $name,
                    'methodName' => $relation->relationType,
                    'relationTable' => $rule->target ?  ucfirst(Str::camel($relationEndpoint->name)) . '::class' : '',
                    ' foreignKey' => $rule->foreign_key ? ', "' . $rule->foreign_key . '"' : '',
                    ' ownerKey' => $rule->owner_key ? ', "' . $rule->owner_key . '"' : '',
                ];

                $methods[] = str_replace(
                    array_keys($relationData),
                    array_values($relationData),
                    $relationTemplate
                );
            }
        }

        $stub = str_replace(
            [
                'DummyClass',
                "dummyNamespace;\n",
                'dummyTableName',
                "dummyMethods"
            ],
            [
                ucfirst(Str::camel($definition['name'])),
                "namespace App;\n\n",
                $endpoint->getTableName(),
                implode("\n\n", $methods)
            ],
            $stub
        );

        $class = str_replace('dummyProperties', $fields, $stub);

        //dd($class);

        File::put(
            $path,
            $class
        );

        return true;
    }
}
