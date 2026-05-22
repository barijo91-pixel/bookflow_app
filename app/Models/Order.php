<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'shipped_at'   => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at'  => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }
}
