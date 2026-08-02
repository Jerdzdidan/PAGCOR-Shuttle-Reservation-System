<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkAllServiceAttendanceRequest;
use App\Http\Requests\RecordServiceAttendanceByEmployeeCodeRequest;
use App\Http\Requests\ScanServiceAttendanceRequest;
use App\Http\Requests\UpdateServiceAttendanceRequest;
use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceOccurrence;
use App\Models\User;
use App\Services\ServiceOccurrenceData;
use App\Services\ShuttleServiceAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ServiceAttendanceController extends Controller
{
    public function scan(
        ScanServiceAttendanceRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceAttendanceService $attendanceService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $attendanceService->scan(
            $serviceOccurrence,
            $request->validated('credential'),
            $this->administrator($request)
        );

        return $this->respond(
            $request,
            $serviceOccurrence,
            $occurrenceData,
            'Passenger boarded successfully.'
        );
    }

    public function update(
        UpdateServiceAttendanceRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleReservation $reservation,
        ShuttleServiceAttendanceService $attendanceService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        if ($request->validated('status') === 'UNMARKED') {
            $attendanceService->unmark($serviceOccurrence, $reservation);
            $message = 'Passenger attendance cleared.';
        } else {
            $attendanceService->markBoarded(
                $serviceOccurrence,
                $reservation,
                $this->administrator($request)
            );
            $message = 'Passenger marked as boarded.';
        }

        return $this->respond(
            $request,
            $serviceOccurrence,
            $occurrenceData,
            $message
        );
    }

    public function recordByEmployeeCode(
        RecordServiceAttendanceByEmployeeCodeRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceAttendanceService $attendanceService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $attendanceService->recordByEmployeeCode(
            $serviceOccurrence,
            $request->validated('employee_code'),
            $this->administrator($request)
        );

        return $this->respond(
            $request,
            $serviceOccurrence,
            $occurrenceData,
            'Passenger boarded successfully.'
        );
    }

    public function markAll(
        MarkAllServiceAttendanceRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceAttendanceService $attendanceService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $count = $attendanceService->markAllBoarded(
            $serviceOccurrence,
            $this->administrator($request)
        );

        return $this->respond(
            $request,
            $serviceOccurrence,
            $occurrenceData,
            "{$count} reserved passengers are marked as boarded."
        );
    }

    private function administrator(
        MarkAllServiceAttendanceRequest|RecordServiceAttendanceByEmployeeCodeRequest|ScanServiceAttendanceRequest|UpdateServiceAttendanceRequest $request,
    ): User {
        $administrator = $request->user();
        abort_unless($administrator instanceof User, 403);

        return $administrator;
    }

    private function respond(
        MarkAllServiceAttendanceRequest|RecordServiceAttendanceByEmployeeCodeRequest|ScanServiceAttendanceRequest|UpdateServiceAttendanceRequest $request,
        ShuttleServiceOccurrence $occurrence,
        ServiceOccurrenceData $occurrenceData,
        string $message,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'occurrence' => $occurrenceData->detail($occurrence->refresh()),
            ]);
        }

        return back()->with('success', $message);
    }
}
