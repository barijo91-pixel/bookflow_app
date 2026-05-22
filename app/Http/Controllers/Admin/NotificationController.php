<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    // -------------------- TEMPLATES --------------------
    public function templates()
    {
        $templates = DB::table('notification_templates')
            ->orderBy('event_code')
            ->orderBy('channel')
            ->get();

        $events = DB::table('codes')->where('group_code', 'notify_event')->orderBy('sort_order')->get();
        $channels = DB::table('codes')->where('group_code', 'notify_channel')->orderBy('sort_order')->get();

        return view('admin.notifications.templates', compact('templates', 'events', 'channels'));
    }

    public function updateTemplate(Request $request, int $id)
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'aligo_template_code' => ['nullable', 'string', 'max:50'],
            'subject'             => ['nullable', 'string', 'max:200'],
            'body'                => ['required', 'string'],
            'is_active'           => ['nullable', 'boolean'],
        ]);
        DB::table('notification_templates')->where('id', $id)->update([
            'name'                => $data['name'],
            'aligo_template_code' => $data['aligo_template_code'] ?? null,
            'subject'             => $data['subject'] ?? null,
            'body'                => $data['body'],
            'is_active'           => $request->boolean('is_active'),
            'updated_at'          => now(),
        ]);
        return back()->with('success', '템플릿이 저장되었습니다.');
    }

    // -------------------- LOGS --------------------
    public function logs(Request $request)
    {
        $event   = $request->query('event');
        $channel = $request->query('channel');
        $status  = $request->query('status');
        $q       = trim((string) $request->query('q'));

        $query = DB::table('notifications')->orderByDesc('id');
        if ($event)   $query->where('event_code', $event);
        if ($channel) $query->where('channel', $channel);
        if ($status)  $query->where('status', $status);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('recipient_phone', 'like', "%{$q}%")
                  ->orWhere('recipient_email', 'like', "%{$q}%")
                  ->orWhere('payload', 'like', "%{$q}%");
            });
        }
        $logs = $query->paginate(30)->withQueryString();

        $events = DB::table('codes')->where('group_code', 'notify_event')->orderBy('sort_order')->get();
        $channels = DB::table('codes')->where('group_code', 'notify_channel')->orderBy('sort_order')->get();

        $summary = [
            'total'   => DB::table('notifications')->count(),
            'sent'    => DB::table('notifications')->where('status', 'sent')->count(),
            'failed'  => DB::table('notifications')->where('status', 'failed')->count(),
            'skipped' => DB::table('notifications')->where('status', 'skipped')->count(),
        ];

        return view('admin.notifications.logs', compact('logs', 'events', 'channels', 'event', 'channel', 'status', 'q', 'summary'));
    }
}
