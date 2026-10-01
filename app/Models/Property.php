<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['location_approximate' => 'boolean'];
    }

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }
}
