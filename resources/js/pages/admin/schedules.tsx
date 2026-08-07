import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { ScheduleOperationsGrid, type ScheduleOccurrence } from '@/components/admin/schedule-operations-grid';
import { ScheduleSeatAllocationEditor, type SeatAllocation } from '@/components/admin/schedule-seat-allocation-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { Clock3, LayoutGrid, LoaderCircle, Plus, Table2 } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type ScheduleStatus = 'ACTIVE' | 'INACTIVE';
type Direction = 'OUTBOUND' | 'RETURN';
type ViewMode = 'table' | 'grid';
type ManagedRoute = {
    id: number;
    name: string;
    status: 'ACTIVE' | 'INACTIVE';
};
type ManagedVehicle = {
    id: number;
    plate_number: string;
    capacity: number;
    status: 'ACTIVE' | 'MAINTENANCE' | 'INACTIVE';
};
type ManagedDriver = {
    id: number;
    name: string;
    employee_id: string;
    status: 'ACTIVE' | 'INACTIVE';
};
type ManagedSchedule = {
    id: number;
    route_id: number;
    vehicle_id: number;
    driver_id: number;
    direction: Direction;
    departure_time: string;
    operating_days: string[];
    effective_from: string;
    effective_until: string | null;
    capacity_override: number | null;
    priority_seats: number[] | null;
    unavailable_seats: number[] | null;
    waitlist_enabled: boolean;
    waitlist_capacity: number | null;
    status: ScheduleStatus;
    notes: string | null;
    route: ManagedRoute;
    vehicle: ManagedVehicle;
    driver: ManagedDriver;
};
type ScheduleFormData = {
    route_id: number | '';
    vehicle_id: number | '';
    driver_id: number | '';
    direction: Direction;
    departure_time: string;
    operating_days: string[];
    effective_from: string;
    effective_until: string;
    capacity_override: number | '';
    priority_seats: number[];
    unavailable_seats: number[];
    waitlist_enabled: boolean;
    waitlist_capacity: number | '';
    status: ScheduleStatus;
    notes: string;
};

interface BookingWindow {
    enabled: boolean;
    opens_at: string;
    closes_at: string;
    defaults: {
        opens_at: string;
        closes_at: string;
    };
}

interface BookingWindowForm {
    [key: string]: string | boolean;
    enabled: boolean;
    opens_at: string;
    closes_at: string;
}

interface SchedulesPageProps {
    schedules: ManagedSchedule[];
    routes: ManagedRoute[];
    vehicles: ManagedVehicle[];
    drivers: ManagedDriver[];
    operatingTimezone: string;
    selectedDate: string;
    bookingWindow: BookingWindow;
    defaultPrioritySeatCount?: number;
    scheduleOccurrences?: ScheduleOccurrence[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Schedules', href: '/admin/schedules' },
];
const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

function today(): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

    return `${values.year}-${values.month}-${values.day}`;
}

