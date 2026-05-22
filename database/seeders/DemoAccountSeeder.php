<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $pw = Hash::make('1234');

        // 시도 ID 캐싱 (서울)
        $seoulId = DB::table('regions')->where('name', '서울특별시')->where('level', 'sido')->value('id');
        $gangnam = DB::table('regions')->where('parent_id', $seoulId)->where('name', '강남구')->value('id');

        // 관리자
        $adminId = DB::table('users')->insertGetId([
            'email' => 'admin@bookflow.local',
            'password' => $pw,
            'name' => '시스템관리자',
            'phone' => '01000000000',
            'phone_verified_at' => $now,
            'email_verified_at' => $now,
            'role_code' => 'admin',
            'admin_level' => 'super',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 총판 2개
        $distA = DB::table('users')->insertGetId([
            'email' => 'distA@bookflow.local',
            'password' => $pw,
            'name' => '한국교재총판',
            'phone' => '01011110000',
            'phone_verified_at' => $now,
            'role_code' => 'distributor',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $distB = DB::table('users')->insertGetId([
            'email' => 'distB@bookflow.local',
            'password' => $pw,
            'name' => '잉글리시북스',
            'phone' => '01022220000',
            'phone_verified_at' => $now,
            'role_code' => 'distributor',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 영업자 2명
        $ag1 = DB::table('users')->insertGetId([
            'email' => 'agent1@bookflow.local',
            'password' => $pw,
            'name' => '김영업',
            'phone' => '01033330001',
            'phone_verified_at' => $now,
            'role_code' => 'agent',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ag2 = DB::table('users')->insertGetId([
            'email' => 'agent2@bookflow.local',
            'password' => $pw,
            'name' => '이영업',
            'phone' => '01033330002',
            'phone_verified_at' => $now,
            'role_code' => 'agent',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 학원 담당 3명
        $ac1 = DB::table('users')->insertGetId([
            'email' => 'academy1@bookflow.local',
            'password' => $pw,
            'name' => '박원장',
            'phone' => '01044440001',
            'phone_verified_at' => $now,
            'role_code' => 'academy',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ac2 = DB::table('users')->insertGetId([
            'email' => 'academy2@bookflow.local',
            'password' => $pw,
            'name' => '최원장',
            'phone' => '01044440002',
            'phone_verified_at' => $now,
            'role_code' => 'academy',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ac3 = DB::table('users')->insertGetId([
            'email' => 'academy3@bookflow.local',
            'password' => $pw,
            'name' => '정원장',
            'phone' => '01044440003',
            'phone_verified_at' => $now,
            'role_code' => 'academy',
            'status_code' => 'active',
            'region_id' => $gangnam,
            'approved_by' => $adminId,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 총판 → 영업자 N:N 관계
        $rel = function ($parent, $child, $type) use ($now) {
            return [
                'parent_user_id' => $parent,
                'child_user_id' => $child,
                'relation_type' => $type,
                'status' => 'active',
                'started_at' => $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };
        DB::table('user_relations')->insert([
            $rel($distA, $ag1, 'distributor_agent'),
            $rel($distA, $ag2, 'distributor_agent'),
            $rel($distB, $ag1, 'distributor_agent'), // ag1 는 두 총판 다 다룸
        ]);

        // 거래처(학원) 3개
        $v1 = DB::table('vendors')->insertGetId([
            'name' => '이런어학원 강남캠퍼스',
            'owner_name' => '박원장',
            'business_no' => '123-45-67890',
            'type_code' => 'academy',
            'status_code' => 'active',
            'biz_type' => '서비스',
            'biz_item' => '교육서비스',
            'mobile' => '01044440001',
            'region_id' => $gangnam,
            'address' => '서울 강남구 테헤란로 1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $v2 = DB::table('vendors')->insertGetId([
            'name' => '브라이트영어학원',
            'owner_name' => '최원장',
            'business_no' => '234-56-78901',
            'type_code' => 'academy',
            'status_code' => 'active',
            'mobile' => '01044440002',
            'region_id' => $gangnam,
            'address' => '서울 강남구 도산대로 50',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $v3 = DB::table('vendors')->insertGetId([
            'name' => '키즈리딩스쿨',
            'owner_name' => '정원장',
            'business_no' => '345-67-89012',
            'type_code' => 'academy',
            'status_code' => 'active',
            'mobile' => '01044440003',
            'region_id' => $gangnam,
            'address' => '서울 강남구 선릉로 100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // vendor_users 매핑
        DB::table('vendor_users')->insert([
            ['vendor_id' => $v1, 'user_id' => $ac1, 'role' => 'owner', 'is_primary' => true, 'created_at' => $now, 'updated_at' => $now],
            ['vendor_id' => $v2, 'user_id' => $ac2, 'role' => 'owner', 'is_primary' => true, 'created_at' => $now, 'updated_at' => $now],
            ['vendor_id' => $v3, 'user_id' => $ac3, 'role' => 'owner', 'is_primary' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 영업자→학원 관계
        DB::table('user_relations')->insert([
            $rel($ag1, $ac1, 'agent_academy'),
            $rel($ag1, $ac2, 'agent_academy'),
            $rel($ag2, $ac3, 'agent_academy'),
        ]);

        // 영업자×학원 기본 할인율
        DB::table('agent_vendor_discounts')->insert([
            ['agent_user_id' => $ag1, 'vendor_id' => $v1, 'discount_rate' => 30, 'started_at' => $now->toDateString(), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['agent_user_id' => $ag1, 'vendor_id' => $v2, 'discount_rate' => 25, 'started_at' => $now->toDateString(), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['agent_user_id' => $ag2, 'vendor_id' => $v3, 'discount_rate' => 35, 'started_at' => $now->toDateString(), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
