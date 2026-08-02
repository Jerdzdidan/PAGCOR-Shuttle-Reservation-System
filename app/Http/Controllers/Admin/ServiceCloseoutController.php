<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteServiceRequest;
use App\Http\Requests\CorrectServiceRecordRequest;
use App\Http\Requests\MarkServiceNotOperatedRequest;
use App\Http\Requests\ReopenServiceRequest;
use App\Models\ShuttleServiceOccurrence;
use App\Models\User;
use App\Services\ServiceOccurrenceData;
use App\Services\ShuttleServiceCloseoutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ServiceCloseoutController extends Controller
{
    public function complete(
        CompleteServiceRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceCloseoutService $closeoutService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $occurrence = $closeoutService->complete(
            $serviceOccurrence,
            $this->administrator($request),
            $request->validated()
        );

        return $this->respond(
            $request,
            $occurrence,
            $occurrenceData,
            'Service completed successfully.'
        );
    }

    public function notOperated(
        MarkServiceNotOperatedRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceCloseoutService $closeoutService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $occurrence = $closeoutService->markNotOperated(
            $serviceOccurrence,
            $this->administrator($request),
            $request->validated()
        );

        return $this->respond(
            $request,
            $occurrence,
            $occurrenceData,
            'Service marked as not operated.'
        );
    }

    public function correct(
        CorrectServiceRecordRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceCloseoutService $closeoutService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $data = $request->validated();
        $data['reason'] = $data['reason'] ?? $data['correction_reason'];
        unset($data['correction_reason']);

        $occurrence = $closeoutService->correct(
            $serviceOccurrence,
            $this->administrator($request),
            $data
        );

        return $this->respond(
            $request,
            $occurrence,
            $occurrenceData,
            'Service record corrected and audited.'
        );
    }

    public function reopen(
        ReopenServiceRequest $request,
        ShuttleServiceOccurrence $serviceOccurrence,
        ShuttleServiceCloseoutService $closeoutService,
        ServiceOccurrenceData $occurrenceData,
    ): JsonResponse|RedirectResponse {
        $occurrence = $closeoutService->reopen(
            $serviceOccurrence,
            $this->administrator($request),
            $request->validated('reason')
        );

        return $this->respond(
            $request,
            $occurrence,
            $occurrenceData,
            'Service reopened for audited refinalization.'
        );
    }

    private function administrator(FormRequest $request): User
    {
        $administrator = $request->user();
        abort_unless($administrator instanceof User, 403);

        return $administrator;
    }

    private function respond(
        FormRequest $request,
        ShuttleServiceOccurrence $occurrence,
        ServiceOccurrenceData $occurrenceData,
        string $message,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'occurrence' => $occurrenceData->detail($occurrence),
            ]);
        }

        return back()->with('success', $message);
    }
}
