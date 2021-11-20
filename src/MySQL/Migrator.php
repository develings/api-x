<?php

namespace ApiX\MySQL;

use ApiX\API;
use ApiX\Definition\Endpoint;
use ApiX\Definition\Field;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

class Migrator
{
    use \Illuminate\Console\Concerns\InteractsWithIO;
    public $api;
    
    public function __construct(API $api)
    {
        $this->api = $api;
    }
    
    public function migrate(array $tables, $force = false)
    {
        $data = $this->api->base;
        
        $tablesWithForeignKeys = [];
        $definitions = [];
        
        foreach ($data->api as $table) {
            if ($tables && !in_array($table->name, $tables, true) ) {
                // Skip table because it is not selected to be migrated
                $this->line(sprintf('<info>Skipping</info> %s', $table->name));
                continue;
            }
            
            $tableName = $this->api->base->getTableName($table);
            
            if ($force) {
                $this->line(sprintf('Deleting <info>%s</info>...', $tableName));
                Schema::disableForeignKeyConstraints();
                Schema::dropIfExists($tableName);
                Schema::enableForeignKeyConstraints();
            }
            
            //continue;
            $exists = Schema::hasTable($tableName);
            //if ($exists) {
            //    $this->line(sprintf('<error> FAIL </error> Table (<info>%s</info>) already exists.', $table->name));
            //    continue;
            //}
            
            $this->line(sprintf('Migrating <info>%s</info>...', $tableName));
            
            //continue;
            
            // For testing purposes only
            //$blueprint = new Blueprint($tableName);
            //$blueprint->create();
            //$blueprint = $this->create($table, $blueprint);
            //dd($blueprint);
            
            $method = $exists ? 'table' : 'create';
    
            $definitions[$tableName] = Schema::getColumnListing($tableName);
    
            Schema::$method($tableName, function(Blueprint $blueprint) use($table, &$tablesWithForeignKeys, $definitions, $tableName) {
                $this->create($table, $blueprint, $definitions[$tableName]);
                $tablesWithForeignKeys[$tableName] = $table;
            });
            //
            $this->line(sprintf('Migrated <info>%s</info>.', $tableName));
            //break;
            //dd($blueprint);
        }
        
        foreach ($tablesWithForeignKeys as $tableName => $table) {
            Schema::table($tableName, function (Blueprint $blueprint) use($table, $definitions, $tableName) {
                $this->line(sprintf('Migrating foreign keys <info>%s</info>...', $tableName));
                $this->makeRelations($table, $blueprint, $definitions[$tableName]);
                $this->line(sprintf('Migrated foreign keys <info>%s</info>.', $tableName));
            });
        }
        
        return true;
    }
    
    public function getTableName(string $tableName)
    {
        $prefix = $this->definition['db_prefix'] ?? '';
        
        return $prefix . $tableName;
    }
    
    /**
     * @param $field
     * @param Blueprint $blueprint
     * @param $key
     *
     * @return Blueprint
     */
    public function parseColumnDefinition(Field $field, Blueprint $blueprint, $key)
    {
        $definitions = explode('|', $field->definition);
        $definitionType = array_shift($definitions);
        
        [$name, $parameters] = $this->parseDefinition($definitionType);
    
        $column = $this->createColumn($blueprint, $field->key, $name, $parameters);
        
        foreach ($definitions as $definition) {
            [$name, $parameters] = $this->parseDefinition($definition);
            $this->setOption($column, $name, $parameters);
        }
    }
    
    /**
     * @param ColumnDefinition $column
     * @param string $name
     * @param array $parameters
     */
    public function setOption($column, $name, $parameters)
    {
        $firstParameter = $parameters[0] ?? null;
        $ignore = ['alphanumeric', 'uuid', 'hashid'];
        if ($name === 'default' && in_array($firstParameter, $ignore)) {
            return;
        }
        
        $column->$name(...$parameters);
    }
    
    public function parseDefinition($definition): array
    {
        $options = explode(':', $definition);
        $name = array_shift($options);
        $parameters = $options[0] ?? null;
    
        $parameters = $parameters ? explode(',', $parameters) : [];
        
        return [$name, $parameters];
    }
    
