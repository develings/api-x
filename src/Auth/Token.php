<?php

namespace API\Auth;

use Illuminate\Http\Request;

class Token
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
        dd($this);
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