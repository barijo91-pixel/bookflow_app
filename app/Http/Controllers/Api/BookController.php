<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /** 도서 검색 (목록) */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $school = $request->query('school');
        $subject = $request->query('subject');
        $publisher = $request->query('publisher');
        $perPage = min((int) ($request->query('per_page', 50)), 50);

        $query = Book::query()->where('status_code', 'selling');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('isbn', $q)
                  ->orWhere('author', 'like', "%{$q}%")
                  ->orWhere('series_name', 'like', "%{$q}%");
            });
        }
        if ($school)   $query->where('school_code', $school);
        if ($subject)  $query->where('subject_code', $subject);
        if ($publisher)$query->where('publisher_id', $publisher);

        $books = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'ok'   => true,
            'data' => collect($books->items())->map(fn ($b) => $this->serialize($b))->all(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page'    => $books->lastPage(),
                'per_page'     => $books->perPage(),
                'total'        => $books->total(),
            ],
        ]);
    }

    /** ISBN 단권 조회 (바코드 스캔용) */
    public function showByIsbn(string $isbn)
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
        $book = Book::where('isbn', $isbn)->where('status_code', 'selling')->first();
        if (! $book) {
            return response()->json(['ok' => false, 'error' => '도서를 찾을 수 없습니다.'], 404);
        }
        return response()->json(['ok' => true, 'data' => $this->serialize($book)]);
    }

    private function serialize(Book $b): array
    {
        $stock = (int) DB::table('book_stocks')->where('book_id', $b->id)->sum(DB::raw('qty - reserved_qty'));
        return [
            'id'          => $b->id,
            'isbn'        => $b->isbn,
            'title'       => $b->title,
            'subtitle'    => $b->subtitle,
            'series_name' => $b->series_name,
            'author'      => $b->author,
            'publisher'   => optional($b->publisher)->name,
            'price'       => $b->price,
            'cover'       => $b->cover_path ? (str_starts_with($b->cover_path, 'http') ? $b->cover_path : url('storage/'.$b->cover_path)) : null,
            'school'      => $b->school_code,
            'subject'     => $b->subject_code,
            'available_qty' => max(0, $stock),
        ];
    }
}
