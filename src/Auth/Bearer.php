<?php

namespace ApiX\Auth;

use ApiX\API;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Bearer
{
    private $db_name;
    private $key;
    
    public function __construct($db_name = 'users', $key = 'api_key')
    {
        $this->db_name = $db_name;
        $this->key = $key;
    }
    
    public function handle(Request $request)
    {
        /** @var API $api */
        $api = app()->get(API::class);
        $endpoint = $api->getEndpoint($this->db_name);
        abort_unless($endpoint, 501, sprintf('Endpoint (%s) not found', $this->db_name));
        
        $token = $this->bearerToken($request);
        if (!$token) {
            return false;
        }
        
        $user = $api->find($endpoint, $token, $this->key, 'auth');
        if (!$user) {
            return false;
        }
        //abort_unless($user, 403, 'Unauthorized');
        
        $api->setUser($user);
        
        return true;
    }
    
    public function bearerToken(Request $request)
    {
        $header = $request->header('Authorization', '');
        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
        
        return null;
    }
}
