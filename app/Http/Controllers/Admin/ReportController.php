<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\AdminReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('admin.reports.show', [
            'reportSlug' => AdminReportService::defaultReportSlug(),
        ]);
    }

    public function show(
        ReportRequest $request,
        AdminReportService $reports,
        string $reportSlug,
    ): InertiaResponse {
        return Inertia::render(
            "admin/reports/{$reportSlug}",
            $reports->report($request->validated()),
        );
    }

    public function export(
        ReportRequest $request,
        AdminReportService $reports,
        string $reportSlug,
    ): BinaryFileResponse|Response {
        $filters = $request->validated();
        $format = $filters['format'] ?? 'xlsx';
        $report = $reports->export($filters);
        $filename = str($filters['report'])->slug()->append('-'.$filters['date_from'].'-to-'.$filters['date_to']);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', $report)
                ->setPaper('a4', 'landscape')
                ->download($filename.'.pdf');
        }

        return ExcelFacade::download(
            new AdminReportExport($report['headings'], $report['rows']),
            $filename.'.'.$format,
            $format === 'csv' ? Excel::CSV : Excel::XLSX,
        );
    }
}
