<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'input_tokens'      => 'integer',
        'output_tokens'     => 'integer',
        'cache_read_tokens' => 'integer',
        'est_cost_krw'      => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
