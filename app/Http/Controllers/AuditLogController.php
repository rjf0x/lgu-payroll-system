<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function show(AuditLog $auditLog, AuditLogger $auditLogger): View
    {
        return view('audit-logs.show', [
            'log' => $auditLog->load('user'),
            'before' => $auditLog->old_values === null ? null : $auditLogger->redact($auditLog->old_values),
            'after' => $auditLog->new_values === null ? null : $auditLogger->redact($auditLog->new_values),
        ]);
    }

    public function index(AuditLogFilterRequest $request): View
    {
        return view('audit-logs.index', [
            'logs' => $this->filteredLogs($request)->orderByDesc('audit_id')->paginate(25)->withQueryString(),
            'filters' => $request->validated(),
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => User::query()->orderBy('username')->get(['user_id', 'username']),
        ]);
    }

    public function export(AuditLogFilterRequest $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = $this->filteredLogs($request)->where('audit_id', '<=', AuditLog::max('audit_id') ?? 0);

        $auditLogger->log('EXPORT', 'Audit', (int) $request->user()->getAuthIdentifier(),
            newValues: ['filters' => $request->validated()]);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Audit ID', 'Timestamp ('.config('app.display_timezone').')', 'User', 'Module', 'Action', 'Record table', 'Record ID', 'IP address'], escape: '');

            foreach ($query->lazyByIdDesc(500, 'audit_id') as $log) {
                $cells = [
                    (string) $log->audit_id,
                    $log->created_at?->copy()->setTimezone(config('app.display_timezone'))->format('Y-m-d H:i:s') ?? '',
                    $log->user?->username ?? 'System / unavailable user',
                    $log->module,
                    $log->action,
                    $log->record_table ?? '',
                    $log->record_id ?? '',
                    $log->ip_address ?? '',
                ];

                fputcsv($output, array_map($this->safeCsvCell(...), $cells), escape: '');
            }

            fclose($output);
        }, 'audit-log-'.now()->setTimezone(config('app.display_timezone'))->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return Builder<AuditLog> */
    private function filteredLogs(AuditLogFilterRequest $request): Builder
    {
        $filters = $request->validated();
        $query = AuditLog::query()->select(['audit_id', 'user_id', 'action', 'module', 'record_table', 'record_id', 'ip_address', 'created_at'])->with('user:user_id,username');

        foreach (['module', 'action', 'user_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], config('app.display_timezone'))->startOfDay()->utc()->toDateTimeString());
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], config('app.display_timezone'))->addDay()->startOfDay()->utc()->toDateTimeString());
        }

        return $query;
    }

    private function safeCsvCell(string $value): string
    {
        if (preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
