<?php

namespace API;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Database
{
    public $definition;

    public function __construct($definition)
    {
        $this->definition = $definition;
    }

    public function do()
	{
		// Schema::dropIfExists('users');

		// Schema::create('users', function (Blueprint $table) {
  //           $table->id();
  //           $table->string('name');
  //           $table->string('email')->unique();
  //           $table->timestamp('email_verified_at')->nullable();
  //           $table->string('password');
  //           $table->rememberToken();
  //           $table->timestamps();
  //       });

        $data = $this->definition;

        dd($data['api']);
		foreach ($data['api'] as $table) {
		    $tableName = $this->getTableName($table['db'] ?? $table['name']);

            Schema::disableForeignKeyConstraints();
            Schema::dropIfExists($tableName);
            Schema::enableForeignKeyConstraints();

		    //continue;
		    $exists = Schema::hasTable($tableName);
		    $fields = $table['fields'] ?? [];

		    // check if definition has changed... how?
            //dump($fields);
            if (!$exists) {
                //dump($tableName);
                //continue;
                Schema::create($tableName, function(Blueprint $t) use($table, $fields) {
                    // types:
                    foreach ($fields as $key => $field) {
                        $parameters = $this->parseColumnDefinition($field, $t, $key);
                        //dump($field, $fields);
                    }

                    if ($table['timestamps'] ?? false) {
                        $t->timestamps();
                    }

                    if ($table['soft_deletes'] ?? false) {
                        $t->softDeletes();
                    }

                    $relations = $table['relation'] ?? [];
                    foreach ($relations as $relationName => $relation) {
                        $parts = explode('|', $relation);
                        $column = null;
                        foreach ($parts as $part) {
                            $parameters = explode(':', $part);
                            $method = array_shift($parameters);

                            $validRelationTypes = ['belongsTo'];
                            if (!in_array($method, $validRelationTypes, true) ) {
                                continue;
                            }

                            $column = $column ?: $t;
                            //if( !method_exists($column, $method) ) {
                            //    dump('method does not exist: ' . $method);
                            //    continue;
                            //}

                            if( $parameters ) {
                                $parameters = explode(',', $parameters[0]);
                            }

                            if ($method === 'belongsTo') {
                                $fieldName = $parameters[1] ?? $relationName . '_id';
                                $relationTableName = $this->getTableName($parameters[0]);
                                $column->foreignId($fieldName)->nullable()->constrained($relationTableName);
                            }
                        }
                    }
                });
            }
		}

		dd('he', $data['api'], \App\User::count());
	}

	public function getTableName(string $tableName)
    {
        $prefix = $this->definition['db_prefix'] ?? '';

        return $prefix . $tableName;
    }

    /**
     * @param $field
     * @param Blueprint $t
     * @param $key
     *
     * @return false|string[]
     */
    public function parseColumnDefinition($field, Blueprint $t, $key)
    {
        $parts = explode('|', $field);
        $column = null;
        foreach ($parts as $part) {
            $parameters = explode(':', $part);
            $method = array_shift($parameters);

            //dump($key . '-'. $field, $parameters);
            $column = $column ?: $t;
            if( !method_exists($column, $method) ) {
                dump('method does not exist: ' . $method);
                continue;
            }

            if( $parameters ) {
                $parameters = explode(',', $parameters[0]);
            }

            $column->$method($key, ...$parameters);
        }

        return $parameters;
    }
}
