import { ReportDataTable, type ReportColumn, type ReportRows } from '@/components/admin/reports/report-data-table';
import { ReportKpiCards, type ReportKpi } from '@/components/admin/reports/report-kpi-cards';
import { ReportSwitcher, type SwitcherReport } from '@/components/admin/reports/report-switcher';
import { ReportTrendChart, type ReportChart } from '@/components/admin/reports/report-trend-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CalendarRange, Download, LoaderCircle, Printer, RotateCcw, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type ReportFilters = {
    report: string;
    date_from: string;
    date_to: string;
    route_id: number | null;
    vehicle_id: number | null;
    driver_id: number | null;
    schedule_id: number | null;
    department: string | null;
    priority_status: string | null;
    status: string | null;
    sort: string | null;
    direction: string | null;
    per_page: number;
};

type FilterOption = {
    id: number;
    label: string;
};

/** Which optional filter controls this report supports, supplied by the backend catalogue. */
type AvailableFilter = 'schedule' | 'route' | 'vehicle' | 'driver' | 'status' | 'department' | 'priority';

export interface AdminReportPageProps {
    report: {
        key: string;
        slug: string;
        title: string;
        answers: string;
        description: string;
        category_label: string;
        default_sort: { key: string; direction: string };
    };
    title: string;
    description: string;
    filters: ReportFilters;
    filterOptions: {
        routes: FilterOption[];
        vehicles: FilterOption[];
        drivers: FilterOption[];
        schedules: FilterOption[];
        departments: string[];
    };
    statusOptions: { value: string; label: string }[];
    availableFilters: AvailableFilter[];
    switcher: SwitcherReport[];
    period: string;
    kpis: ReportKpi[];
    chart: ReportChart;
    columns: ReportColumn[];
    rows: ReportRows;
}

interface AdminReportPageComponentProps extends AdminReportPageProps {
    reportUrl: string;
}

const none = 'ALL';

function queryFrom(filters: ReportFilters): Record<string, string> {
    return Object.entries({
        date_from: filters.date_from,
        date_to: filters.date_to,
        route_id: filters.route_id,
        vehicle_id: filters.vehicle_id,
        driver_id: filters.driver_id,
        schedule_id: filters.schedule_id,
        department: filters.department,
        priority_status: filters.priority_status,
        status: filters.status,
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
    })
        .filter(([, value]) => value !== null && value !== '')
        .reduce<Record<string, string>>((carry, [key, value]) => ({ ...carry, [key]: String(value) }), {});
}

