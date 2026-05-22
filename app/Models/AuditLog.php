<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    public static function log(string $entity, $entityId, string $action, ?array $before = null, ?array $after = null): void
    {
        static::create([
            'user_id'    => auth()->id(),
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'action'     => $action,
            'before'     => $before,
            'after'      => $after,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
