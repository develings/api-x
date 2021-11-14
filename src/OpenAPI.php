<?php

namespace API;

use API\Definition\Base;
use Illuminate\Support\Facades\File;

class OpenAPI
{
    /**
     * @var Base
     */
    public $base;
    
    /**
     * OpenAPI constructor.
     *
     * @param $base
     */
    public function __construct(Base $base)
    {
        $this->base = $base;
    }
    
    public function definition()
    {
        $servers = [
            [
                'url' => config('app.url') . ($this->base->endpoint ?? ''),
            ]
        ];
        
        if ($this->base->servers) {
            $servers = $this->base->servers;
        }
        
        foreach ($servers as $i => $server) {
            $servers[$i]['url'] .= $this->base->endpoint;
        }
        
        $laravelTypes = $this->getLaravelTypes();
        
        $data = [
            'openapi' => '3.0.2',
            'info' => [
                'title' => config('app.name'),
                'version' => config('app.version', '1.0.0')
            ],
            'servers' => $servers,
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => []
            ],
            'tags' => []
        ];

        abort_unless($this->base->api, 501, 'Invalid API json file');
        
        $data['components']['securitySchemes']['api_key'] = [
            'type' => 'apiKey',
            'in' => 'query',
            'name' => 'api_key'
        ];
    
        $data['components']['securitySchemes']['bearer'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT'
        ];
        
        $data['security'] = [
            ['api_key' => []]
        ];

        foreach ($this->base->api as $key => $api) {
            $tags = [
                $key
            ];

            $getParameters = [
                [
                    'in' => 'query',
                    'name' => 'search',
                    'required' => false,
                    'schema' => [
                        'type' => 'string'
                    ]
                ],
                [
                    'in' => 'query',
                    'name' => 'page',
                    'required' => false,
                    'schema' => [
                        'type' => 'integer',
                        'default' => 1,
                    ]
                ],
                [
                    'in' => 'query',
                    'name' => 'per_page',
                    'required' => false,
                    'schema' => [
                        'type' => 'integer',
                        'default' => 25,
                    ]
                ],
            ];

            $relationKeys = $api->getRelationKeys();
            if ($relationKeys) {
                $relationKeys = $relationKeys ? implode(', ', $relationKeys) : '';

                $getParameters[] = [
                    'in' => 'query',
                    'name' => 'with',
                    'required' => false,
                    'description' => 'Provide a list of relations for the entity, separated by comma. Possible values: ' . $relationKeys,
                    'example' => 'info,products:40',
                    'schema' => [
                        'type' => 'string',
                    ]
                ];
            }

            $definition = [];

            if ($api->index !== false) {
                $definition['get'] = [
                    'tags' => $tags,
                    'parameters' => $getParameters,
                    'responses' => [
                        200 => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/' . $key . '_paginated',
                                        //'type' => 'object',
                                        //'properties' => array_flip(array_keys($api['fields'] ?? []))
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
            }

            $e200 = [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $key,
                            //'type' => 'object',
                            //'properties' => array_flip(array_keys($api['fields'] ?? []))
                        ]
                    ]
                ]
            ];

            $properties = [];
            foreach ($api->fields as $k => $v) {
                $type = $laravelTypes[$v->type] ?? 'string';
                $properties[$k] = is_string($type) ? [
                    'type' => $type,
                ] : $type;
            }
            

            if (isset($api->relations)) {
                foreach ($api->relations as $k => $v) {
                    $properties[$k.'_id'] = [
                        'type' => 'integer',
                    ];
                }
            }
    
            if (isset($api->timestamps)) {
                $properties['created_at'] = [
                    'type' => 'string',
                    'format' => 'date-time'
                ];
                $properties['updated_at'] = [
                    'type' => 'string',
                    'format' => 'date-time'
                ];
            }
    
            if (isset($api->soft_deletes)) {
                $properties['deleted_at'] = [
                    'type' => 'string',
                    'format' => 'date-time'
                ];
            }