function displayClockTime(time: string): string {
    if (!time) {
        return '—';
    }

    const [hourValue, minute = '00'] = time.split(':');
    const hour = Number(hourValue);

    return `${hour % 12 || 12}:${minute} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function BookingWindowDialog({ bookingWindow, operatingTimezone }: { bookingWindow: BookingWindow; operatingTimezone: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm<BookingWindowForm>({
        enabled: bookingWindow.enabled,
        opens_at: bookingWindow.opens_at,
        closes_at: bookingWindow.closes_at,
    });
    const spansMidnight = form.data.enabled && form.data.opens_at !== '' && form.data.closes_at !== '' && form.data.closes_at < form.data.opens_at;
    const defaultBookingWindow = bookingWindow.defaults;

    function openDialog(): void {
        form.setData({
            enabled: bookingWindow.enabled,
            opens_at: bookingWindow.opens_at,
            closes_at: bookingWindow.closes_at,
        });
        form.clearErrors();
        setOpen(true);
    }

    function toggleEnabled(enabled: boolean): void {
        form.setData({
            enabled,
            opens_at: enabled && form.data.opens_at === '' ? defaultBookingWindow.opens_at : form.data.opens_at,
            closes_at: enabled && form.data.closes_at === '' ? defaultBookingWindow.closes_at : form.data.closes_at,
        });
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put('/admin/schedules/booking-window', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success('Employee booking window updated successfully.');
            },
        });
    }

    return (
        <>
            <Button variant="outline" onClick={openDialog}>
                <Clock3 />
                Booking window
                <Badge variant={bookingWindow.enabled ? 'default' : 'secondary'} className="ml-1">
                    {bookingWindow.enabled
                        ? `${displayClockTime(bookingWindow.opens_at)} – ${displayClockTime(bookingWindow.closes_at)}`
                        : 'Always open'}
                </Badge>
            </Button>
            <Dialog open={open} onOpenChange={(nextOpen) => !form.processing && setOpen(nextOpen)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Employee booking window</DialogTitle>
                        <DialogDescription>
                            Choose the hours when employees may open the schedules page and book seats. Times follow {operatingTimezone}.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <label className="hover:bg-accent/40 flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors">
                            <Checkbox checked={form.data.enabled} onCheckedChange={(checked) => toggleEnabled(checked === true)} className="mt-0.5" />
                            <span className="min-w-0">
                                <span className="block text-sm font-medium">Restrict booking to set hours</span>
                                <span className="text-muted-foreground block text-xs">
                                    When off, employees may browse schedules and book at any time of day.
                                </span>
                            </span>
                        </label>
                        {form.errors.enabled && <p className="text-destructive text-sm">{form.errors.enabled}</p>}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="booking-opens-at">Booking opens at</Label>
                                <Input
                                    id="booking-opens-at"
                                    type="time"
                                    value={form.data.opens_at}
                                    disabled={!form.data.enabled}
                                    onChange={(event) => form.setData('opens_at', event.target.value)}
                                />
                                {form.errors.opens_at && <p className="text-destructive text-sm">{form.errors.opens_at}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="booking-closes-at">Booking closes at</Label>
                                <Input
                                    id="booking-closes-at"
                                    type="time"
                                    value={form.data.closes_at}
                                    disabled={!form.data.enabled}
                                    onChange={(event) => form.setData('closes_at', event.target.value)}
                                />
                                {form.errors.closes_at && <p className="text-destructive text-sm">{form.errors.closes_at}</p>}
                            </div>
                        </div>

                        <p className="text-muted-foreground rounded-lg border p-3 text-xs leading-5">
                            {spansMidnight
                                ? `This window crosses midnight: booking stays open from ${displayClockTime(form.data.opens_at)} through ${displayClockTime(form.data.closes_at)} the following morning. `
                                : ''}
                            Outside these hours the employee schedules page is locked, and reserving a seat, changing seats, and joining a waitlist
                            are blocked. Employees can still sign in, review their reservations, and cancel a booking at any time.
                        </p>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={form.processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="animate-spin" />}
                                Save booking window
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function createEmptyForm(): ScheduleFormData {
    return {
        route_id: '',
        vehicle_id: '',
        driver_id: '',
        direction: 'OUTBOUND',
        departure_time: '07:00',
        operating_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        effective_from: today(),
        effective_until: '',
        capacity_override: '',
        priority_seats: [],
        unavailable_seats: [],
        waitlist_enabled: true,
        waitlist_capacity: '',
        status: 'ACTIVE',
        notes: '',
    };
}

function defaultPrioritySeats(capacity: number, defaultPrioritySeatCount: number): number[] {
    return Array.from({ length: Math.min(capacity, defaultPrioritySeatCount) }, (_, index) => index + 1);
}

function normalizeAllocation(capacity: number, prioritySeats: number[], unavailableSeats: number[]): SeatAllocation {
    const normalizedUnavailableSeats = new Set(unavailableSeats.filter((seatNumber) => seatNumber >= 1 && seatNumber <= capacity));
    const normalizedPrioritySeats = new Set(
        prioritySeats.filter((seatNumber) => seatNumber >= 1 && seatNumber <= capacity && !normalizedUnavailableSeats.has(seatNumber)),
    );

    return {
        prioritySeats: [...normalizedPrioritySeats].sort((left, right) => left - right),
        unavailableSeats: [...normalizedUnavailableSeats].sort((left, right) => left - right),
    };
}

function effectiveCapacity(vehicleId: number | '', capacityOverride: number | '', vehicles: ManagedVehicle[]): number {
    if (capacityOverride !== '') {
        return Math.max(0, capacityOverride);
    }

    return vehicles.find((vehicle) => vehicle.id === vehicleId)?.capacity ?? 0;
}

export default function SchedulesPage({
    schedules,
    routes,
    vehicles,
    drivers,
    operatingTimezone,
    selectedDate = today(),
    bookingWindow,
    defaultPrioritySeatCount = 8,
    scheduleOccurrences = [],
}: SchedulesPageProps) {
    const [viewMode, setViewMode] = useState<ViewMode>('grid');
    const [formOpen, setFormOpen] = useState(false);
    const [editingSchedule, setEditingSchedule] = useState<ManagedSchedule | null>(null);
    const [seatAllocationCustomized, setSeatAllocationCustomized] = useState(false);
    const [deletingSchedule, setDeletingSchedule] = useState<ManagedSchedule | null>(null);
    const form = useForm<ScheduleFormData>(createEmptyForm());
    const deleteForm = useForm<Record<string, never>>({});
    const formCapacity = effectiveCapacity(form.data.vehicle_id, form.data.capacity_override, vehicles);

    usePoll(10000, {
        only: ['scheduleOccurrences'],
    });

    const columns = useMemo<AdminTableColumn<ManagedSchedule>[]>(
        () => [
            {
                key: 'route',
                label: 'Route',
                render: (schedule) => <span className="font-medium">{schedule.route?.name}</span>,
                sortValue: (schedule) => schedule.route?.name ?? '',
            },
            {
                key: 'vehicle',
                label: 'Vehicle',
                render: (schedule) => schedule.vehicle?.plate_number,
                sortValue: (schedule) => schedule.vehicle?.plate_number ?? '',
            },
            {
                key: 'driver',
                label: 'Driver',
                render: (schedule) => schedule.driver?.name,
                sortValue: (schedule) => schedule.driver?.name ?? '',
            },
            {
                key: 'departure_time',
                label: 'Departure',
                render: (schedule) => `${schedule.departure_time.slice(0, 5)} · ${schedule.direction}`,
                sortValue: (schedule) => schedule.departure_time,
            },
            {
                key: 'operating_days',
                label: 'Operating days',
                render: (schedule) => schedule.operating_days.map((day) => day.slice(0, 3)).join(', '),
                sortValue: (schedule) => schedule.operating_days.join(','),
            },
            {
                key: 'seats',
                label: 'Seat allocation',
                render: (schedule) => {
                    const capacity = schedule.capacity_override ?? schedule.vehicle?.capacity ?? 0;
                    const prioritySeatCount = schedule.priority_seats?.length ?? Math.min(capacity, defaultPrioritySeatCount);
                    const unavailableSeatCount = schedule.unavailable_seats?.length ?? 0;

                    return (
                        <span className="text-sm">
                            {prioritySeatCount} priority · {unavailableSeatCount} unavailable
                        </span>
                    );
                },
            },
            {
                key: 'waitlist',
                label: 'Waitlist',
                render: (schedule) => (
                    <Badge variant={schedule.waitlist_enabled ? 'secondary' : 'outline'}>
                        {schedule.waitlist_enabled
                            ? schedule.waitlist_capacity === null
                                ? 'Enabled'
                                : `Max ${schedule.waitlist_capacity}`
                            : 'Disabled'}
                    </Badge>
                ),
                sortValue: (schedule) => (schedule.waitlist_enabled ? 1 : 0),
            },
            {
                key: 'status',
                label: 'Status',
                render: (schedule) => <Badge variant={schedule.status === 'ACTIVE' ? 'default' : 'secondary'}>{schedule.status}</Badge>,
                sortValue: (schedule) => schedule.status,
            },
        ],
        [defaultPrioritySeatCount],
    );

    function openCreate(): void {
        setEditingSchedule(null);
        setSeatAllocationCustomized(false);
        form.setData(createEmptyForm());
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(schedule: ManagedSchedule): void {
        const capacity = schedule.capacity_override ?? schedule.vehicle?.capacity ?? 0;
        const allocation = normalizeAllocation(
            capacity,
            schedule.priority_seats ?? defaultPrioritySeats(capacity, defaultPrioritySeatCount),
            schedule.unavailable_seats ?? [],
        );

        setEditingSchedule(schedule);
        setSeatAllocationCustomized(true);
        form.setData({
            route_id: schedule.route_id,
            vehicle_id: schedule.vehicle_id,
            driver_id: schedule.driver_id,
            direction: schedule.direction,
            departure_time: schedule.departure_time.slice(0, 5),
            operating_days: schedule.operating_days,
            effective_from: schedule.effective_from.slice(0, 10),
            effective_until: schedule.effective_until?.slice(0, 10) ?? '',
            capacity_override: schedule.capacity_override ?? '',
            priority_seats: allocation.prioritySeats,
            unavailable_seats: allocation.unavailableSeats,
            waitlist_enabled: schedule.waitlist_enabled,
            waitlist_capacity: schedule.waitlist_capacity ?? '',
            status: schedule.status,
            notes: schedule.notes ?? '',
        });
        form.clearErrors();
        setFormOpen(true);
    }

    function openEditById(scheduleId: number): void {
        const schedule = schedules.find((item) => item.id === scheduleId);

        if (!schedule) {
            toast.error('This schedule template could not be found.');
            return;
        }

        openEdit(schedule);
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                form.setData(createEmptyForm());
                toast.success(editingSchedule ? 'Schedule and seat configuration updated successfully.' : 'Schedule created successfully.');
            },
        };

        if (editingSchedule) {
            form.put(`/admin/schedules/${editingSchedule.id}`, options);
        } else {
            form.post('/admin/schedules', options);
        }
    }

    function confirmDelete(): void {
        if (!deletingSchedule) {
            return;
        }

        deleteForm.delete(`/admin/schedules/${deletingSchedule.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingSchedule(null);
                toast.success('Schedule deleted successfully.');
            },
        });
    }

    function toggleDay(day: string, checked: boolean): void {
        form.setData(
            'operating_days',
            checked ? [...form.data.operating_days, day] : form.data.operating_days.filter((selected) => selected !== day),
        );
    }

    function changeVehicle(vehicleId: number): void {
        const capacity = effectiveCapacity(vehicleId, form.data.capacity_override, vehicles);
        const allocation = normalizeAllocation(
            capacity,
            !editingSchedule && !seatAllocationCustomized ? defaultPrioritySeats(capacity, defaultPrioritySeatCount) : form.data.priority_seats,
            !editingSchedule && !seatAllocationCustomized ? [] : form.data.unavailable_seats,
        );

        form.setData({
            ...form.data,
            vehicle_id: vehicleId,
            priority_seats: allocation.prioritySeats,
            unavailable_seats: allocation.unavailableSeats,
        });
    }

    function changeCapacityOverride(value: string): void {
        const capacityOverride = value ? Number(value) : '';
        const capacity = effectiveCapacity(form.data.vehicle_id, capacityOverride, vehicles);
        const allocation = normalizeAllocation(
            capacity,
            !editingSchedule && !seatAllocationCustomized ? defaultPrioritySeats(capacity, defaultPrioritySeatCount) : form.data.priority_seats,
            !editingSchedule && !seatAllocationCustomized ? [] : form.data.unavailable_seats,
        );

        form.setData({
            ...form.data,
            capacity_override: capacityOverride,
            priority_seats: allocation.prioritySeats,
            unavailable_seats: allocation.unavailableSeats,
        });
    }

    function changeAllocation(allocation: SeatAllocation): void {
        setSeatAllocationCustomized(true);
        const normalized = normalizeAllocation(formCapacity, allocation.prioritySeats, allocation.unavailableSeats);

        form.setData({
            ...form.data,
            priority_seats: normalized.prioritySeats,
            unavailable_seats: normalized.unavailableSeats,
        });
        form.clearErrors('priority_seats', 'unavailable_seats');
    }

    function changeDate(date: string): void {
        if (date === selectedDate) {
            return;
        }

        router.get(
            '/admin/schedules',
            { date },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['selectedDate', 'scheduleOccurrences'],
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Schedule management" />
            <div className="flex max-w-full min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Schedule management</h1>
                        <p className="text-muted-foreground">Configure reusable schedules or inspect live trip occurrences in {operatingTimezone}.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            value={viewMode}
                            onValueChange={(value) => value && setViewMode(value as ViewMode)}
                            variant="outline"
                            aria-label="Schedule view"
                            className="rounded-md border p-0.5"
                        >
                            <ToggleGroupItem value="table" aria-label="Table view" className="gap-2 px-3">
                                <Table2 />
                                <span className="hidden sm:inline">Table</span>
                            </ToggleGroupItem>
                            <ToggleGroupItem value="grid" aria-label="Grid view" className="gap-2 px-3">
                                <LayoutGrid />
                                <span className="hidden sm:inline">Grid</span>
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <BookingWindowDialog bookingWindow={bookingWindow} operatingTimezone={operatingTimezone} />
                        <Button onClick={openCreate}>
                            <Plus />
                            Add schedule
                        </Button>
                    </div>
                </div>

                {viewMode === 'table' ? (
                    <AdminDataTable
                        data={schedules}
                        columns={columns}
                        searchPlaceholder="Search schedules..."
                        getSearchText={(schedule) =>
                            `${schedule.route?.name} ${schedule.vehicle?.plate_number} ${schedule.driver?.name} ${schedule.direction} ${schedule.status} ${schedule.operating_days.join(' ')} ${schedule.waitlist_enabled ? 'waitlist enabled' : 'waitlist disabled'}`
                        }
                        onEdit={openEdit}
                        onDelete={setDeletingSchedule}
                        filterOptions={[
                            { value: 'ALL', label: 'All statuses' },
                            { value: 'ACTIVE', label: 'Active' },
                            { value: 'INACTIVE', label: 'Inactive' },
                        ]}
                        getFilterValue={(schedule) => schedule.status}
                    />
                ) : (
                    <ScheduleOperationsGrid
                        selectedDate={selectedDate}
                        occurrences={scheduleOccurrences}
                        operatingTimezone={operatingTimezone}
                        onDateChange={changeDate}
                        onConfigure={openEditById}
                    />
                )}
            </div>

            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent className="max-h-[92vh] gap-0 overflow-hidden p-0 sm:max-w-4xl">
                    <div className="[&::-webkit-scrollbar-thumb]:bg-border hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/60 mx-2 mt-8 mb-8 max-h-[calc(92vh-3rem)] overflow-y-auto px-4 py-4 pr-6 [scrollbar-color:var(--color-border)_transparent] [scrollbar-width:thin] sm:px-6 sm:py-6 sm:pr-8 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:transition-colors [&::-webkit-scrollbar-track]:bg-transparent">
                        <DialogHeader className="pr-8">
                            <DialogTitle>{editingSchedule ? 'Edit and configure schedule' : 'Add schedule'}</DialogTitle>
                            <DialogDescription>
                                Configure the recurring trip, its usable seats, protected seat allocation, and waitlist behavior.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submit} className="mt-4 space-y-6">
                            <section className="space-y-4">
                                <div>
                                    <h3 className="font-semibold">Schedule details</h3>
                                    <p className="text-muted-foreground text-xs">
                                        Active schedules require active route, vehicle, and driver records.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Route</Label>
                                        <Select
                                            value={form.data.route_id ? String(form.data.route_id) : ''}
                                            onValueChange={(value) => form.setData('route_id', Number(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select route" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {routes.map((route) => (
                                                    <SelectItem
                                                        key={route.id}
                                                        value={String(route.id)}
                                                        disabled={form.data.status === 'ACTIVE' && route.status !== 'ACTIVE'}
                                                    >
                                                        {route.name}
                                                        {route.status !== 'ACTIVE' ? ' (inactive)' : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.route_id && <p className="text-destructive text-sm">{form.errors.route_id}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Vehicle</Label>
                                        <Select
                                            value={form.data.vehicle_id ? String(form.data.vehicle_id) : ''}
                                            onValueChange={(value) => changeVehicle(Number(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select vehicle" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {vehicles.map((vehicle) => (
                                                    <SelectItem
                                                        key={vehicle.id}
                                                        value={String(vehicle.id)}
                                                        disabled={form.data.status === 'ACTIVE' && vehicle.status !== 'ACTIVE'}
                                                    >
                                                        {vehicle.plate_number} · {vehicle.capacity} seats
                                                        {vehicle.status !== 'ACTIVE' ? ` (${vehicle.status.toLowerCase()})` : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.vehicle_id && <p className="text-destructive text-sm">{form.errors.vehicle_id}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Driver</Label>
                                        <Select
                                            value={form.data.driver_id ? String(form.data.driver_id) : ''}
                                            onValueChange={(value) => form.setData('driver_id', Number(value))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select driver" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {drivers.map((driver) => (
                                                    <SelectItem
                                                        key={driver.id}
                                                        value={String(driver.id)}
                                                        disabled={form.data.status === 'ACTIVE' && driver.status !== 'ACTIVE'}
                                                    >
                                                        {driver.name} · {driver.employee_id}
                                                        {driver.status !== 'ACTIVE' ? ' (inactive)' : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.driver_id && <p className="text-destructive text-sm">{form.errors.driver_id}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Direction</Label>
                                        <Select value={form.data.direction} onValueChange={(value: Direction) => form.setData('direction', value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="OUTBOUND">Outbound</SelectItem>
                                                <SelectItem value="RETURN">Return</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {form.errors.direction && <p className="text-destructive text-sm">{form.errors.direction}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="schedule-departure">Departure time ({operatingTimezone})</Label>
                                        <Input
                                            id="schedule-departure"
                                            type="time"
                                            value={form.data.departure_time}
                                            onChange={(event) => form.setData('departure_time', event.target.value)}
                                        />
                                        {form.errors.departure_time && <p className="text-destructive text-sm">{form.errors.departure_time}</p>}
                                    </div>

                                    {editingSchedule && (
                                        <div className="space-y-2">
                                            <Label>Status</Label>
                                            <Select value={form.data.status} onValueChange={(value: ScheduleStatus) => form.setData('status', value)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="ACTIVE">Active</SelectItem>
                                                    <SelectItem value="INACTIVE">Inactive</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {form.errors.status && <p className="text-destructive text-sm">{form.errors.status}</p>}
                                        </div>
                                    )}

                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Operating days</Label>
                                        <div className="flex flex-wrap gap-4 rounded-md border p-3">
                                            {days.map((day) => (
                                                <label key={day} className="flex cursor-pointer items-center gap-2 text-sm capitalize">
                                                    <Checkbox
                                                        checked={form.data.operating_days.includes(day)}
                                                        onCheckedChange={(checked) => toggleDay(day, checked === true)}
                                                    />
                                                    {day}
                                                </label>
                                            ))}
                                        </div>
                                        {form.errors.operating_days && <p className="text-destructive text-sm">{form.errors.operating_days}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="schedule-from">Effective from</Label>
                                        <Input
                                            id="schedule-from"
                                            type="date"
                                            value={form.data.effective_from}
                                            onChange={(event) => form.setData('effective_from', event.target.value)}
                                        />
                                        {form.errors.effective_from && <p className="text-destructive text-sm">{form.errors.effective_from}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="schedule-until">Effective until (optional)</Label>
                                        <Input
                                            id="schedule-until"
                                            type="date"
                                            value={form.data.effective_until}
                                            onChange={(event) => form.setData('effective_until', event.target.value)}
                                        />
                                        {form.errors.effective_until && <p className="text-destructive text-sm">{form.errors.effective_until}</p>}
                                    </div>
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <div>
                                    <h3 className="font-semibold">Capacity and queue</h3>
                                    <p className="text-muted-foreground text-xs">
                                        A blank capacity override uses the selected vehicle capacity. A blank waitlist limit allows an unlimited
                                        queue.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="schedule-capacity">Capacity override (optional)</Label>
                                        <Input
                                            id="schedule-capacity"
                                            type="number"
                                            min={1}
                                            value={form.data.capacity_override}
                                            onChange={(event) => changeCapacityOverride(event.target.value)}
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Current physical capacity: {formCapacity || 'Select a vehicle'}
                                        </p>
                                        {form.errors.capacity_override && <p className="text-destructive text-sm">{form.errors.capacity_override}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="schedule-waitlist-capacity">Waitlist capacity (optional)</Label>
                                        <Input
                                            id="schedule-waitlist-capacity"
                                            type="number"
                                            min={1}
                                            disabled={!form.data.waitlist_enabled}
                                            placeholder="Unlimited"
                                            value={form.data.waitlist_capacity}
                                            onChange={(event) =>
                                                form.setData('waitlist_capacity', event.target.value ? Number(event.target.value) : '')
                                            }
                                        />
                                        {form.errors.waitlist_capacity && <p className="text-destructive text-sm">{form.errors.waitlist_capacity}</p>}
                                    </div>

                                    <label className="bg-muted/25 flex cursor-pointer items-start gap-3 rounded-xl border p-4 sm:col-span-2">
                                        <Checkbox
                                            checked={form.data.waitlist_enabled}
                                            onCheckedChange={(checked) =>
                                                form.setData({
                                                    ...form.data,
                                                    waitlist_enabled: checked === true,
                                                    waitlist_capacity: checked === true ? form.data.waitlist_capacity : '',
                                                })
                                            }
                                        />
                                        <span>
                                            <span className="block text-sm font-medium">Enable waitlist for this schedule</span>
                                            <span className="text-muted-foreground mt-1 block text-xs">
                                                Employees may join only after every seat eligible to them is occupied.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <section className="space-y-4 border-t pt-6">
                                <ScheduleSeatAllocationEditor
                                    capacity={formCapacity}
                                    prioritySeats={form.data.priority_seats}
                                    unavailableSeats={form.data.unavailable_seats}
                                    onChange={changeAllocation}
                                />
                                {form.errors.priority_seats && <p className="text-destructive text-sm">{form.errors.priority_seats}</p>}
                                {form.errors.unavailable_seats && <p className="text-destructive text-sm">{form.errors.unavailable_seats}</p>}
                            </section>

                            <section className="space-y-2 border-t pt-6">
                                <Label htmlFor="schedule-notes">Notes</Label>
                                <Textarea
                                    id="schedule-notes"
                                    value={form.data.notes}
                                    onChange={(event) => form.setData('notes', event.target.value)}
                                />
                                {form.errors.notes && <p className="text-destructive text-sm">{form.errors.notes}</p>}
                            </section>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setFormOpen(false)} disabled={form.processing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Saving...' : 'Save schedule'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={Boolean(deletingSchedule)} onOpenChange={(open) => !open && setDeletingSchedule(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete schedule?</DialogTitle>
                        <DialogDescription>
                            This permanently removes the reusable schedule template for {deletingSchedule?.route?.name}.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteForm.errors.schedule && <p className="text-destructive text-sm">{deleteForm.errors.schedule}</p>}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingSchedule(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                            {deleteForm.processing ? 'Deleting...' : 'Delete schedule'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
