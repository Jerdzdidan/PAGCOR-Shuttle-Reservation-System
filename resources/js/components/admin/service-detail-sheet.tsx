import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BusFront,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    Gauge,
    History,
    LoaderCircle,
    MapPin,
    PencilLine,
    RotateCcw,
    UserRound,
    UsersRound,
    XCircle,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { ServiceManifest } from './service-manifest';
import {
    manifestEmployeeName,
    manifestEntries,
    manifestStatus,
    occurrenceDriverName,
    occurrencePlateNumber,
    occurrenceRouteName,
    type ServiceOccurrence,
} from './service-operation-types';
import { ServiceStatusBadge } from './service-status-badge';

interface ServiceDetailSheetProps {
    open: boolean;
    occurrence?: ServiceOccurrence;
    loading?: boolean;
    error?: string;
    onOpenChange: (open: boolean) => void;
    onReloadDetail: () => void;
    onFinalized: () => void;
}

type CompleteFormData = {
    opening_odometer_km: string;
    closing_odometer_km: string;
    actual_departure_at: string;
    actual_arrival_at: string;
    operational_notes: string;
    incident_notes: string;
};

type NotOperatedFormData = {
    not_operated_reason: string;
    operational_notes: string;
    incident_notes: string;
};

type AttendanceCorrection = {
    reservation_id: number;
    status: 'BOARDED' | 'NO_SHOW';
    boarded_at: string | null;
};

type CorrectionFormData = CompleteFormData & {
    not_operated_reason: string;
    reason: string;
    attendance: AttendanceCorrection[];
};

function displayDate(value: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00+08:00`));
}

function displayDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function displayTime(value: string): string {
    const parsedDate = value.includes('T') ? new Date(value) : null;

    if (parsedDate && !Number.isNaN(parsedDate.getTime())) {
        return new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            hour: 'numeric',
            minute: '2-digit',
        }).format(parsedDate);
    }

    const [hourValue, minuteValue = '00'] = value.split(':');
    const hour = Number(hourValue);

    return `${hour % 12 || 12}:${minuteValue.slice(0, 2)} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function datetimeLocalValue(value?: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value.slice(0, 16);
    }

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);
    const part = (type: Intl.DateTimeFormatPartTypes): string => parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

function numberValue(value?: number | string | null): string {
    return value === null || value === undefined ? '' : String(value);
}

function manifestReservationId(entry: ReturnType<typeof manifestEntries>[number]): number | null {
    return entry.reservation_id ?? entry.shuttle_reservation_id ?? entry.reservation?.id ?? entry.id ?? null;
}

function correctionAttendanceItems(occurrence: ServiceOccurrence): AttendanceCorrection[] {
    return manifestEntries(occurrence).flatMap((entry) => {
        const reservationId = manifestReservationId(entry);
        const status = manifestStatus(entry);

        if (reservationId === null || (status !== 'BOARDED' && status !== 'NO_SHOW')) {
            return [];
        }

        return [
            {
                reservation_id: reservationId,
                status,
                boarded_at: status === 'BOARDED' ? (entry.boarded_at ?? null) : null,
            },
        ];
    });
}

function firstFormError(errors: object): string | undefined {
    return Object.values(errors).find((value): value is string => typeof value === 'string');
}

function DetailMetric({ icon: Icon, label, value }: { icon: typeof CalendarDays; label: string; value: string | number }) {
    return (
        <div className="bg-muted/25 flex items-center gap-3 rounded-xl border p-3">
            <span className="bg-background text-primary rounded-lg border p-2">
                <Icon className="size-4" />
            </span>
            <div className="min-w-0">
                <p className="text-muted-foreground text-xs">{label}</p>
                <p className="truncate text-sm font-semibold">{value}</p>
            </div>
        </div>
    );
}

