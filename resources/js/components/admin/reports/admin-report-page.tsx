import { ReportDataTable, type ReportColumn, type ReportRows } from '@/components/admin/reports/report-data-table';
import { ReportKpiCards } from '@/components/admin/reports/report-kpi-cards';
import { ReportTrendChart } from '@/components/admin/reports/report-trend-chart';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { useState } from 'react';

type ReportFilters = {
    report: string;
    date_from: string;
    date_to: string;
    route_id: number | null;
    vehicle_id: number | null;
    driver_id: number | null;
    department: string | null;
    priority_status: string | null;
    status: string | null;
    per_page: number;
};

type FilterOption = {
    id: number;
    label: string;
};

export interface AdminReportPageProps {
    title: string;
    description: string;
    filters: ReportFilters;
    filterOptions: {
        routes: FilterOption[];
        vehicles: FilterOption[];
        drivers: FilterOption[];
        departments: string[];
    };
    statusOptions: { value: string; label: string }[];
    kpis: { label: string; value: string | number }[];
    chart: { label: string; data: { label: string; value: number }[] };
    columns: ReportColumn[];
    rows: ReportRows;
}

interface AdminReportPageComponentProps extends AdminReportPageProps {
    reportUrl: string;
}

const none = 'ALL';

export function AdminReportPage({
    title,
    description,
    filters,
    filterOptions,
    statusOptions,
    kpis,
    chart,
    columns,
    rows,
    reportUrl,
}: AdminReportPageComponentProps) {
    const [form, setForm] = useState(filters);
    const showsOperationalFilters = form.report !== 'login_activity';
    const showsStatusFilter = !['fleet_utilization', 'route_schedule_demand', 'driver_utilization', 'login_activity'].includes(form.report);
    const showsDepartmentFilter = ['shuttle_attendance', 'login_activity'].includes(form.report);
    const showsPriorityFilter = ['shuttle_attendance', 'reservation_waitlist_activity', 'login_activity'].includes(form.report);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '/admin/reports' },
        { title, href: reportUrl },
    ];

    function visit(next: ReportFilters & { page?: number }): void {
        router.get(
            reportUrl,
            {
                date_from: next.date_from,
                date_to: next.date_to,
                route_id: next.route_id,
                vehicle_id: next.vehicle_id,
                driver_id: next.driver_id,
                department: next.department,
                priority_status: next.priority_status,
                status: next.status,
                per_page: next.per_page,
                page: next.page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function selectId(key: 'route_id' | 'vehicle_id' | 'driver_id', value: string): void {
        setForm((current) => ({ ...current, [key]: value === none ? null : Number(value) }));
    }

    function exportReport(format: 'xlsx' | 'csv' | 'pdf'): void {
        const query = new URLSearchParams(
            Object.entries({
                date_from: form.date_from,
                date_to: form.date_to,
                route_id: form.route_id,
                vehicle_id: form.vehicle_id,
                driver_id: form.driver_id,
                department: form.department,
                priority_status: form.priority_status,
                status: form.status,
                format,
            })
                .filter(([, value]) => value !== null)
                .map(([key, value]) => [key, String(value)]),
        );

        window.location.assign(`${reportUrl}/export?${query.toString()}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="report-print-surface flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <p className="text-brand-blue text-xs font-semibold tracking-[0.16em] uppercase">Reports</p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground mt-1">{description}</p>
                    </div>
                    <div className="flex flex-wrap gap-2 print:hidden">
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer /> Print
                        </Button>
                        <Button variant="outline" onClick={() => exportReport('csv')}>
                            <Download /> CSV
                        </Button>
                        <Button variant="outline" onClick={() => exportReport('xlsx')}>
                            <Download /> Excel
                        </Button>
                        <Button onClick={() => exportReport('pdf')}>
                            <Download /> PDF
                        </Button>
                    </div>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ ...form, page: 1 });
                    }}
                    className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 xl:grid-cols-4 print:hidden"
                >
                    <div className="space-y-2">
                        <Label htmlFor="date-from">From</Label>
                        <Input
                            id="date-from"
                            type="date"
                            value={form.date_from}
                            max={form.date_to || undefined}
                            onChange={(event) => setForm((current) => ({ ...current, date_from: event.target.value }))}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="date-to">To</Label>
                        <Input
                            id="date-to"
                            type="date"
                            value={form.date_to}
                            min={form.date_from || undefined}
                            onChange={(event) => setForm((current) => ({ ...current, date_to: event.target.value }))}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Rows per page</Label>
                        <Select
                            value={String(form.per_page)}
                            onValueChange={(value) => setForm((current) => ({ ...current, per_page: Number(value) }))}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="25">25 rows</SelectItem>
                                <SelectItem value="50">50 rows</SelectItem>
                                <SelectItem value="100">100 rows</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label aria-hidden="true" className="invisible">
                            Filter actions
                        </Label>
                        <Button className="w-full" type="submit">
                            Apply filters
                        </Button>
                    </div>

                    {showsOperationalFilters && (
                        <>
                            <div className="space-y-2">
                                <Label>Route</Label>
                                <Select value={form.route_id?.toString() ?? none} onValueChange={(value) => selectId('route_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={none}>All routes</SelectItem>
                                        {filterOptions.routes.map((option) => (
                                            <SelectItem key={option.id} value={String(option.id)}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Vehicle</Label>
                                <Select value={form.vehicle_id?.toString() ?? none} onValueChange={(value) => selectId('vehicle_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={none}>All vehicles</SelectItem>
                                        {filterOptions.vehicles.map((option) => (
                                            <SelectItem key={option.id} value={String(option.id)}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Driver</Label>
                                <Select value={form.driver_id?.toString() ?? none} onValueChange={(value) => selectId('driver_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={none}>All drivers</SelectItem>
                                        {filterOptions.drivers.map((option) => (
                                            <SelectItem key={option.id} value={String(option.id)}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </>
                    )}

                    {showsStatusFilter && (
                        <div className="space-y-2">
                            <Label>Status or activity type</Label>
                            <Select
                                value={form.status ?? none}
                                onValueChange={(value) => setForm((current) => ({ ...current, status: value === none ? null : value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={none}>All statuses</SelectItem>
                                    {statusOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {showsDepartmentFilter && (
                        <div className="space-y-2">
                            <Label>Department</Label>
                            <Select
                                value={form.department ?? none}
                                onValueChange={(value) => setForm((current) => ({ ...current, department: value === none ? null : value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={none}>All departments</SelectItem>
                                    {filterOptions.departments.map((department) => (
                                        <SelectItem key={department} value={department}>
                                            {department}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {showsPriorityFilter && (
                        <div className="space-y-2">
                            <Label>Priority tier</Label>
                            <Select
                                value={form.priority_status ?? none}
                                onValueChange={(value) => setForm((current) => ({ ...current, priority_status: value === none ? null : value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={none}>All priority tiers</SelectItem>
                                    <SelectItem value="REGULAR">Regular</SelectItem>
                                    <SelectItem value="PRIORITY">Priority</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </form>

                <ReportKpiCards kpis={kpis} />
                <ReportTrendChart chart={chart} />

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">Detailed records</h2>
                        <p className="text-muted-foreground text-sm">Filtered results are paginated on the server.</p>
                    </div>
                    <ReportDataTable columns={columns} rows={rows} onPageChange={(page) => visit({ ...filters, page })} />
                </section>
            </div>
        </AppLayout>
    );
}
