<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Filter extends Model
{
    protected $fillable = [
        'keyword',
        'list_type',
        'logic_mode',
    ];
}
