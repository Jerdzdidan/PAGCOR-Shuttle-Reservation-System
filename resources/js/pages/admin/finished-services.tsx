import { ServiceDetailSheet } from '@/components/admin/service-detail-sheet';
import {
    isPaginated,
    occurrenceDriverName,
    occurrencePlateNumber,
    occurrenceRouteName,
    occurrenceRows,
    type PaginatedData,
    type ServiceFilterOption,
    type ServiceOccurrence,
} from '@/components/admin/service-operation-types';
import { ServiceStatusBadge } from '@/components/admin/service-status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BusFront,
    CalendarCheck2,
    CheckCircle2,
    Eye,
    Filter,
    Gauge,
    History,
    RotateCcw,
    Route,
    SearchX,
    UserRound,
    UsersRound,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';

type ServiceView = 'needs_completion' | 'history';

interface FilterRecord {
    view?: ServiceView;
    date_from?: string | null;
    date_to?: string | null;
    route_id?: number | string | null;
    vehicle_id?: number | string | null;
    driver_id?: number | string | null;
    outcome?: string | null;
}

interface OptionRecord {
    id: number;
    name?: string;
    plate_number?: string;
}

interface FinishedServicesPageProps {
    occurrences?: PaginatedData<ServiceOccurrence> | ServiceOccurrence[];
    filters?: FilterRecord;
    options?: {
        routes?: OptionRecord[];
        vehicles?: OptionRecord[];
        drivers?: OptionRecord[];
        outcomes?: ServiceFilterOption[];
    };
    summary?: {
        pending?: number;
        total?: number;
        completed?: number;
        not_operated?: number;
        closed?: number;
    };
    selectedOccurrenceId?: number | string | null;
    selectedOccurrence?: ServiceOccurrence | null;
    occurrence?: number | string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Finished Services', href: '/admin/finished-services' },
];

