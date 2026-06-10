<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    // -------------------- LIST --------------------
    public function index(Request $request)
    {
        $vendor = $request->query('vendor');
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));

        $allowedSorts = [
            'id'           => 'c.id',
            'name'         => 'c.name',
            'vendor_name'  => 'v.name',
            'grade_code'   => 'c.grade_code',
            'status'       => 'c.status',
            'started_at'   => 'c.started_at',
        ];
        $sort = $request->query('sort', 'id');
        $dir  = $request->query('dir', 'desc');
        if (! array_key_exists($sort, $allowedSorts)) $sort = 'id';
        if (! in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

        $query = DB::table('academy_classes as c')
            ->leftJoin('vendors as v', 'v.id', '=', 'c.vendor_id')
            ->leftJoin('users as t', 't.id', '=', 'c.teacher_user_id')
            ->select(
                'c.id', 'c.name', 'c.grade_code', 'c.status', 'c.started_at', 'c.ended_at',
                'v.id as vendor_id', 'v.name as vendor_name',
                't.name as teacher_name'
            )
            ->orderBy($allowedSorts[$sort], $dir);
        if ($sort !== 'id') $query->orderByDesc('c.id');

        if ($vendor) $query->where('c.vendor_id', $vendor);
        if ($status) $query->where('c.status', $status);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('c.name', 'like', "%{$q}%")
                  ->orWhere('v.name', 'like', "%{$q}%");
            });
        }

        $classes = $query->paginate(20)->withQueryString();

        // 각 학급의 학생 수
        $classIds = $classes->pluck('id')->toArray();
        $counts = DB::table('students')
            ->whereIn('class_id', $classIds)
            ->whereNull('deleted_at')
            ->select('class_id', DB::raw('count(*) as cnt'))
            ->groupBy('class_id')->pluck('cnt', 'class_id');
        foreach ($classes as $c) {
            $c->student_count = $counts[$c->id] ?? 0;
        }

        $vendors = DB::table('vendors')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.classes.index', compact('classes', 'vendors', 'vendor', 'status', 'q', 'sort', 'dir'));
    }

    public function create()
    {
        $vendors = DB::table('vendors')->where('status_code', 'active')->orderBy('name')->get(['id', 'name']);
        $grades  = DB::table('codes')->where('group_code', 'grade')->orderBy('sort_order')->get();
        return view('admin.classes.create', compact('vendors', 'grades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id'  => ['required', 'integer', 'exists:vendors,id'],
            'name'       => ['required', 'string', 'max:100'],
            'grade_code' => ['nullable', 'string', 'max:30'],
            'started_at' => ['nullable', 'date'],
            'ended_at'   => ['nullable', 'date'],
            'memo'       => ['nullable', 'string', 'max:1000'],
        ]);
        $data['status'] = 'active';
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('academy_classes')->insertGetId($data);
        return redirect()->route('admin.classes.show', $id)->with('success', '학급이 등록되었습니다.');
    }

    public function show($id)
    {
        $class = DB::table('academy_classes')->where('id', $id)->first();
        abort_if(! $class, 404);

        $vendor = DB::table('vendors')->find($class->vendor_id);
        $grades = DB::table('codes')->where('group_code', 'grade')->orderBy('sort_order')->get();

        $students = DB::table('students as s')
            ->leftJoin('parents as p', 'p.id', '=', 's.parent_id')
            ->where('s.class_id', $id)
            ->whereNull('s.deleted_at')
            ->select('s.id','s.name','s.grade_code','s.parent_id',
                'p.name as parent_name','p.phone as parent_phone','p.email as parent_email')
            ->orderBy('s.name')->get();

        $books = DB::table('class_books as cb')
            ->join('books as b', 'b.id', '=', 'cb.book_id')
            ->leftJoin('publishers as pb', 'pb.id', '=', 'b.publisher_id')
            ->where('cb.class_id', $id)
            ->select('cb.id as cb_id','cb.qty','cb.sort_order',
                'b.id as book_id','b.isbn','b.title','b.cover_path','b.price',
                'pb.name as publisher_name')
            ->orderBy('cb.sort_order')->get();

        $shareLinks = DB::table('parent_share_links as l')
            ->leftJoin('parents as p', 'p.id', '=', 'l.parent_id')
            ->leftJoin('students as s', 's.id', '=', 'l.student_id')
            ->where('l.class_id', $id)
            ->select('l.*', 'p.name as parent_name', 'p.phone as parent_phone', 's.name as student_name')
            ->orderByDesc('l.id')->get();

        return view('admin.classes.show', compact('class', 'vendor', 'grades', 'students', 'books', 'shareLinks'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'grade_code' => ['nullable', 'string', 'max:30'],
            'started_at' => ['nullable', 'date'],
            'ended_at'   => ['nullable', 'date'],
            'memo'       => ['nullable', 'string', 'max:1000'],
            'status'     => ['required', Rule::in(['active', 'closed'])],
        ]);
        $data['updated_at'] = now();
        DB::table('academy_classes')->where('id', $id)->update($data);
        return back()->with('success', '학급이 수정되었습니다.');
    }

    public function destroy($id)
    {
        if (DB::table('students')->where('class_id', $id)->whereNull('deleted_at')->exists()) {
            return back()->with('error', '소속 학생이 있는 학급은 삭제할 수 없습니다. 상태를 "종료"로 변경하세요.');
        }
        DB::table('academy_classes')->where('id', $id)->delete();
        return redirect()->route('admin.classes.index')->with('success', '학급이 삭제되었습니다.');
    }

    // -------------------- 학생 + 학부모 --------------------
    public function attachStudent(Request $request, $classId)
    {
        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:100'],
            'grade_code'   => ['nullable', 'string', 'max:30'],
            'parent_name'  => ['nullable', 'string', 'max:100'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
            'parent_email' => ['nullable', 'email', 'max:150'],
        ]);

        $class = DB::table('academy_classes')->where('id', $classId)->first();
        abort_if(! $class, 404);

        $parentId = null;
        if (! empty($data['parent_phone']) || ! empty($data['parent_name'])) {
            // 휴대폰 기준으로 중복 매칭
            if (! empty($data['parent_phone'])) {
                $parent = DB::table('parents')->where('phone', $data['parent_phone'])->whereNull('deleted_at')->first();
                if ($parent) $parentId = $parent->id;
            }
            if (! $parentId) {
                $parentId = DB::table('parents')->insertGetId([
                    'name'  => $data['parent_name']  ?? '학부모',
                    'phone' => $data['parent_phone'] ?? '',
                    'email' => $data['parent_email'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // 이름/이메일 업데이트
                $update = [];
                if (! empty($data['parent_name'])) $update['name'] = $data['parent_name'];
                if (! empty($data['parent_email'])) $update['email'] = $data['parent_email'];
                if ($update) {
                    $update['updated_at'] = now();
                    DB::table('parents')->where('id', $parentId)->update($update);
                }
            }
        }

        DB::table('students')->insert([
            'vendor_id'  => $class->vendor_id,
            'class_id'   => $classId,
            'parent_id'  => $parentId,
            'name'       => $data['student_name'],
            'grade_code' => $data['grade_code'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', '학생이 추가되었습니다.');
    }

    public function detachStudent($classId, $studentId)
    {
        DB::table('students')->where('id', $studentId)->where('class_id', $classId)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', '학생이 제거되었습니다.');
    }

    // -------------------- 교재 매핑 --------------------
    public function attachBook(Request $request, $classId)
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'qty'     => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (DB::table('class_books')->where('class_id', $classId)->where('book_id', $data['book_id'])->exists()) {
            return back()->with('error', '이미 등록된 교재입니다.');
        }
        DB::table('class_books')->insert([
            'class_id' => $classId,
            'book_id'  => $data['book_id'],
            'qty'      => $data['qty'],
            'sort_order' => $data['sort_order'] ?? 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', '교재가 추가되었습니다.');
    }

    public function detachBook($classId, $cbId)
    {
        DB::table('class_books')->where('id', $cbId)->where('class_id', $classId)->delete();
        return back()->with('success', '교재가 제거되었습니다.');
    }

    // -------------------- 공유링크 --------------------
    public function createShareLink(Request $request, $classId, NotificationService $notify)
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $student = DB::table('students')->find($data['student_id']);
        abort_if(! $student || $student->class_id != $classId, 404);

        if (! $student->parent_id) {
            return back()->with('error', '학부모 정보가 등록된 학생만 공유링크를 발송할 수 있습니다.');
        }
        $parent = DB::table('parents')->find($student->parent_id);
        if (! $parent || ! $parent->phone) {
            return back()->with('error', '학부모 휴대폰이 없습니다.');
        }

        $token = Str::random(40);
        $expiresAt = $request->boolean('expires_days') ? now()->addDays((int) $data['expires_days']) : now()->addDays(30);

        $linkId = DB::table('parent_share_links')->insertGetId([
            'class_id'   => $classId,
            'student_id' => $student->id,
            'parent_id'  => $parent->id,
            'token'      => $token,
            'sent_at'    => now(),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 알림 발송 (학부모)
        $class = DB::table('academy_classes')->find($classId);
        $vendor = DB::table('vendors')->find($class->vendor_id);
        $url = url('/p/'.$token);

        $notify->send('b2c.share_link', [
            'academy_name' => $vendor->name ?? '',
            'student_name' => $student->name,
            'link_url'     => $url,
        ], [
            ['type' => 'parent', 'id' => $parent->id, 'phone' => $parent->phone, 'email' => $parent->email],
        ]);

        return back()->with('success', "공유링크 발송 완료 — {$parent->name}({$parent->phone})에게 알림톡/SMS 발송됨.")
            ->with('share_url', $url);
    }
}