    /**
     * @param Blueprint $blueprint
     * @param $key
     * @param $method
     * @param null $parameters
     *
     * @return bool
     */
    public function createColumn($blueprint, $key, $method, $parameters = null)
    {
        if ($method === 'password') {
            return $blueprint->string($key, ...$parameters);
        }
    
        if ($method === 'email') {
            return $blueprint->string($key, ...$parameters);
        }
    
        return $blueprint->$method($key, ...$parameters);
    }
    
    /**
     * @param \ApiX\Definition\Endpoint $table
     * @param Blueprint $blueprint
     *
     * @return Blueprint
     */
    private function create(\ApiX\Definition\Endpoint $table, Blueprint $blueprint, $definitions)
    {
        $fields = $table->fields;
        foreach ($fields as $key => $field) {
            if (in_array($field->key, $definitions, true)) {
                continue;
            }
            
            $this->parseColumnDefinition($field, $blueprint, $key);
        }
    
        if( ($table->timestamps ?? false) && !in_array('created_at', $definitions, true) ) {
            $blueprint->timestamps();
        }
    
        if( ($table->soft_deletes ?? false) && !in_array('deleted_at', $definitions, true) ) {
            $blueprint->softDeletes();
        }
        
        // hmm? what does this do?
        //if ($table->unique) {
        //    // Convert unique to an array of associative array
        //    $uniques = !isset($table->unique[0]) ? $table->unique : [$table->unique];
        //    collect($uniques)->map(function($keys, $id) use($blueprint) {
        //        $blueprint->unique($keys, !$id || is_numeric($id) ? null : $id);
        //    });
        //}
        
        return $blueprint;
    }
    
    public function makeRelations(Endpoint $table, Blueprint $blueprint, $definitions)
    {
        $relations = $table->relations ?? [];
        $isSqlite = config('database.default') === 'sqlite';
        
        //$relations = [];
        foreach ($relations as $relationName => $relation) {
            if (in_array($relationName, $definitions, true)) {
                continue;
            }
            
            $parts = explode('|', $relation->definition);
            $column = null;
        
            foreach ($parts as $part) {
                $parameters = explode(':', $part);
                $method = array_shift($parameters);
            
                $validRelationTypes = ['belongsTo'];
                if( !in_array($method, $validRelationTypes, true) ) {
                    continue;
                }
            
                $column = $column ?: $blueprint;
                //if( !method_exists($column, $method) ) {
                //    dump('method does not exist: ' . $method);
                //    continue;
                //}
            
                if( $parameters ) {
                    $parameters = explode(',', $parameters[0]);
                }
            
                //if( $method === 'belongsTo' || $method === 'hasOne' ) {
                $fieldName = $parameters[1] ?? $relationName . '_id';
    
                if (in_array($fieldName, $definitions, true)) {
                    continue;
                }
                
                $relationEndpoint = $this->api->getEndpoint($parameters[0]);
                if( !$relationEndpoint ) {
                    $this->error(sprintf('Relation table %s does not exist', $parameters[0]));
                    continue;
                }
            
                $relationTableName = $this->api->base->getTableName($relationEndpoint);
                $field = $column->foreignId($fieldName);
                if ($relation->isNullable()) {
                    $field = $field->nullable();
                } else if($isSqlite) {
                    // SQLite: if not nullable, it will be set to default value (https://stackoverflow.com/questions/20822159/laravel-migration-with-sqlite-cannot-add-a-not-null-column-with-default-value-n)
                    $field->default('');
                }
                if ($relation->isUnique()) {
                    $field = $field->unique();
                }
                
                $field->constrained($relationTableName);
                //}
            
                //if( $method === 'hasOne' ) {
                //    $fieldName = $parameters[1] ?? $relationName . '_id';
                //    $relationEndpoint = $this->api->getEndpoint($parameters[0]);
                //    if( !$relationEndpoint ) {
                //        $this->error(sprintf('Relation table %s does not exist', $parameters[0]));
                //        continue;
                //    }
                //    $relationTableName = $this->api->base->getTableName($relationEndpoint);
                //    $column->foreignId($fieldName)->nullable()->constrained($relationTableName);
                //}
            }
        }
    }
}
