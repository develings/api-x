<?php

namespace ApiX\Auth;

use ApiX\ApiX;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Token
{
    private $db_name;
    private $key;
    /**
     * @var mixed|string
     */
    private $request_key;
    
    public function __construct($db_name = 'user', $key = 'api_key', $requestKey = 'api_key')
    {
        $this->db_name = $db_name;
        $this->key = $key;
        $this->request_key = $key;
    }
    
    public function handle(Request $request)
    {
        //return true;
        /** @var ApiX $api */
        $api = app()->get(ApiX::class);
        $endpoint = $api->getEndpoint($this->db_name);
        
        abort_unless($endpoint, 501, sprintf('Endpoint (%s) not found', $this->db_name));
        
        $token = $request->get($this->request_key);
        if (!$token) {
            return false;
        }
        
        $user = $api->find($endpoint, $token, $this->key, 'auth');
        //dd($user);
        //abort_unless($user, 403, 'Unauthorized');
        if (!$user) {
            return false;
        }
        
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
    
    public function getUser(Request $request)
	{
		$token = $request->get('token');
		abort_unless($token, 403);

		$user = DB::table('users')
			->where('api_token', $token)
			->firstOrFail();

		abort_unless($user, 403);

		return $user;
	}
}
