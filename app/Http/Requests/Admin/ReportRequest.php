<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminReportService;
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
            'department' => ['nullable', 'string', 'max:100'],
            'priority_status' => ['nullable', 'string', Rule::in(['REGULAR', 'PRIORITY'])],
            'status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['required', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'csv', 'pdf'])],
        ];
    }
}