export function AdminReportPage({
    report,
    title,
    description,
    filters,
    filterOptions,
    statusOptions,
    availableFilters,
    switcher,
    period,
    kpis,
    chart,
    columns,
    rows,
    reportUrl,
}: AdminReportPageComponentProps) {
    const [form, setForm] = useState(filters);
    const [busy, setBusy] = useState(false);
    const supports = (filter: AvailableFilter): boolean => availableFilters.includes(filter);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports', href: '/admin/reports' },
        { title, href: reportUrl },
    ];

    /**
     * Navigation always starts from the filters the server actually applied, so paging or
     * sorting can never silently pick up half-edited form state.
     */
    function visit(next: Partial<ReportFilters> & { page?: number }, base: ReportFilters = filters): void {
        const merged = { ...base, ...next };

        router.get(
            reportUrl,
            { ...queryFrom(merged), ...(next.page ? { page: String(next.page) } : {}) },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    }

    function applyFilters(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        visit({}, form);
    }

    function resetFilters(): void {
        router.get(reportUrl, {}, { preserveScroll: true, replace: true });
    }

    function changeSort(key: string): void {
        const direction = filters.sort === key && filters.direction === 'desc' ? 'asc' : 'desc';
        visit({ sort: key, direction });
    }

    function clearFilter(key: keyof ReportFilters): void {
        const next = { ...filters, [key]: null } as ReportFilters;
        setForm(next);
        visit({ [key]: null });
    }

    function selectId(key: 'route_id' | 'vehicle_id' | 'driver_id' | 'schedule_id', value: string): void {
        setForm((current) => ({ ...current, [key]: value === none ? null : Number(value) }));
    }

    /** Export always mirrors what the server rendered, not unapplied form edits. */
    function exportReport(format: 'xlsx' | 'csv' | 'pdf'): void {
        const query = new URLSearchParams({ ...queryFrom(filters), format });
        window.location.assign(`${reportUrl}/export?${query.toString()}`);
    }

    const activeChips = [
        filters.schedule_id && {
            key: 'schedule_id' as const,
            label: `Schedule: ${filterOptions.schedules.find((item) => item.id === filters.schedule_id)?.label ?? filters.schedule_id}`,
        },
        filters.route_id && {
            key: 'route_id' as const,
            label: `Route: ${filterOptions.routes.find((item) => item.id === filters.route_id)?.label ?? filters.route_id}`,
        },
        filters.vehicle_id && {
            key: 'vehicle_id' as const,
            label: `Vehicle: ${filterOptions.vehicles.find((item) => item.id === filters.vehicle_id)?.label ?? filters.vehicle_id}`,
        },
        filters.driver_id && {
            key: 'driver_id' as const,
            label: `Driver: ${filterOptions.drivers.find((item) => item.id === filters.driver_id)?.label ?? filters.driver_id}`,
        },
        filters.status && {
            key: 'status' as const,
            label: `Status: ${statusOptions.find((item) => item.value === filters.status)?.label ?? filters.status}`,
        },
        filters.department && { key: 'department' as const, label: `Department: ${filters.department}` },
        filters.priority_status && { key: 'priority_status' as const, label: `Priority: ${filters.priority_status}` },
    ].filter(Boolean) as { key: keyof ReportFilters; label: string }[];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="report-print-surface flex min-w-0 flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div className="min-w-0">
                        <p className="text-brand-blue text-xs font-semibold tracking-[0.16em] uppercase">{report.category_label}</p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground mt-1 max-w-2xl">{description}</p>
                        <p className="text-muted-foreground mt-2 flex items-center gap-1.5 text-sm font-medium">
                            <CalendarRange className="size-4" />
                            {period}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 print:hidden">
                        <ReportSwitcher reports={switcher} currentKey={report.key} />
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer /> Print
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button>
                                    <Download /> Export
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => exportReport('csv')}>CSV (.csv)</DropdownMenuItem>
                                <DropdownMenuItem onClick={() => exportReport('xlsx')}>Excel (.xlsx)</DropdownMenuItem>
                                <DropdownMenuItem onClick={() => exportReport('pdf')}>PDF (.pdf)</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <Card className="print:hidden">
                    <CardContent className="p-4">
                        <form onSubmit={applyFilters} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div className="space-y-2">
                                <Label htmlFor="date-from">From</Label>
                                <Input
                                    id="date-from"
                                    type="date"
                                    value={form.date_from}
                                    onChange={(event) => setForm({ ...form, date_from: event.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="date-to">To</Label>
                                <Input
                                    id="date-to"
                                    type="date"
                                    value={form.date_to}
                                    onChange={(event) => setForm({ ...form, date_to: event.target.value })}
                                />
                            </div>

                            {supports('schedule') && (
                                <div className="space-y-2">
                                    <Label>Schedule</Label>
                                    <Select
                                        value={form.schedule_id ? String(form.schedule_id) : none}
                                        onValueChange={(value) => selectId('schedule_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All schedules" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={none}>All schedules</SelectItem>
                                            {filterOptions.schedules.map((option) => (
                                                <SelectItem key={option.id} value={String(option.id)}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {supports('route') && (
                                <div className="space-y-2">
                                    <Label>Route</Label>
                                    <Select
                                        value={form.route_id ? String(form.route_id) : none}
                                        onValueChange={(value) => selectId('route_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All routes" />
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
                            )}

                            {supports('vehicle') && (
                                <div className="space-y-2">
                                    <Label>Vehicle</Label>
                                    <Select
                                        value={form.vehicle_id ? String(form.vehicle_id) : none}
                                        onValueChange={(value) => selectId('vehicle_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All vehicles" />
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
                            )}

                            {supports('driver') && (
                                <div className="space-y-2">
                                    <Label>Driver</Label>
                                    <Select
                                        value={form.driver_id ? String(form.driver_id) : none}
                                        onValueChange={(value) => selectId('driver_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All drivers" />
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
                            )}

                            {supports('status') && statusOptions.length > 0 && (
                                <div className="space-y-2">
                                    <Label>{report.key === 'booking_activity' ? 'Activity type' : 'Status'}</Label>
                                    <Select
                                        value={form.status ?? none}
                                        onValueChange={(value) => setForm({ ...form, status: value === none ? null : value })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={none}>All</SelectItem>
                                            {statusOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {supports('department') && (
                                <div className="space-y-2">
                                    <Label>Department</Label>
                                    <Select
                                        value={form.department ?? none}
                                        onValueChange={(value) => setForm({ ...form, department: value === none ? null : value })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All departments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={none}>All departments</SelectItem>
                                            {filterOptions.departments.map((option) => (
                                                <SelectItem key={option} value={option}>
                                                    {option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {supports('priority') && (
                                <div className="space-y-2">
                                    <Label>Priority tier</Label>
                                    <Select
                                        value={form.priority_status ?? none}
                                        onValueChange={(value) => setForm({ ...form, priority_status: value === none ? null : value })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All tiers" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={none}>All tiers</SelectItem>
                                            <SelectItem value="REGULAR">Regular</SelectItem>
                                            <SelectItem value="PRIORITY">Priority</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label>Rows per page</Label>
                                <Select value={String(form.per_page)} onValueChange={(value) => setForm({ ...form, per_page: Number(value) })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[25, 50, 100].map((size) => (
                                            <SelectItem key={size} value={String(size)}>
                                                {size} rows
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-end gap-2 sm:col-span-2 xl:col-span-4">
                                <Button type="submit" disabled={busy}>
                                    {busy && <LoaderCircle className="animate-spin" />}
                                    Apply filters
                                </Button>
                                <Button type="button" variant="outline" onClick={resetFilters} disabled={busy}>
                                    <RotateCcw />
                                    Reset
                                </Button>
                            </div>
                        </form>

                        {activeChips.length > 0 && (
                            <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-4">
                                <span className="text-muted-foreground text-xs font-medium">Active filters</span>
                                {activeChips.map((chip) => (
                                    <Badge key={chip.key} variant="secondary" className="gap-1 font-normal">
                                        <span className="max-w-56 truncate">{chip.label}</span>
                                        <button type="button" onClick={() => clearFilter(chip.key)} aria-label={`Clear ${chip.label}`}>
                                            <X className="size-3 opacity-60 hover:opacity-100" />
                                        </button>
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <ReportKpiCards kpis={kpis} />
                <ReportTrendChart chart={chart} />

                <div className="min-w-0 space-y-3">
                    <h2 className="text-lg font-semibold">Detailed records</h2>
                    <ReportDataTable
                        columns={columns}
                        rows={rows}
                        sort={{
                            key: filters.sort ?? report.default_sort.key,
                            direction: (filters.direction ?? report.default_sort.direction) === 'asc' ? 'asc' : 'desc',
                        }}
                        onPageChange={(page) => visit({ page })}
                        onSortChange={changeSort}
                        onReset={resetFilters}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
