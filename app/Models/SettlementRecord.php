<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementRecord extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'computed_at'  => 'datetime',
        'paid_out_at'  => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function distributor()
    {
        return $this->belongsTo(User::class, 'distributor_user_id');
    }

    public function paymentRequest()
    {
        return $this->belongsTo(\App\Models\PaymentRequest::class);
    }

    public function getBreakdownAttribute()
    {
        return $this->breakdown_json ? json_decode($this->breakdown_json, true) : [];
    }
}