            if ($api->create !== false) {
                $definition['post'] = [
                    'tags' => $tags,
                    'responses' => [
                        200 => $e200
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $key
                                ]
                            ]
                        ]
                    ]
                ];
            }

            $data['paths']['/' . $key] = $definition;

            $definition = [];
            //$definition['get'] = [
            //    'responses' => []
            //];
            //$data['paths']['/' . $key . '/search'] = $definition;

            if ($api->get !== false) {
                $definition['get'] = [
                    'tags' => $tags,
                    'responses' => [
                        200 => $e200
                    ],
                    'parameters' => [
                        [
                            'in' => 'path',
                            'name' => 'id',
                            'required' => true,
                            'schema' => [
                                'type' => 'integer'
                            ]
                        ]
                    ],
                ];
            }

            if ($api->update !== false) {
                $definition['put'] = [
                    'tags' => $tags,
                    'responses' => [
                        200 => $e200
                    ],
                    'parameters' => [
                        [
                            'in' => 'path',
                            'name' => 'id',
                            'required' => true,
                            'schema' => [
                                'type' => 'integer'
                            ]
                        ]
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $key
                                ]
                            ]
                        ]
                    ]
                ];
            }

            if ($api->delete !== false) {
                $definition['delete'] = [
                    'tags' => $tags,
                    'responses' => [
                        200 => $e200
                    ],
                    'parameters' => [
                        [
                            'in' => 'path',
                            'name' => 'id',
                            'required' => true,
                            'schema' => [
                                'type' => 'integer'
                            ]
                        ]
                    ],
                ];
            }

            $data['paths']['/' . $key . '/{id}'] = $definition;

            $data['components']['schemas'][$key] = [
                'type' => 'object',
                'properties' => $properties
            ];

            $data['components']['schemas'][$key . '_paginated'] = [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'first_page_url' => ['type' => 'string'],
                    'from' => ['type' => 'integer', 'example' => 1],
                    'last_page' => ['type' => 'integer', 'example' => 1],
                    'last_page_url' => ['type' => 'string'],
                    'next_page_url' => ['type' => 'string'],
                    'path' => ['type' => 'string'],
                    'per_page' => ['type' => 'integer', 'example' => 1],
                    'prev_page_url' => ['type' => 'string'],
                    'to' => ['type' => 'integer', 'example' => 1],
                    'total' => ['type' => 'integer', 'example' => 1],
                    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/user']],
                ]
            ];


            $data['tags'][] = [
                'name' => $key
            ];
        }

        return $data;
    }
    
    public function save(string $path)
    {
        return File::put($path, json_encode($this->definition(), JSON_PRETTY_PRINT));
    }
    
    public function getLaravelTypes(): array
    {
        return [
            'bigIncrements' => 'integer',
            'bigInteger' => 'integer',
            'binary' => 'binary',
            'boolean' => 'boolean',
            'char' => 'char',
            'dateTimeTz' => 'datetime',
            'dateTime' => 'datetime',
            'date' => 'date',
            'decimal' => 'decimal',
            'double' => 'double',
            'enum' => 'enum',
            'float' => 'float',
            'foreignId' => 'string',
            'foreignIdFor' => 'string',
            'foreignUuid' => 'string',
            'geometryCollection' => 'string',
            'geometry' => 'string',
            'id' => 'integer',
            'increments' => 'integer',
            'integer' => 'integer',
            'ipAddress' => 'string',
            'json' => 'json',
            'jsonb' => 'jsonb',
            'lineString' => 'string',
            'longText' => 'string',
            'macAddress' => 'string',
            'mediumIncrements' => 'integer',
            'mediumInteger' => 'integer',
            'mediumText' => 'string',
            'morphs' => 'string',
            'multiLineString' => 'string',
            'multiPoint' => 'string',
            'multiPolygon' => 'string',
            'nullableMorphs' => 'string',
            'nullableTimestamps' => 'string',
            'nullableUuidMorphs' => 'string',
            'point' => 'string',
            'polygon' => 'string',
            'rememberToken' => 'string',
            'set' => 'string',
            'smallIncrements' => 'string',
            'smallInteger' => 'string',
            'softDeletesTz' => 'string',
            'softDeletes' => 'string',
            'string' => 'string',
            'text' => 'string',
            'timeTz' => 'time',
            'time' => 'time',
            'timestampTz' => 'string',
            'timestamp' => 'string',
            'timestampsTz' => 'string',
            'timestamps' => 'string',
            'tinyIncrements' => 'integer',
            'tinyInteger' => 'integer',
            'tinyText' => 'string',
            'unsignedBigInteger' => 'string',
            'unsignedDecimal' => 'string',
            'unsignedInteger' => 'string',
            'unsignedMediumInteger' => 'string',
            'unsignedSmallInteger' => 'string',
            'unsignedTinyInteger' => 'string',
            'uuidMorphs' => 'string',
            'uuid' => 'uuid',
            'year' => 'string',
        ];
    }

}
