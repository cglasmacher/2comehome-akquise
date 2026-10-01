<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'postal_patterns' => 'array', 'sources' => 'array', 'property_types' => 'array', 'markets' => 'array'];
    }
}