function displayDate(value: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00+08:00`));
}

function displayTime(value: string): string {
    if (value.includes('T')) {
        return new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(value));
    }

    const [hourValue, minute = '00'] = value.split(':');
    const hour = Number(hourValue);

    return `${hour % 12 || 12}:${minute.slice(0, 2)} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function queryOccurrenceId(pageUrl: string): number | null {
    const queryString = pageUrl.split('?')[1];

    if (!queryString) {
        return null;
    }

    const value = new URLSearchParams(queryString).get('occurrence');
    const parsedValue = value ? Number(value) : Number.NaN;

    return Number.isInteger(parsedValue) && parsedValue > 0 ? parsedValue : null;
}

function outcomeOptions(options?: ServiceFilterOption[]): ServiceFilterOption[] {
    return (
        options ?? [
            { value: 'COMPLETED', label: 'Completed' },
            { value: 'NOT_OPERATED', label: 'Not operated' },
        ]
    );
}

function Pagination({ paginator }: { paginator: PaginatedData<ServiceOccurrence> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <p className="text-muted-foreground text-sm">
                {paginator.from ?? 0}–{paginator.to ?? 0} of {paginator.total}
            </p>
            <div className="flex flex-wrap gap-1.5">
                {paginator.links.map((link, index) =>
                    link.url ? (
                        <Button key={`${link.label}-${index}`} variant={link.active ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={link.url} preserveScroll preserveState>
                                {link.label.replace('&laquo;', '').replace('&raquo;', '').trim() || (index === 0 ? 'Previous' : 'Next')}
                            </Link>
                        </Button>
                    ) : (
                        <Button key={`${link.label}-${index}`} variant="outline" size="sm" disabled>
                            {link.label.replace('&laquo;', '').replace('&raquo;', '').trim() || (index === 0 ? 'Previous' : 'Next')}
                        </Button>
                    ),
                )}
            </div>
        </div>
    );
}

export default function FinishedServicesPage({
    occurrences,
    filters = {},
    options = {},
    summary = {},
    selectedOccurrenceId,
    selectedOccurrence,
    occurrence: occurrenceQueryProp,
}: FinishedServicesPageProps) {
    const page = usePage();
    const currentView: ServiceView = filters.view === 'history' ? 'history' : 'needs_completion';
    const initialOccurrenceId = Number(selectedOccurrenceId ?? occurrenceQueryProp) || queryOccurrenceId(page.url) || null;
    const [detailOpen, setDetailOpen] = useState(initialOccurrenceId !== null);
    const [selectedId, setSelectedId] = useState<number | null>(initialOccurrenceId);
    const [detail, setDetail] = useState<ServiceOccurrence | undefined>(selectedOccurrence ?? undefined);
    const [detailLoading, setDetailLoading] = useState(initialOccurrenceId !== null && !selectedOccurrence);
    const [detailError, setDetailError] = useState<string>();
    const detailRequestId = useRef(0);
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [routeId, setRouteId] = useState(filters.route_id ? String(filters.route_id) : 'ALL');
    const [vehicleId, setVehicleId] = useState(filters.vehicle_id ? String(filters.vehicle_id) : 'ALL');
    const [driverId, setDriverId] = useState(filters.driver_id ? String(filters.driver_id) : 'ALL');
    const [outcome, setOutcome] = useState(filters.outcome || 'ALL');
    const rows = occurrenceRows(occurrences);
    const pendingCount = summary.pending ?? rows.filter((item) => item.status === 'AWAITING_COMPLETION').length;
    const completedCount = summary.completed ?? rows.filter((item) => item.status === 'COMPLETED').length;
    const notOperatedCount = summary.not_operated ?? rows.filter((item) => item.status === 'NOT_OPERATED').length;
    const historyCount = summary.closed ?? completedCount + notOperatedCount;

    usePoll(15000, {
        only: ['occurrences', 'summary', 'pending_completion_count'],
    });

    const loadDetail = useCallback(async (id: number): Promise<void> => {
        const requestId = ++detailRequestId.current;
        setDetailLoading(true);
        setDetailError(undefined);

        try {
            const response = await fetch(`/admin/finished-services/${id}`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(response.status === 404 ? 'This service record no longer exists.' : 'The service record could not be retrieved.');
            }

            const payload = (await response.json()) as
                | ServiceOccurrence
                | { occurrence?: ServiceOccurrence; serviceOccurrence?: ServiceOccurrence; data?: ServiceOccurrence };
            const resolvedDetail =
                'occurrence' in payload
                    ? payload.occurrence
                    : 'serviceOccurrence' in payload
                      ? payload.serviceOccurrence
                      : 'data' in payload
                        ? payload.data
                        : payload;

            if (!resolvedDetail || !('id' in resolvedDetail)) {
                throw new Error('The server returned an incomplete service record.');
            }

            if (requestId === detailRequestId.current) {
                setDetail(resolvedDetail);
            }
        } catch (error) {
            if (requestId === detailRequestId.current) {
                setDetailError(error instanceof Error ? error.message : 'The service record could not be retrieved.');
            }
        } finally {
            if (requestId === detailRequestId.current) {
                setDetailLoading(false);
            }
        }
    }, []);

    useEffect(() => {
        if (initialOccurrenceId) {
            setSelectedId(initialOccurrenceId);
            setDetailOpen(true);

            if (selectedOccurrence?.id === initialOccurrenceId) {
                setDetail(selectedOccurrence);
                setDetailLoading(false);
            } else {
                void loadDetail(initialOccurrenceId);
            }
        }
    }, [initialOccurrenceId, loadDetail, selectedOccurrence]);

    function openDetail(id: number): void {
        setSelectedId(id);
        setDetail(undefined);
        setDetailOpen(true);
        void loadDetail(id);
    }

    function reloadDetail(): void {
        if (selectedId) {
            void loadDetail(selectedId);
        }
    }

    function switchView(nextView: ServiceView): void {
        router.get(
            '/admin/finished-services',
            {
                view: nextView,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                route_id: routeId === 'ALL' ? undefined : routeId,
                vehicle_id: vehicleId === 'ALL' ? undefined : vehicleId,
                driver_id: driverId === 'ALL' ? undefined : driverId,
                outcome: nextView === 'history' && outcome !== 'ALL' ? outcome : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function applyFilters(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        router.get(
            '/admin/finished-services',
            {
                view: currentView,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                route_id: routeId === 'ALL' ? undefined : routeId,
                vehicle_id: vehicleId === 'ALL' ? undefined : vehicleId,
                driver_id: driverId === 'ALL' ? undefined : driverId,
                outcome: currentView === 'history' && outcome !== 'ALL' ? outcome : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function resetFilters(): void {
        setDateFrom('');
        setDateTo('');
        setRouteId('ALL');
        setVehicleId('ALL');
        setDriverId('ALL');
        setOutcome('ALL');
        router.get('/admin/finished-services', { view: currentView }, { preserveScroll: true, replace: true });
    }

    function handleFinalized(): void {
        setDetailOpen(false);
        setSelectedId(null);
        setDetail(undefined);
        router.reload({
            only: ['occurrences', 'summary', 'pending_completion_count'],
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Finished Services" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-brand-blue text-xs font-semibold tracking-[0.16em] uppercase">Operations closeout</p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">Finished Services</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Complete departed trips, record passenger attendance, and preserve the final fleet record.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/admin/reports">
                            View operational reports
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>

                {pendingCount > 0 && currentView === 'needs_completion' && (
                    <Alert className="border-amber-200 bg-amber-50/75 dark:border-amber-900 dark:bg-amber-950/30">
                        <AlertTriangle className="text-amber-700 dark:text-amber-300" />
                        <AlertDescription>
                            {pendingCount} departed {pendingCount === 1 ? 'service needs' : 'services need'} closeout. Empty trips must also be
                            completed or marked not operated.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        icon={AlertTriangle}
                        label="Needs completion"
                        value={pendingCount}
                        className="border-amber-200 dark:border-amber-900"
                        iconClassName="bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                    />
                    <SummaryCard icon={History} label="Closed services" value={historyCount} />
                    <SummaryCard
                        icon={CheckCircle2}
                        label="Completed"
                        value={completedCount}
                        className="border-emerald-200 dark:border-emerald-900"
                        iconClassName="bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                    />
                    <SummaryCard icon={XCircle} label="Not operated" value={notOperatedCount} />
                </div>

                <Card className="overflow-hidden shadow-sm">
                    <div className="border-b p-2">
                        <div className="bg-muted grid max-w-lg grid-cols-2 rounded-xl p-1">
                            <button
                                type="button"
                                onClick={() => switchView('needs_completion')}
                                className={cn(
                                    'flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                                    currentView === 'needs_completion'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <AlertTriangle className="size-4" />
                                Needs Completion
                                {pendingCount > 0 && <Badge className="h-5 min-w-5 justify-center px-1.5">{pendingCount}</Badge>}
                            </button>
                            <button
                                type="button"
                                onClick={() => switchView('history')}
                                className={cn(
                                    'flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                                    currentView === 'history'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <History className="size-4" />
                                History
                            </button>
                        </div>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-4 border-b bg-slate-50/60 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 dark:bg-slate-950/25"
                    >
                        <FilterField label="From">
                            <Input type="date" value={dateFrom} max={dateTo || undefined} onChange={(event) => setDateFrom(event.target.value)} />
                        </FilterField>
                        <FilterField label="To">
                            <Input type="date" value={dateTo} min={dateFrom || undefined} onChange={(event) => setDateTo(event.target.value)} />
                        </FilterField>
                        <FilterSelect
                            label="Route"
                            value={routeId}
                            onValueChange={setRouteId}
                            allLabel="All routes"
                            options={(options.routes ?? []).map((item) => ({ value: String(item.id), label: item.name ?? `Route ${item.id}` }))}
                        />
                        <FilterSelect
                            label="Vehicle"
                            value={vehicleId}
                            onValueChange={setVehicleId}
                            allLabel="All vehicles"
                            options={(options.vehicles ?? []).map((item) => ({
                                value: String(item.id),
                                label: item.plate_number ?? item.name ?? `Vehicle ${item.id}`,
                            }))}
                        />
                        <FilterSelect
                            label="Driver"
                            value={driverId}
                            onValueChange={setDriverId}
                            allLabel="All drivers"
                            options={(options.drivers ?? []).map((item) => ({ value: String(item.id), label: item.name ?? `Driver ${item.id}` }))}
                        />
                        {currentView === 'history' && (
                            <FilterSelect
                                label="Outcome"
                                value={outcome}
                                onValueChange={setOutcome}
                                allLabel="All outcomes"
                                options={outcomeOptions(options.outcomes)}
                            />
                        )}
                        <div className="flex items-end gap-2">
                            <Button type="submit" className="flex-1">
                                <Filter />
                                Apply
                            </Button>
                            <Button type="button" variant="outline" size="icon" onClick={resetFilters} aria-label="Reset filters">
                                <RotateCcw />
                            </Button>
                        </div>
                    </form>

                    <CardHeader className="border-b">
                        <CardTitle className="text-base">
                            {currentView === 'needs_completion' ? 'Departed services awaiting closeout' : 'Service history'}
                        </CardTitle>
                        <CardDescription>
                            {isPaginated(occurrences) ? occurrences.total : rows.length}{' '}
                            {(isPaginated(occurrences) ? occurrences.total : rows.length) === 1 ? 'record' : 'records'} shown
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {rows.length === 0 ? (
                            <EmptyServices view={currentView} />
                        ) : (
                            <>
                                <div className="hidden overflow-x-auto lg:block">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Date and time</TableHead>
                                                <TableHead>Route</TableHead>
                                                <TableHead>Vehicle</TableHead>
                                                <TableHead>Driver</TableHead>
                                                <TableHead>Passengers</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead className="text-right">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {rows.map((service) => (
                                                <TableRow key={service.id}>
                                                    <TableCell>
                                                        <p className="font-medium">{displayDate(service.travel_date)}</p>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            {displayTime(service.departure_time ?? service.scheduled_departure_at)}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        <p className="font-medium">{occurrenceRouteName(service)}</p>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">{service.direction}</p>
                                                    </TableCell>
                                                    <TableCell>{occurrencePlateNumber(service)}</TableCell>
                                                    <TableCell>{occurrenceDriverName(service)}</TableCell>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {service.boarded_count ?? 0} / {service.reserved_count ?? service.reservation_count ?? 0}{' '}
                                                            boarded
                                                        </p>
                                                        {(service.no_show_count ?? 0) > 0 && (
                                                            <p className="mt-0.5 text-xs text-red-700 dark:text-red-300">
                                                                {service.no_show_count} no-show
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <ServiceStatusBadge status={service.status} />
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            type="button"
                                                            variant={service.status === 'AWAITING_COMPLETION' ? 'default' : 'outline'}
                                                            size="sm"
                                                            onClick={() => openDetail(service.id)}
                                                        >
                                                            {service.status === 'AWAITING_COMPLETION' ? <Gauge /> : <Eye />}
                                                            {service.status === 'AWAITING_COMPLETION' ? 'Complete' : 'View'}
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="grid gap-3 p-4 lg:hidden">
                                    {rows.map((service) => (
                                        <ServiceCard key={service.id} service={service} onOpen={() => openDetail(service.id)} />
                                    ))}
                                </div>
                            </>
                        )}
                    </CardContent>

                    {isPaginated(occurrences) && <Pagination paginator={occurrences} />}
                </Card>
            </div>

            <ServiceDetailSheet
                open={detailOpen}
                occurrence={detail}
                loading={detailLoading}
                error={detailError}
                onOpenChange={(open) => {
                    setDetailOpen(open);

                    if (!open) {
                        detailRequestId.current += 1;
                        setSelectedId(null);
                        setDetail(undefined);
                        setDetailError(undefined);
                    }
                }}
                onReloadDetail={reloadDetail}
                onFinalized={handleFinalized}
            />
        </AppLayout>
    );
}

function SummaryCard({
    icon: Icon,
    label,
    value,
    className,
    iconClassName,
}: {
    icon: typeof CalendarCheck2;
    label: string;
    value: number;
    className?: string;
    iconClassName?: string;
}) {
    return (
        <Card className={cn('shadow-sm', className)}>
            <CardContent className="flex items-center gap-4 p-5">
                <span className={cn('bg-primary/10 text-primary rounded-2xl p-3', iconClassName)}>
                    <Icon className="size-5" />
                </span>
                <div>
                    <p className="text-muted-foreground text-xs">{label}</p>
                    <p className="mt-1 text-2xl font-semibold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function FilterField({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-xs">{label}</Label>
            {children}
        </div>
    );
}

function FilterSelect({
    label,
    value,
    onValueChange,
    allLabel,
    options,
}: {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    allLabel: string;
    options: ServiceFilterOption[];
}) {
    return (
        <FilterField label={label}>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="ALL">{allLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FilterField>
    );
}

function ServiceCard({ service, onOpen }: { service: ServiceOccurrence; onOpen: () => void }) {
    return (
        <div className="overflow-hidden rounded-2xl border">
            <div className="flex items-start justify-between gap-3 border-b bg-slate-50/70 p-4 dark:bg-slate-950/35">
                <div>
                    <p className="text-sm font-semibold">{occurrenceRouteName(service)}</p>
                    <p className="text-muted-foreground mt-1 text-xs">
                        {displayDate(service.travel_date)} · {displayTime(service.departure_time ?? service.scheduled_departure_at)}
                    </p>
                </div>
                <ServiceStatusBadge status={service.status} />
            </div>
            <div className="grid grid-cols-2 gap-3 p-4 text-sm">
                <MobileMetric icon={BusFront} label="Vehicle" value={occurrencePlateNumber(service)} />
                <MobileMetric icon={UserRound} label="Driver" value={occurrenceDriverName(service)} />
                <MobileMetric icon={Route} label="Direction" value={service.direction} />
                <MobileMetric
                    icon={UsersRound}
                    label="Attendance"
                    value={`${service.boarded_count ?? 0} / ${service.reserved_count ?? service.reservation_count ?? 0} boarded`}
                />
            </div>
            <div className="border-t p-3">
                <Button type="button" className="w-full" variant={service.status === 'AWAITING_COMPLETION' ? 'default' : 'outline'} onClick={onOpen}>
                    {service.status === 'AWAITING_COMPLETION' ? <Gauge /> : <Eye />}
                    {service.status === 'AWAITING_COMPLETION' ? 'Complete service' : 'View service record'}
                </Button>
            </div>
        </div>
    );
}

function MobileMetric({ icon: Icon, label, value }: { icon: typeof BusFront; label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                <Icon className="size-3.5" />
                {label}
            </p>
            <p className="mt-1 truncate font-medium">{value}</p>
        </div>
    );
}

function EmptyServices({ view }: { view: ServiceView }) {
    return (
        <div className="px-5 py-16 text-center">
            <span className="bg-muted text-muted-foreground mx-auto flex size-14 items-center justify-center rounded-2xl">
                {view === 'needs_completion' ? <CalendarCheck2 className="size-6" /> : <SearchX className="size-6" />}
            </span>
            <h2 className="mt-4 font-semibold">{view === 'needs_completion' ? 'All departed services are closed' : 'No service history found'}</h2>
            <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                {view === 'needs_completion'
                    ? 'There are no services waiting for attendance and odometer completion.'
                    : 'Try changing the date, route, vehicle, driver, or outcome filters.'}
            </p>
        </div>
    );
}
