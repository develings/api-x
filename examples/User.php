<?php

namespace Examples;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'user';
    
    protected $guarded = [];
    
}