export function ServiceDetailSheet({ open, occurrence, loading = false, error, onOpenChange, onReloadDetail, onFinalized }: ServiceDetailSheetProps) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl lg:max-w-5xl">
                <SheetHeader className="shrink-0 border-b px-5 py-5 pr-14 sm:px-7">
                    <div className="flex flex-wrap items-center gap-2">
                        <SheetTitle>Service operations</SheetTitle>
                        {occurrence && <ServiceStatusBadge status={occurrence.status} />}
                    </div>
                    <SheetDescription>Record reserved-passenger attendance and complete the operational trip record.</SheetDescription>
                </SheetHeader>

                <div className="[&::-webkit-scrollbar-thumb]:bg-border hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/60 min-h-0 flex-1 overflow-y-auto overscroll-contain [scrollbar-color:var(--color-border)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:transition-colors [&::-webkit-scrollbar-track]:bg-transparent">
                    {loading ? (
                        <div className="flex min-h-80 items-center justify-center gap-3">
                            <LoaderCircle className="text-primary size-6 animate-spin" />
                            <p className="text-muted-foreground text-sm">Loading service record…</p>
                        </div>
                    ) : error ? (
                        <div className="p-6">
                            <Alert variant="destructive">
                                <AlertTriangle />
                                <AlertTitle>Service details could not be loaded</AlertTitle>
                                <AlertDescription className="mt-2 flex flex-col items-start gap-3">
                                    <span>{error}</span>
                                    <Button type="button" variant="outline" size="sm" onClick={onReloadDetail}>
                                        <RotateCcw />
                                        Try again
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        </div>
                    ) : occurrence ? (
                        <ServiceDetailPanel
                            key={`${occurrence.id}-${occurrence.status}-${occurrence.finalized_at ?? 'open'}-${occurrence.corrections?.[0]?.id ?? 'uncorrected'}`}
                            occurrence={occurrence}
                            onReloadDetail={onReloadDetail}
                            onFinalized={onFinalized}
                        />
                    ) : null}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ServiceDetailPanel({
    occurrence,
    onReloadDetail,
    onFinalized,
}: {
    occurrence: ServiceOccurrence;
    onReloadDetail: () => void;
    onFinalized: () => void;
}) {
    const isFinalized = occurrence.status === 'COMPLETED' || occurrence.status === 'NOT_OPERATED';
    const [activeSection, setActiveSection] = useState<'attendance' | 'closeout'>(isFinalized ? 'closeout' : 'attendance');
    const entries = manifestEntries(occurrence);
    const boardedCount = entries.filter((entry) => manifestStatus(entry) === 'BOARDED').length;
    const departureValue = occurrence.departure_time ?? occurrence.scheduled_departure_at;
    const routeOrigin = occurrence.route_origin ?? occurrence.origin ?? occurrence.route?.origin;
    const routeDestination = occurrence.route_destination ?? occurrence.destination ?? occurrence.route?.destination;

    return (
        <div className="space-y-6 p-5 sm:p-7">
            <section className="overflow-hidden rounded-2xl border">
                <div className="bg-brand-navy relative overflow-hidden px-5 py-5 text-white sm:px-6">
                    <div className="bg-brand-blue/35 absolute -top-20 right-0 size-44 rounded-full blur-3xl" />
                    <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.16em] text-blue-200 uppercase">{occurrence.direction}</p>
                            <h2 className="mt-1 text-xl font-semibold">{occurrenceRouteName(occurrence)}</h2>
                            {(routeOrigin || routeDestination) && (
                                <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-blue-100/70">
                                    <MapPin className="size-4" />
                                    {routeOrigin ?? 'Origin'}
                                    <ArrowRight className="size-3.5" />
                                    {routeDestination ?? 'Destination'}
                                </p>
                            )}
                        </div>
                        <div className="rounded-xl border border-white/10 bg-white/8 px-4 py-3 sm:text-right">
                            <p className="text-xs text-blue-100/60">Scheduled departure</p>
                            <p className="mt-1 text-lg font-semibold">{displayTime(departureValue)}</p>
                            <p className="text-xs text-blue-100/60">{displayDate(occurrence.travel_date)}</p>
                        </div>
                    </div>
                </div>
                <div className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <DetailMetric icon={BusFront} label="Vehicle" value={occurrencePlateNumber(occurrence)} />
                    <DetailMetric icon={UserRound} label="Driver" value={occurrenceDriverName(occurrence)} />
                    <DetailMetric icon={UsersRound} label="Capacity" value={`${occurrence.effective_capacity} seats`} />
                    <DetailMetric icon={ClipboardCheck} label="Attendance" value={`${boardedCount} of ${entries.length} boarded`} />
                </div>
            </section>

            <div className="bg-muted grid grid-cols-2 rounded-xl p-1">
                <button
                    type="button"
                    onClick={() => setActiveSection('attendance')}
                    className={cn(
                        'flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                        activeSection === 'attendance' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <UsersRound className="size-4" />
                    Attendance
                </button>
                <button
                    type="button"
                    onClick={() => setActiveSection('closeout')}
                    className={cn(
                        'flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                        activeSection === 'closeout' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Gauge className="size-4" />
                    {isFinalized ? 'Service record' : 'Closeout'}
                </button>
            </div>

            {activeSection === 'attendance' ? (
                <ServiceManifest occurrence={occurrence} onRefresh={onReloadDetail} />
            ) : isFinalized ? (
                <FinalizedServiceRecord occurrence={occurrence} onReloadDetail={onReloadDetail} onFinalized={onFinalized} />
            ) : (
                <ServiceCloseout occurrence={occurrence} onFinalized={onFinalized} />
            )}
        </div>
    );
}

function ServiceCloseout({ occurrence, onFinalized }: { occurrence: ServiceOccurrence; onFinalized: () => void }) {
    const [outcome, setOutcome] = useState<'COMPLETED' | 'NOT_OPERATED'>('COMPLETED');
    const [confirmed, setConfirmed] = useState(false);
    const completeForm = useForm<CompleteFormData>({
        opening_odometer_km: numberValue(
            occurrence.opening_odometer_km ?? occurrence.suggested_opening_odometer_km ?? occurrence.opening_odometer_prefill,
        ),
        closing_odometer_km: numberValue(occurrence.closing_odometer_km),
        actual_departure_at: datetimeLocalValue(occurrence.actual_departure_at),
        actual_arrival_at: datetimeLocalValue(occurrence.actual_arrival_at),
        operational_notes: occurrence.operational_notes ?? '',
        incident_notes: occurrence.incident_notes ?? '',
    });
    const notOperatedForm = useForm<NotOperatedFormData>({
        not_operated_reason: occurrence.not_operated_reason ?? '',
        operational_notes: occurrence.operational_notes ?? '',
        incident_notes: occurrence.incident_notes ?? '',
    });
    const hasOdometerReadings = completeForm.data.opening_odometer_km.trim() !== '' && completeForm.data.closing_odometer_km.trim() !== '';
    const openingOdometer = hasOdometerReadings ? Number(completeForm.data.opening_odometer_km) : Number.NaN;
    const closingOdometer = hasOdometerReadings ? Number(completeForm.data.closing_odometer_km) : Number.NaN;
    const calculatedDistance =
        Number.isFinite(openingOdometer) && Number.isFinite(closingOdometer) && closingOdometer >= openingOdometer
            ? (closingOdometer - openingOdometer).toFixed(1)
            : null;

    function submitCompleted(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        completeForm.post(`/admin/finished-services/${occurrence.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Service completed and attendance finalized.');
                onFinalized();
            },
            onError: (errors) => {
                toast.error(firstFormError(errors) ?? 'The service could not be completed.');
            },
        });
    }

    function submitNotOperated(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        notOperatedForm.post(`/admin/finished-services/${occurrence.id}/not-operated`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Service recorded as not operated.');
                onFinalized();
            },
            onError: (errors) => {
                toast.error(firstFormError(errors) ?? 'The service could not be marked as not operated.');
            },
        });
    }

    return (
        <div className="space-y-5">
            <Alert className="border-blue-200 bg-blue-50/70 dark:border-blue-900 dark:bg-blue-950/30">
                <ClipboardCheck className="text-blue-700 dark:text-blue-300" />
                <AlertTitle>Finalize this service</AlertTitle>
                <AlertDescription>
                    Completion locks the manifest and converts every unmarked reservation to a no-show. A not-operated service does not create
                    no-shows.
                </AlertDescription>
            </Alert>

            <div className="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    onClick={() => {
                        setOutcome('COMPLETED');
                        setConfirmed(false);
                    }}
                    className={cn(
                        'rounded-2xl border p-4 text-left transition-all',
                        outcome === 'COMPLETED'
                            ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:ring-emerald-950'
                            : 'hover:bg-muted/40',
                    )}
                >
                    <CheckCircle2 className="size-5 text-emerald-600" />
                    <p className="mt-3 font-semibold">Completed</p>
                    <p className="text-muted-foreground mt-1 text-xs leading-5">The shuttle operated. Record odometers and final attendance.</p>
                </button>
                <button
                    type="button"
                    onClick={() => {
                        setOutcome('NOT_OPERATED');
                        setConfirmed(false);
                    }}
                    className={cn(
                        'rounded-2xl border p-4 text-left transition-all',
                        outcome === 'NOT_OPERATED'
                            ? 'border-slate-400 bg-slate-100 ring-2 ring-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:ring-slate-900'
                            : 'hover:bg-muted/40',
                    )}
                >
                    <XCircle className="size-5 text-slate-600" />
                    <p className="mt-3 font-semibold">Not operated</p>
                    <p className="text-muted-foreground mt-1 text-xs leading-5">The planned service did not run. A reason is required.</p>
                </button>
            </div>

            {outcome === 'COMPLETED' ? (
                <form onSubmit={submitCompleted} className="space-y-5">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Gauge className="text-primary size-5" />
                                Odometer and distance
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="opening-odometer">Opening odometer (km)</Label>
                                <Input
                                    id="opening-odometer"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value={completeForm.data.opening_odometer_km}
                                    onChange={(event) => completeForm.setData('opening_odometer_km', event.target.value)}
                                    aria-invalid={Boolean(completeForm.errors.opening_odometer_km)}
                                />
                                {completeForm.errors.opening_odometer_km && (
                                    <p className="text-destructive text-xs">{completeForm.errors.opening_odometer_km}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="closing-odometer">Closing odometer (km)</Label>
                                <Input
                                    id="closing-odometer"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value={completeForm.data.closing_odometer_km}
                                    onChange={(event) => completeForm.setData('closing_odometer_km', event.target.value)}
                                    aria-invalid={Boolean(completeForm.errors.closing_odometer_km)}
                                />
                                {completeForm.errors.closing_odometer_km && (
                                    <p className="text-destructive text-xs">{completeForm.errors.closing_odometer_km}</p>
                                )}
                            </div>
                            <div className="bg-muted/35 rounded-xl border p-4 sm:col-span-2">
                                <p className="text-muted-foreground text-xs">Calculated trip distance</p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {calculatedDistance ? `${calculatedDistance} km` : 'Enter valid readings'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Clock3 className="text-primary size-5" />
                                Actual times and notes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="actual-departure">Actual departure (optional)</Label>
                                <Input
                                    id="actual-departure"
                                    type="datetime-local"
                                    value={completeForm.data.actual_departure_at}
                                    onChange={(event) => completeForm.setData('actual_departure_at', event.target.value)}
                                />
                                {completeForm.errors.actual_departure_at && (
                                    <p className="text-destructive text-xs">{completeForm.errors.actual_departure_at}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="actual-arrival">Actual arrival (optional)</Label>
                                <Input
                                    id="actual-arrival"
                                    type="datetime-local"
                                    value={completeForm.data.actual_arrival_at}
                                    onChange={(event) => completeForm.setData('actual_arrival_at', event.target.value)}
                                />
                                {completeForm.errors.actual_arrival_at && (
                                    <p className="text-destructive text-xs">{completeForm.errors.actual_arrival_at}</p>
                                )}
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="operational-notes">Operational notes (optional)</Label>
                                <Textarea
                                    id="operational-notes"
                                    value={completeForm.data.operational_notes}
                                    onChange={(event) => completeForm.setData('operational_notes', event.target.value)}
                                    rows={3}
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="incident-notes">Incident notes (optional)</Label>
                                <Textarea
                                    id="incident-notes"
                                    value={completeForm.data.incident_notes}
                                    onChange={(event) => completeForm.setData('incident_notes', event.target.value)}
                                    rows={3}
                                    placeholder="Record delays, safety concerns, breakdowns, or other incidents."
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <FinalizationConfirmation checked={confirmed} onCheckedChange={setConfirmed} outcome="COMPLETED" />
                    <Button type="submit" className="w-full sm:w-auto" disabled={!confirmed || completeForm.processing}>
                        {completeForm.processing ? <LoaderCircle className="animate-spin" /> : <CheckCircle2 />}
                        Complete service
                    </Button>
                </form>
            ) : (
                <form onSubmit={submitNotOperated} className="space-y-5">
                    <Card className="shadow-none">
                        <CardContent className="grid gap-4 p-5">
                            <div className="grid gap-2">
                                <Label htmlFor="not-operated-reason">Reason not operated</Label>
                                <Textarea
                                    id="not-operated-reason"
                                    value={notOperatedForm.data.not_operated_reason}
                                    onChange={(event) => notOperatedForm.setData('not_operated_reason', event.target.value)}
                                    rows={3}
                                    placeholder="Explain why this planned service did not run."
                                    aria-invalid={Boolean(notOperatedForm.errors.not_operated_reason)}
                                />
                                {notOperatedForm.errors.not_operated_reason && (
                                    <p className="text-destructive text-xs">{notOperatedForm.errors.not_operated_reason}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="not-operated-operational-notes">Operational notes (optional)</Label>
                                <Textarea
                                    id="not-operated-operational-notes"
                                    value={notOperatedForm.data.operational_notes}
                                    onChange={(event) => notOperatedForm.setData('operational_notes', event.target.value)}
                                    rows={3}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="not-operated-incident-notes">Incident notes (optional)</Label>
                                <Textarea
                                    id="not-operated-incident-notes"
                                    value={notOperatedForm.data.incident_notes}
                                    onChange={(event) => notOperatedForm.setData('incident_notes', event.target.value)}
                                    rows={3}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <FinalizationConfirmation checked={confirmed} onCheckedChange={setConfirmed} outcome="NOT_OPERATED" />
                    <Button type="submit" variant="secondary" className="w-full sm:w-auto" disabled={!confirmed || notOperatedForm.processing}>
                        {notOperatedForm.processing ? <LoaderCircle className="animate-spin" /> : <XCircle />}
                        Record as not operated
                    </Button>
                </form>
            )}
        </div>
    );
}

function FinalizationConfirmation({
    checked,
    onCheckedChange,
    outcome,
}: {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    outcome: 'COMPLETED' | 'NOT_OPERATED';
}) {
    return (
        <Label className="bg-muted/30 flex cursor-pointer items-start gap-3 rounded-xl border p-4 leading-5">
            <Checkbox checked={checked} onCheckedChange={(value) => onCheckedChange(value === true)} className="mt-0.5" />
            <span>
                I have reviewed this service and confirm it should be finalized as{' '}
                <strong>{outcome === 'COMPLETED' ? 'completed' : 'not operated'}</strong>.
            </span>
        </Label>
    );
}

function FinalizedServiceRecord({
    occurrence,
    onReloadDetail,
    onFinalized,
}: {
    occurrence: ServiceOccurrence;
    onReloadDetail: () => void;
    onFinalized: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);
    const manifest = manifestEntries(occurrence);
    const manifestByReservationId = new Map(
        manifest.flatMap((entry) => {
            const reservationId = manifestReservationId(entry);

            return reservationId === null ? [] : [[reservationId, entry] as const];
        }),
    );
    const initialAttendance = correctionAttendanceItems(occurrence);
    const [originalAttendanceStatuses] = useState(() => new Map(initialAttendance.map((item) => [item.reservation_id, item.status])));
    const correctionForm = useForm<CorrectionFormData>({
        opening_odometer_km: numberValue(occurrence.opening_odometer_km),
        closing_odometer_km: numberValue(occurrence.closing_odometer_km),
        actual_departure_at: datetimeLocalValue(occurrence.actual_departure_at),
        actual_arrival_at: datetimeLocalValue(occurrence.actual_arrival_at),
        operational_notes: occurrence.operational_notes ?? '',
        incident_notes: occurrence.incident_notes ?? '',
        not_operated_reason: occurrence.not_operated_reason ?? '',
        reason: '',
        attendance: initialAttendance,
    });
    const reopenForm = useForm<{ reason: string }>({ reason: '' });

    function submitCorrection(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        correctionForm.transform((data) => ({
            ...data,
            attendance: data.attendance.filter((item) => originalAttendanceStatuses.get(item.reservation_id) !== item.status),
        }));
        correctionForm.patch(`/admin/finished-services/${occurrence.id}/correction`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditing(false);
                toast.success('Service record corrected with an audit entry.');
                onReloadDetail();
            },
            onError: (errors) => {
                toast.error(firstFormError(errors) ?? 'The service record could not be corrected.');
            },
        });
    }

    function submitReopen(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        reopenForm.post(`/admin/finished-services/${occurrence.id}/reopen`, {
            preserveScroll: true,
            onSuccess: () => {
                setReopenOpen(false);
                toast.success('Service reopened for attendance and refinalization.');
                onFinalized();
            },
            onError: (errors) => {
                toast.error(firstFormError(errors) ?? 'The service could not be reopened.');
            },
        });
    }

    function cancelCorrection(): void {
        correctionForm.reset();
        correctionForm.clearErrors();
        setEditing(false);
    }

    const finalizerName = occurrence.finalized_by_name ?? occurrence.finalized_by?.name ?? occurrence.finalizer?.name ?? 'Administrator';

    return (
        <div className="space-y-5">
            <Alert
                className={
                    occurrence.status === 'COMPLETED'
                        ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/30'
                        : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900'
                }
            >
                {occurrence.status === 'COMPLETED' ? <CheckCircle2 /> : <XCircle />}
                <AlertTitle>{occurrence.status === 'COMPLETED' ? 'Completed service' : 'Service did not operate'}</AlertTitle>
                <AlertDescription>
                    Finalized {displayDateTime(occurrence.finalized_at)} by {finalizerName}. This record is read-only unless a correction or reopen
                    reason is recorded.
                </AlertDescription>
            </Alert>

            <Card className="shadow-none">
                <CardHeader className="flex-row items-center justify-between gap-3">
                    <CardTitle className="text-base">Final operational record</CardTitle>
                    {!editing && (
                        <Button type="button" variant="outline" size="sm" onClick={() => setEditing(true)}>
                            <PencilLine />
                            Correct record
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {editing ? (
                        <form onSubmit={submitCorrection} className="grid gap-4 sm:grid-cols-2">
                            {occurrence.status === 'COMPLETED' && (
                                <>
                                    <CorrectionField
                                        id="correction-opening"
                                        label="Opening odometer (km)"
                                        type="number"
                                        value={correctionForm.data.opening_odometer_km}
                                        onChange={(value) => correctionForm.setData('opening_odometer_km', value)}
                                        error={correctionForm.errors.opening_odometer_km}
                                    />
                                    <CorrectionField
                                        id="correction-closing"
                                        label="Closing odometer (km)"
                                        type="number"
                                        value={correctionForm.data.closing_odometer_km}
                                        onChange={(value) => correctionForm.setData('closing_odometer_km', value)}
                                        error={correctionForm.errors.closing_odometer_km}
                                    />
                                    <CorrectionField
                                        id="correction-departure"
                                        label="Actual departure"
                                        type="datetime-local"
                                        value={correctionForm.data.actual_departure_at}
                                        onChange={(value) => correctionForm.setData('actual_departure_at', value)}
                                        error={correctionForm.errors.actual_departure_at}
                                    />
                                    <CorrectionField
                                        id="correction-arrival"
                                        label="Actual arrival"
                                        type="datetime-local"
                                        value={correctionForm.data.actual_arrival_at}
                                        onChange={(value) => correctionForm.setData('actual_arrival_at', value)}
                                        error={correctionForm.errors.actual_arrival_at}
                                    />
                                </>
                            )}
                            {occurrence.status === 'NOT_OPERATED' && (
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="correction-not-operated">Reason not operated</Label>
                                    <Textarea
                                        id="correction-not-operated"
                                        value={correctionForm.data.not_operated_reason}
                                        onChange={(event) => correctionForm.setData('not_operated_reason', event.target.value)}
                                        aria-invalid={Boolean(correctionForm.errors.not_operated_reason)}
                                    />
                                    {correctionForm.errors.not_operated_reason && (
                                        <p className="text-destructive text-xs">{correctionForm.errors.not_operated_reason}</p>
                                    )}
                                </div>
                            )}
                            {occurrence.status === 'COMPLETED' && correctionForm.data.attendance.length > 0 && (
                                <div className="overflow-hidden rounded-xl border sm:col-span-2">
                                    <div className="border-b bg-slate-50/70 px-4 py-3 dark:bg-slate-950/35">
                                        <p className="text-sm font-semibold">Passenger attendance</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Change only incorrect outcomes. Unchanged passengers are not rewritten.
                                        </p>
                                    </div>
                                    <div className="[&::-webkit-scrollbar-thumb]:bg-border hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/60 max-h-72 divide-y overflow-y-auto overscroll-contain [scrollbar-color:var(--color-border)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:transition-colors [&::-webkit-scrollbar-track]:bg-transparent">
                                        {correctionForm.data.attendance.map((attendanceItem, index) => {
                                            const manifestEntry = manifestByReservationId.get(attendanceItem.reservation_id);

                                            return (
                                                <div
                                                    key={attendanceItem.reservation_id}
                                                    className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {manifestEntry ? manifestEmployeeName(manifestEntry) : 'Reserved passenger'}
                                                        </p>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            Seat #{manifestEntry?.seat_number ?? '—'}
                                                        </p>
                                                    </div>
                                                    <Select
                                                        value={attendanceItem.status}
                                                        onValueChange={(status: 'BOARDED' | 'NO_SHOW') =>
                                                            correctionForm.setData(
                                                                'attendance',
                                                                correctionForm.data.attendance.map((item, itemIndex) =>
                                                                    itemIndex === index
                                                                        ? {
                                                                              ...item,
                                                                              status,
                                                                              boarded_at: status === 'BOARDED' ? item.boarded_at : null,
                                                                          }
                                                                        : item,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full sm:w-40">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="BOARDED">Boarded</SelectItem>
                                                            <SelectItem value="NO_SHOW">No-show</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    {correctionForm.errors.attendance && (
                                        <p className="text-destructive border-t px-4 py-3 text-xs">{correctionForm.errors.attendance}</p>
                                    )}
                                </div>
                            )}
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="correction-operational">Operational notes</Label>
                                <Textarea
                                    id="correction-operational"
                                    value={correctionForm.data.operational_notes}
                                    onChange={(event) => correctionForm.setData('operational_notes', event.target.value)}
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="correction-incident">Incident notes</Label>
                                <Textarea
                                    id="correction-incident"
                                    value={correctionForm.data.incident_notes}
                                    onChange={(event) => correctionForm.setData('incident_notes', event.target.value)}
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="correction-reason">Correction reason</Label>
                                <Textarea
                                    id="correction-reason"
                                    value={correctionForm.data.reason}
                                    onChange={(event) => correctionForm.setData('reason', event.target.value)}
                                    placeholder="Required for the permanent audit record."
                                    aria-invalid={Boolean(correctionForm.errors.reason)}
                                />
                                {correctionForm.errors.reason && <p className="text-destructive text-xs">{correctionForm.errors.reason}</p>}
                            </div>
                            <div className="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
                                <Button type="button" variant="ghost" onClick={cancelCorrection}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={correctionForm.processing}>
                                    {correctionForm.processing ? <LoaderCircle className="animate-spin" /> : <History />}
                                    Save audited correction
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <div className="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                            {occurrence.status === 'COMPLETED' ? (
                                <>
                                    <ReadOnlyValue label="Opening odometer" value={`${numberValue(occurrence.opening_odometer_km) || '—'} km`} />
                                    <ReadOnlyValue label="Closing odometer" value={`${numberValue(occurrence.closing_odometer_km) || '—'} km`} />
                                    <ReadOnlyValue
                                        label="Trip distance"
                                        value={`${numberValue(occurrence.trip_distance_km ?? occurrence.distance_km) || '—'} km`}
                                    />
                                    <ReadOnlyValue label="Actual departure" value={displayDateTime(occurrence.actual_departure_at)} />
                                    <ReadOnlyValue label="Actual arrival" value={displayDateTime(occurrence.actual_arrival_at)} />
                                </>
                            ) : (
                                <ReadOnlyValue label="Reason not operated" value={occurrence.not_operated_reason ?? '—'} className="sm:col-span-2" />
                            )}
                            <ReadOnlyValue
                                label="Operational notes"
                                value={occurrence.operational_notes ?? '—'}
                                className="sm:col-span-2 lg:col-span-3"
                            />
                            <ReadOnlyValue label="Incident notes" value={occurrence.incident_notes ?? '—'} className="sm:col-span-2 lg:col-span-3" />
                        </div>
                    )}
                </CardContent>
            </Card>

            {(occurrence.corrections?.length ?? 0) > 0 && (
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <History className="text-primary size-5" />
                            Audit history
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {occurrence.corrections?.map((correction) => {
                            const beforeValues = correction.before_values ?? {};
                            const afterValues = correction.after_values ?? {};
                            const changedFields = Object.keys(afterValues)
                                .filter((field) => JSON.stringify(beforeValues[field]) !== JSON.stringify(afterValues[field]))
                                .map((field) => field.replaceAll('_', ' '))
                                .join(', ');

                            return (
                                <div key={correction.id} className="rounded-xl border p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-semibold capitalize">{correction.action.toLowerCase().replaceAll('_', ' ')}</p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {displayDateTime(correction.corrected_at)} · {correction.administrator?.name ?? 'Administrator'}
                                            </p>
                                        </div>
                                        {changedFields && <Badge variant="outline">{changedFields}</Badge>}
                                    </div>
                                    <p className="mt-3 text-sm">{correction.reason}</p>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            )}

            <div className="flex flex-col gap-3 rounded-2xl border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="font-semibold">Wrong service outcome?</p>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Reopening is required to change completed to not operated, or the reverse. The reason is permanently audited.
                    </p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={() => setReopenOpen(true)}>
                    <RotateCcw />
                    Reopen service
                </Button>
            </div>

            <Dialog open={reopenOpen} onOpenChange={setReopenOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reopen finalized service?</DialogTitle>
                        <DialogDescription>
                            Attendance and closeout become editable again. You must refinalize the service, and this action stays in the audit
                            history.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitReopen} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="reopen-reason">Reason for reopening</Label>
                            <Textarea
                                id="reopen-reason"
                                value={reopenForm.data.reason}
                                onChange={(event) => reopenForm.setData('reason', event.target.value)}
                                rows={4}
                                placeholder="Explain why the finalized record must be reopened."
                                aria-invalid={Boolean(reopenForm.errors.reason)}
                            />
                            {reopenForm.errors.reason && <p className="text-destructive text-xs">{reopenForm.errors.reason}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setReopenOpen(false)}>
                                Keep finalized
                            </Button>
                            <Button type="submit" variant="destructive" disabled={reopenForm.processing}>
                                {reopenForm.processing ? <LoaderCircle className="animate-spin" /> : <RotateCcw />}
                                Reopen record
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function CorrectionField({
    id,
    label,
    type,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    type: 'number' | 'datetime-local';
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type={type}
                step={type === 'number' ? '0.1' : undefined}
                min={type === 'number' ? '0' : undefined}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
            />
            {error && <p className="text-destructive text-xs">{error}</p>}
        </div>
    );
}

function ReadOnlyValue({ label, value, className }: { label: string; value: string; className?: string }) {
    return (
        <div className={className}>
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="mt-1 text-sm font-medium whitespace-pre-wrap">{value}</p>
        </div>
    );
}
