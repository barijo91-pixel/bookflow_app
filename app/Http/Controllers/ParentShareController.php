<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentShareController extends Controller
{
    /** 학부모 공유링크 페이지 (공개, 토큰 기반) */
    public function show(string $token)
    {
        $link = DB::table('parent_share_links')->where('token', $token)->first();
        abort_if(! $link, 404);
        if ($link->expires_at && now()->greaterThan($link->expires_at)) {
            return view('parent.share_expired');
        }

        // 접속 카운트 증가
        DB::table('parent_share_links')->where('id', $link->id)->update([
            'accessed_at' => now(),
            'access_count' => DB::raw('access_count + 1'),
            'updated_at' => now(),
        ]);

        $class   = DB::table('academy_classes')->find($link->class_id);
        $vendor  = $class ? DB::table('vendors')->find($class->vendor_id) : null;
        $student = $link->student_id ? DB::table('students')->find($link->student_id) : null;
        $parent  = $link->parent_id ? DB::table('parents')->find($link->parent_id) : null;

        $books = DB::table('class_books as cb')
            ->join('books as b', 'b.id', '=', 'cb.book_id')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->where('cb.class_id', $link->class_id)
            ->select('b.isbn','b.title','b.subtitle','b.cover_path','b.price','b.author',
                'p.name as publisher_name','cb.qty')
            ->orderBy('cb.sort_order')->get();

        return view('parent.share', compact('class', 'vendor', 'student', 'parent', 'books'));
    }
}
