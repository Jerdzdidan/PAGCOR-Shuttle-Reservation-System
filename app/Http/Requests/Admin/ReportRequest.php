<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminReportService;
use App\Services\Reports\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $now = CarbonImmutable::now((string) config('shuttle.operating_timezone', 'Asia/Manila'));
        $reportSlug = $this->route('reportSlug');
        $reportKey = is_string($reportSlug)
            ? AdminReportService::reportKeyFromSlug($reportSlug)
            : null;

        $this->merge([
            'report' => $reportKey,
            'date_from' => $this->input('date_from', $now->startOfMonth()->toDateString()),
            'date_to' => $this->input('date_to', $now->endOfMonth()->toDateString()),
            'per_page' => $this->input('per_page', 25),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report' => ['required', 'string', Rule::in(AdminReportService::reportKeys())],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'route_id' => ['nullable', 'integer', 'exists:shuttle_routes,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:shuttle_schedules,id'],
            'department' => ['nullable', 'string', 'max:100'],
            'priority_status' => ['nullable', 'string', Rule::in(['REGULAR', 'PRIORITY'])],
            'status' => ['nullable', 'string', Rule::in($this->allowedStatuses())],
            'sort' => ['nullable', 'string', Rule::in($this->allowedSortKeys())],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'csv', 'pdf'])],
        ];
    }

    /**
     * Statuses the current report actually understands, so a stale filter fails loudly
     * instead of silently returning nothing.
     *
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        $report = $this->input('report');

        return is_string($report) && ReportCatalog::exists($report)
            ? array_column(ReportCatalog::statusOptions($report), 'value')
            : [];
    }

    /** @return list<string> */
    private function allowedSortKeys(): array
    {
        $report = $this->input('report');

        return is_string($report) && ReportCatalog::exists($report)
            ? ReportCatalog::columnKeys($report)
            : [];
    }
}
