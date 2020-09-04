<?php

namespace API\Auth;

use Illuminate\Http\Request;

class Guest
{
    public function handle(Request $request)
    {
        return true;
    }
}