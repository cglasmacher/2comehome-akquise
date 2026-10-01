<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['contact_blocked' => 'boolean'];
    }
}
