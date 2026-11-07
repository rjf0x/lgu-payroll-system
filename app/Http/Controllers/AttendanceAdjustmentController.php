<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceAdjustmentRequest;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceAdjustmentController extends Controller
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: null;

        $adjustments = AttendanceAdjustment::query()
            ->with(['attendanceRecord.employee', 'requestedBy', 'reviewedBy'])
            ->when($status !== null, fn ($query) => $query->where('approval_status', $status))
            ->orderByDesc('requested_at')
            ->paginate(20)
            ->withQueryString();

        return view('attendance-adjustments.index', compact('adjustments', 'status'));
    }

    public function create(Request $request): View
    {
        return view('attendance-adjustments.create', [
            'records' => AttendanceRecord::with('employee')->orderByDesc('attendance_date')->limit(200)->get(),
            'selectedId' => $request->integer('attendance_id') ?: null,
        ]);
    }

    public function store(AttendanceAdjustmentRequest $request): RedirectResponse
    {
        $attendance = AttendanceRecord::findOrFail($request->integer('attendance_id'));

        AttendanceAdjustment::create([
            'attendance_id' => $attendance->attendance_id,
            'requested_by' => auth()->id(),
            'old_attendance_status' => $attendance->attendance_status,
            'requested_attendance_status' => $request->string('requested_attendance_status')->toString(),
            'old_credited_days' => $attendance->credited_days,
            'requested_credited_days' => $request->input('requested_credited_days'),
            'adjustment_reason' => $request->string('adjustment_reason')->toString(),
            'approval_status' => self::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('attendance-adjustments.index')
            ->with('status', 'Adjustment requested.');
    }

    public function show(AttendanceAdjustment $attendanceAdjustment): View
    {
        $attendanceAdjustment->load(['attendanceRecord.employee', 'requestedBy', 'reviewedBy']);

        return view('attendance-adjustments.show', ['adjustment' => $attendanceAdjustment]);
    }

    public function approve(AttendanceAdjustment $attendanceAdjustment): RedirectResponse
    {
        if ($attendanceAdjustment->approval_status !== self::STATUS_PENDING) {
            return back()->with('error', 'This adjustment has already been reviewed.');
        }

        DB::transaction(function () use ($attendanceAdjustment): void {
            $locked = AttendanceAdjustment::whereKey($attendanceAdjustment->getKey())->lockForUpdate()->firstOrFail();
            $attendanceAdjustment->setRawAttributes($locked->getAttributes(), true);
            abort_unless($attendanceAdjustment->approval_status === self::STATUS_PENDING, 422, 'This record has already been reviewed.');
            $attendanceAdjustment->update([
                'approval_status' => self::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $attendanceAdjustment->attendanceRecord()->lockForUpdate()->firstOrFail()->update([
                'attendance_status' => $attendanceAdjustment->requested_attendance_status,
                'credited_days' => $attendanceAdjustment->requested_credited_days,
            ]);
        });

        return redirect()
            ->route('attendance-adjustments.show', $attendanceAdjustment)
            ->with('status', 'Adjustment approved and applied.');
    }

    public function reject(AttendanceAdjustment $attendanceAdjustment): RedirectResponse
    {
        if ($attendanceAdjustment->approval_status !== self::STATUS_PENDING) {
            return back()->with('error', 'This adjustment has already been reviewed.');
        }

        DB::transaction(function () use ($attendanceAdjustment): void {
            $locked = AttendanceAdjustment::whereKey($attendanceAdjustment->getKey())->lockForUpdate()->firstOrFail();
            $attendanceAdjustment->setRawAttributes($locked->getAttributes(), true);
            abort_unless($attendanceAdjustment->approval_status === self::STATUS_PENDING, 422, 'This record has already been reviewed.');

            $attendanceAdjustment->update([
                'approval_status' => self::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        return redirect()
            ->route('attendance-adjustments.show', $attendanceAdjustment)
            ->with('status', 'Adjustment rejected.');
    }
}
