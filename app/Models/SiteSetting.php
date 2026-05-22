<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $guarded = ['id'];

    public const CACHE_KEY = 'site_settings.flat';
    public const CACHE_TTL = 86400; // 24h

    /**
     * 평면 배열로 캐시 (Eloquent 컬렉션 캐싱은 역직렬화 이슈 가능 → 배열 사용)
     */
    public static function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::query()
                ->orderBy('group')
                ->orderBy('sort_order')
                ->get(['key', 'value', 'group', 'type', 'label'])
                ->mapWithKeys(fn ($r) => [$r->key => (string) ($r->value ?? '')])
                ->toArray();
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
