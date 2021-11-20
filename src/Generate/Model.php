<?php

namespace ApiX\Generate;

use ApiX\Definition\Endpoint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ApiX\Facade\ApiX;

class Model
{
    public function create(Endpoint $endpoint, bool $force = false)
    {
        $definition = $endpoint->definition;
        $api = ApiX::getInstance();

        $path = base_path('app/Models/' . ucfirst(Str::camel($endpoint->name)) . '.php');

        if (!$force && File::exists($path)) {
            return false;
        }

        $stub = File::get(__DIR__ . '/../../resources/stubs/Model.stub');

        $dynamicFields = '';
        $classProperties = '';
        foreach ($endpoint->fields as $key => $field) {
            $tab = '    ';
            //$def = $tab .'/**' . PHP_EOL;
            //$def .= $tab . ' * @var ' . $field->getPhpType() . PHP_EOL;
            //$def .= $tab . ' *' . PHP_EOL;
            //$def .= $tab . ' * Definition: ' . $field->definition . PHP_EOL;
            //$def .= $tab . ' */' . PHP_EOL;
            //$def .= $tab . '//public $' . $key . ';' . PHP_EOL . PHP_EOL;
    
            $def = ' * @property ' . $field->getPhpType() . ' ' . $key . PHP_EOL;

            $dynamicFields .= $def;
        }
        
        if ($endpoint->timestamps) {
            $dynamicFields .= ' * @property string created_at' . PHP_EOL;
            $dynamicFields .= ' * @property string updated_at' . PHP_EOL;
        } else {
            $classProperties .= '    public $timestamps = false;' . PHP_EOL;
        }
        
        if ($endpoint->soft_deletes) {
            $dynamicFields .= ' * @property string deleted_at' . PHP_EOL;
        }

        $methods = [];
        if ($endpoint->relations) {
            $relationTemplate = "    public function relationName()
    {
        return \$this->methodName(relationTable foreignKey ownerKey);
    }";
            //dd($endpoint->relations);
            foreach ($endpoint->relations as $name => $relation) {
                $rule = $relation->getRelationRule();

                if (!$rule) {
                    abort(sprintf('Relation (%s) does not exist', $relation->relationType));
                }
                //dd($rule);
                $relationEndpoint = $api->getEndpoint($rule->target);
                $foreignKey = $rule->foreign_key;
                $ownerKey = $rule->owner_key;
                
                if ($relation->relationType === 'hasOneThrough' || $relation->relationType === 'hasManyThrough') {
                    $foreignEndpoint = $api->getEndpoint($foreignKey);
                    $foreignKey = ucfirst(Str::camel($foreignEndpoint->name)) . '::class';
                    $ownerKey = null;
                } else {
                    $foreignKey = '"' . $foreignKey . '"';
                }
                
                if ($relation->relationType === 'hasMany') {
                    $foreignKey = null;
                    $ownerKey = null;
                }

                $relationData = [
                    'relationName' => Str::camel($name),
                    'methodName' => $relation->relationType,
                    'relationTable' => $rule->target ?  ucfirst(Str::camel($relationEndpoint->name)) . '::class' : '',
                    ' foreignKey' => $foreignKey ? ', ' . $foreignKey : '',
                    ' ownerKey' => $ownerKey ? ', "' . $ownerKey . '"' : '',
                ];
                
                if ($foreignKey) {
                    $def = ' * @property string ' . $rule->foreign_key . PHP_EOL;
    
                    $dynamicFields .= $def;
                }

                $methods[] = str_replace(
                    array_keys($relationData),
                    array_values($relationData),
                    $relationTemplate
                );
            }
        }
        
        if (count($methods) > 2) {
            //dd($methods);
        }

        $stub = str_replace(
            [
                'DummyClass',
                "dummyNamespace;\n",
                'dummyTableName',
                "dummyMethods",
                'dummyProperties'
            ],
            [
                ucfirst(Str::camel($definition['name'])),
                "namespace App\\Models;\n\n",
                $endpoint->getTableName(),
                implode("\n\n", $methods),
                $classProperties,
            ],
            $stub
        );

        //$class = str_replace('dummyProperties', '', $stub);
        $class = str_replace('dummyDynamicProperties', $dynamicFields, $stub);

        //dd($class);

        File::put(
            $path,
            $class
        );

        return true;
    }
}
