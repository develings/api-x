<?php

namespace API;

use Illuminate\Http\Request;

class Routes
{
    /**
     * @var API
     */
    public $api;
    
    /**
     * Routes constructor.
     *
     * @param API $api
     */
    public function __construct(API $api)
    {
        $this->api = $api;
    }
    
    public function getOpenApiJson()
    {
        return response($this->api->openApiJson());
    }
    
    public function index($api, Request $request)
    {
        return $this->api->index($api, $request);
    }
    
    public function migrate(Request $request)
    {
        return $this->api->migrate();
    }
    
}