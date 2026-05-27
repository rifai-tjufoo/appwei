<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_customer_group',
            'customer_group_id',
            'customer_id',
        );
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
