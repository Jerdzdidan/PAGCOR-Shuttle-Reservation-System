<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        AdminDashboardService $dashboard,
    ): Response {
        return Inertia::render('dashboard', [
            'dailySummary' => fn (): array => $dashboard->dailySummary(),
        ]);
    }
}
