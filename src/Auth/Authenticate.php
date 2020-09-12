<?php

namespace API\Auth;

use Illuminate\Http\Request;

class Authenticate
{
    public function handle(Request $request, \Closure $next)
    {
        // Validate request and check for an app key (eg: ?app_id=kajhdsfkahsdkfahdg)
        return $next($request);
    }
}