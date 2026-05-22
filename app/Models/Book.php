<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'pub_date'      => 'date',
        'source_payload'=> 'array',
        'price'         => 'integer',
        'default_discount_rate' => 'decimal:2',
    ];

    public function publisher()
    {
        return $this->belongsTo(Publisher::class);
    }
}
