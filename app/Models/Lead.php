<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['acquisition_active' => 'boolean', 'owner_linked' => 'boolean', 'follow_up_at' => 'datetime', 'transferred_at' => 'datetime'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
