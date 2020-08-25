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
        
        $data = [
            'openapi' => '3.0.2',
            'info' => [
                'title' => config('app.name'),
                'version' => config('app.version', '1.0.0')
            ],
            'servers' => $servers,
            'paths' => [],
            'components' => [
                'schemas' => []
            ],
            'tags' => []
        ];

        abort_unless($this->base->api, 500, 'Invalid API json file');

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
            $propertiesCreate = [];
            foreach ($api->fields as $k => $v) {
                $properties[$k] = [
                    'type' => 'string',
                ];

                $propertiesCreate[] = [
                    'in' => 'body',
                    'name' => $k,
                    'required' => true,
                    'schema' => [
                        'type' => 'integer'
                    ]
                ];
            }

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

            $data['paths']['/' . $key] = $definition;

            $definition = [];
            //$definition['get'] = [
            //    'responses' => []
            //];
            //$data['paths']['/' . $key . '/search'] = $definition;

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

            //$definition['post'] = [
            //    'responses' => []
            //];

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

}
