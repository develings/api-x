<?php

namespace API\Auth;

class Token
{
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