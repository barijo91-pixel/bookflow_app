<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 총판 산하 범위 정의 — "이 총판이 볼 수 있는 것"의 유일한 기준.
 *
 * 총판이 여러 개인 구조(지역별 총판)에서 관리자 화면 필터와 총판 본인 화면이
 * 서로 다른 기준을 쓰면 "관리자엔 보이는데 총판엔 안 보임" 같은 어긋남이 생겨서
 * 한 곳에 모아둔다.
 *
 *   총판 → user_relations(distributor_agent, active) → 영업자
 *        → agent_vendor_discounts(is_active) → 학원(vendor)
 *        → vendor_users → 학원 계정
 */
class DistributorScopeService
{
    /** 산하 영업자 user id */
    public static function agentIds(int $distributorId): array
    {
        return DB::table('user_relations')
            ->where('parent_user_id', $distributorId)
            ->where('relation_type', 'distributor_agent')
            ->where('status', 'active')
            ->pluck('child_user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()->values()->all();
    }

    /** 산하 영업자가 담당하는 학원 vendor id */
    public static function vendorIds(int $distributorId): array
    {
        $agentIds = self::agentIds($distributorId);
        if (empty($agentIds)) return [];

        return DB::table('agent_vendor_discounts')
            ->whereIn('agent_user_id', $agentIds)
            ->where('is_active', true)
            ->pluck('vendor_id')
            ->map(fn ($v) => (int) $v)
            ->unique()->values()->all();
    }

    /** 산하 학원 계정 user id */
    public static function academyUserIds(int $distributorId): array
    {
        $vendorIds = self::vendorIds($distributorId);
        if (empty($vendorIds)) return [];

        return DB::table('vendor_users')
            ->whereIn('vendor_id', $vendorIds)
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()->values()->all();
    }

    /** 산하 사용자 전체 (영업자 + 학원 계정), $includeSelf 면 총판 본인 포함 */
    public static function userIds(int $distributorId, bool $includeSelf = false): array
    {
        $ids = array_merge(
            $includeSelf ? [$distributorId] : [],
            self::agentIds($distributorId),
            self::academyUserIds($distributorId),
        );
        return array_values(array_unique($ids));
    }

    /** 이 학원이 해당 총판 산하인가 */
    public static function ownsVendor(int $distributorId, int $vendorId): bool
    {
        return in_array($vendorId, self::vendorIds($distributorId), true);
    }

    /** 이 사용자가 해당 총판 산하인가 */
    public static function ownsUser(int $distributorId, int $userId): bool
    {
        return in_array($userId, self::userIds($distributorId), true);
    }
}
