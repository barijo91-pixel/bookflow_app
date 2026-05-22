<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('orders')->exists()) {
            $this->command->warn('Orders already exist, skipping demo orders.');
            return;
        }

        $ag1 = DB::table('users')->where('email', 'agent1@bookflow.local')->value('id');
        $ag2 = DB::table('users')->where('email', 'agent2@bookflow.local')->value('id');
        $distA = DB::table('users')->where('email', 'distA@bookflow.local')->value('id');
        $distB = DB::table('users')->where('email', 'distB@bookflow.local')->value('id');

        $v1 = DB::table('vendors')->where('name', '이런어학원 강남캠퍼스')->value('id');
        $v2 = DB::table('vendors')->where('name', '브라이트영어학원')->value('id');
        $v3 = DB::table('vendors')->where('name', '키즈리딩스쿨')->value('id');

        $books = DB::table('books')->orderBy('id')->limit(10)->get();
        $bookArr = $books->toArray();

        // 8개 주문, 다양한 상태로
        $scenarios = [
            ['vendor' => $v1, 'agent' => $ag1, 'dist' => $distA, 'status' => 'requested',  'days_ago' => 0, 'lines' => 3],
            ['vendor' => $v2, 'agent' => $ag1, 'dist' => $distB, 'status' => 'confirmed',  'days_ago' => 0, 'lines' => 2],
            ['vendor' => $v3, 'agent' => $ag2, 'dist' => $distA, 'status' => 'accepted',   'days_ago' => 1, 'lines' => 4],
            ['vendor' => $v1, 'agent' => $ag1, 'dist' => $distA, 'status' => 'shipped',    'days_ago' => 2, 'lines' => 2],
            ['vendor' => $v2, 'agent' => $ag1, 'dist' => $distB, 'status' => 'in_transit', 'days_ago' => 3, 'lines' => 3],
            ['vendor' => $v3, 'agent' => $ag2, 'dist' => $distA, 'status' => 'completed',  'days_ago' => 5, 'lines' => 5],
            ['vendor' => $v1, 'agent' => $ag1, 'dist' => $distA, 'status' => 'completed',  'days_ago' => 7, 'lines' => 2],
            ['vendor' => $v2, 'agent' => $ag1, 'dist' => $distB, 'status' => 'canceled',   'days_ago' => 4, 'lines' => 2],
        ];

        $orderNo = 1;
        foreach ($scenarios as $sc) {
            $now = now()->subDays($sc['days_ago']);
            $requestedAt = $now->copy()->subHours(rand(1, 5));
            $confirmedAt = in_array($sc['status'], ['confirmed','accepted','shipped','in_transit','completed'], true) ? $requestedAt->copy()->addMinutes(rand(10, 60)) : null;
            $acceptedAt  = in_array($sc['status'], ['accepted','shipped','in_transit','completed'], true) ? ($confirmedAt ? $confirmedAt->copy()->addHours(1) : null) : null;
            $shippedAt   = in_array($sc['status'], ['shipped','in_transit','completed'], true) ? ($acceptedAt ? $acceptedAt->copy()->addHours(rand(2, 8)) : null) : null;
            $completedAt = $sc['status'] === 'completed' ? ($shippedAt ? $shippedAt->copy()->addDays(rand(1, 3)) : null) : null;
            $canceledAt  = $sc['status'] === 'canceled' ? $requestedAt->copy()->addHours(rand(1, 24)) : null;

            // ship_to: vendor 주소 사용
            $vendor = DB::table('vendors')->where('id', $sc['vendor'])->first();

            // 할인율
            $discount = DB::table('agent_vendor_discounts')
                ->where('agent_user_id', $sc['agent'])
                ->where('vendor_id', $sc['vendor'])
                ->value('discount_rate') ?? 30.0;

            // 주문 라인
            $items = [];
            $subtotal = 0;
            $usedBooks = collect($bookArr)->shuffle()->take($sc['lines']);
            foreach ($usedBooks as $b) {
                $qty = rand(2, 10);
                $price = (int) $b->price;
                $rate = (float) $discount;
                $unit = (int) round($price * (100 - $rate) / 100);
                $lineTotal = $unit * $qty;
                $subtotal += $lineTotal;
                $items[] = [
                    'book_id'       => $b->id,
                    'qty'           => $qty,
                    'list_price'    => $price,
                    'discount_rate' => $rate,
                    'discount_source' => 'default',
                    'unit_price'    => $unit,
                    'line_total'    => $lineTotal,
                    'isbn_snapshot' => $b->isbn,
                    'title_snapshot'=> $b->title,
                ];
            }
            $shipping = 0;

            $orderId = DB::table('orders')->insertGetId([
                'order_no'        => 'BF'.$now->format('Ymd').str_pad((string)$orderNo, 4, '0', STR_PAD_LEFT),
                'vendor_id'       => $sc['vendor'],
                'agent_user_id'   => $sc['agent'],
                'distributor_user_id' => $sc['dist'],
                'status_code'     => $sc['status'],
                'ship_to_region_id' => $vendor->region_id,
                'ship_to_address' => $vendor->address,
                'ship_to_address_detail' => $vendor->address_detail,
                'ship_to_contact' => $vendor->owner_name.' / '.$vendor->mobile,
                'subtotal_amount' => $subtotal,
                'shipping_fee'    => $shipping,
                'total_amount'    => $subtotal + $shipping,
                'requested_at'    => $requestedAt,
                'confirmed_at'    => $confirmedAt,
                'accepted_at'     => $acceptedAt,
                'shipped_at'      => $shippedAt,
                'completed_at'    => $completedAt,
                'canceled_at'     => $canceledAt,
                'memo'            => null,
                'created_at'      => $requestedAt,
                'updated_at'      => $completedAt ?? $canceledAt ?? $shippedAt ?? $acceptedAt ?? $confirmedAt ?? $requestedAt,
            ]);

            foreach ($items as $item) {
                DB::table('order_items')->insert(array_merge($item, [
                    'order_id'   => $orderId,
                    'created_at' => $requestedAt,
                    'updated_at' => $requestedAt,
                ]));
            }

            // status logs
            $logs = [
                ['to_status' => 'requested', 'at' => $requestedAt],
            ];
            if ($confirmedAt) $logs[] = ['to_status' => 'confirmed', 'at' => $confirmedAt];
            if ($acceptedAt)  $logs[] = ['to_status' => 'accepted',  'at' => $acceptedAt];
            if ($shippedAt)   $logs[] = ['to_status' => 'shipped',   'at' => $shippedAt];
            if ($completedAt) $logs[] = ['to_status' => 'completed', 'at' => $completedAt];
            if ($canceledAt)  $logs[] = ['to_status' => 'canceled',  'at' => $canceledAt];

            $prev = null;
            foreach ($logs as $log) {
                DB::table('order_status_logs')->insert([
                    'order_id'   => $orderId,
                    'from_status'=> $prev,
                    'to_status'  => $log['to_status'],
                    'changed_by' => $sc['agent'],
                    'created_at' => $log['at'],
                ]);
                $prev = $log['to_status'];
            }

            // shipment (shipped/in_transit/completed만)
            if ($shippedAt) {
                $courier = ['cj', 'lotte', 'hanjin'][array_rand(['cj', 'lotte', 'hanjin'])];
                DB::table('order_shipments')->insert([
                    'order_id'         => $orderId,
                    'courier_code'     => $courier,
                    'tracking_no'      => str_pad((string) rand(100000000000, 999999999999), 12, '0'),
                    'ship_status_code' => $completedAt ? 'delivered' : ($sc['status'] === 'in_transit' ? 'in_transit' : 'shipped'),
                    'shipped_at'       => $shippedAt,
                    'delivered_at'     => $completedAt,
                    'created_at'       => $shippedAt,
                    'updated_at'       => $completedAt ?? $shippedAt,
                ]);
            }

            $orderNo++;
        }

        $this->command->info('Demo orders created: ' . count($scenarios) . ' orders.');
    }
}
