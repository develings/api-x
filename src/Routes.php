<?php

namespace ApiX;

use Illuminate\Http\Request;

class Routes
{
    /**
     * @var ApiX
     */
    public $api;
    
    /**
     * Routes constructor.
     *
     * @param ApiX $api
     */
    public function __construct(ApiX $api)
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
    
    public function post($api, Request $request)
    {
        return $this->api->post($api, $request);
    }
    
    public function get($api, $id, Request $request)
    {
        return $this->api->get($api, $id, $request);
    }
    
    public function put($api, $id, Request $request)
    {
        return $this->api->put($api, $id, $request);
    }
    
    public function delete($api, $id, Request $request)
    {
        return $this->api->delete($api, $id, $request);
    }
    
    public function getSwagger()
    {
        return view('apix::swagger', [
            'url' => route('api.openapi'),
        ]);
    }
    
}
