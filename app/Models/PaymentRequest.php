<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sent_at'    => 'datetime',
        'viewed_at'  => 'datetime',
        'paid_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function settlement()
    {
        return $this->hasOne(SettlementRecord::class);
    }
}
