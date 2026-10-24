<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'type' => $request->string('type')->toString(),
            'event' => $request->string('event')->toString(),
            'subject_id' => $request->integer('subject_id') ?: null,
            'actor' => $request->string('actor')->trim()->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['type'], fn ($q, $type) => $q->where('auditable_type', 'App\\Models\\'.$type))
            ->when($filters['event'], fn ($q, $event) => $q->where('event', $event))
            ->when($filters['subject_id'], fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($filters['actor'], fn ($q, $actor) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$actor}%")
                ->orWhere('email', 'like', "%{$actor}%")))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'type' => class_basename($log->auditable_type),
                'subject_id' => $log->auditable_id,
                'old' => $log->old_values,
                'new' => $log->new_values,
                'description' => $log->description,
                'actor' => $log->user?->name ?? 'System',
                'ip' => $log->ip_address,
                'at' => $log->created_at->toDayDateTimeString(),
            ]);

        return Inertia::render('admin/audit', [
            'logs' => $logs,
            'filters' => $filters,
            'types' => AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type')->map(fn ($t) => class_basename($t)),
        ]);
    }
}
