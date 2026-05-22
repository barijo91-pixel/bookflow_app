<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    public function sigungu(Request $request)
    {
        $sidoId = (int) $request->query('sido_id');
        if (! $sidoId) {
            return response()->json([]);
        }
        $rows = DB::table('regions')
            ->where('parent_id', $sidoId)
            ->where('level', 'sigungu')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);
        return response()->json($rows);
    }
}
