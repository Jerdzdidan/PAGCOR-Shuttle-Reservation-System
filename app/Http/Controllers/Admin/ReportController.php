<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\AdminReportService;
use App\Services\Reports\ReportCatalog;
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
    /**
     * The report catalogue — the entry point that explains what each report answers.
     */
    public function index(): InertiaResponse
    {
        return Inertia::render('admin/reports/index', [
            'groups' => ReportCatalog::grouped(),
        ]);
    }

    public function show(
        ReportRequest $request,
        AdminReportService $reports,
        string $reportSlug,
    ): InertiaResponse|RedirectResponse {
        if ($redirect = $this->redirectLegacySlug($request, $reportSlug, 'admin.reports.show')) {
            return $redirect;
        }

        return Inertia::render(
            "admin/reports/{$reportSlug}",
            $reports->report($request->validated()),
        );
    }

    public function export(
        ReportRequest $request,
        AdminReportService $reports,
        string $reportSlug,
    ): BinaryFileResponse|Response|RedirectResponse {
        if ($redirect = $this->redirectLegacySlug($request, $reportSlug, 'admin.reports.export')) {
            return $redirect;
        }

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

    /**
     * Sends retired slugs to their replacement, preserving any filters on the URL.
     */
    private function redirectLegacySlug(
        ReportRequest $request,
        string $reportSlug,
        string $routeName,
    ): ?RedirectResponse {
        $slug = ReportCatalog::slugReplacing($reportSlug);

        if ($slug === null) {
            return null;
        }

        // `report` is derived from the slug, so it must not travel with the redirect.
        return redirect()->route(
            $routeName,
            ['reportSlug' => $slug, ...collect($request->query())->except('report')->all()],
            301,
        );
    }
}
