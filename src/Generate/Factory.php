<?php

namespace ApiX\Generate;

use ApiX\Definition\Endpoint;
use ApiX\Facade\ApiX;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Factory
{
    public function create(Endpoint $endpoint, bool $force = false)
    {
        $definition = $endpoint->definition;
        $api = ApiX::getInstance();
        
        $path = base_path('database/factories/' . ucfirst(Str::camel($endpoint->name)) . 'Factory.php');
        
        if (!$force && File::exists($path)) {
            return false;
        }
        
        $stub = File::get(__DIR__ . '/../../resources/stubs/Factory.stub');
        
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
            
            $def = "			'$key' => \$this->faker->{$field->getPhpType()}," . PHP_EOL;
            
            $dynamicFields .= $def;
        }
    
        $className = ucfirst(Str::camel($definition['name']));

        $stub = str_replace(
            [
                'dummyUse',
                'DummyClass',
                "dummyNamespace;\n",
                'dummyTableName',
                'dummyModel',
                'dummyFields'
            ],
            [
                'use App\Models\\'.$className.'',
                $className . 'Factory',
                "namespace Database\Factories;\n\n",
                $endpoint->getTableName() . 'Factory',
                $className . '::class',
                $dynamicFields,
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
