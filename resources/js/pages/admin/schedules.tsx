import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type ScheduleStatus = 'ACTIVE' | 'INACTIVE';
type Direction = 'OUTBOUND' | 'RETURN';
type ManagedRoute = { id: number; name: string; status: 'ACTIVE' | 'INACTIVE' };
type ManagedVehicle = { id: number; plate_number: string; capacity: number; status: 'ACTIVE' | 'MAINTENANCE' | 'INACTIVE' };
type ManagedDriver = { id: number; name: string; employee_id: string; status: 'ACTIVE' | 'INACTIVE' };
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
    status: ScheduleStatus;
    notes: string;
};

interface SchedulesPageProps {
    schedules: ManagedSchedule[];
    routes: ManagedRoute[];
    vehicles: ManagedVehicle[];
    drivers: ManagedDriver[];
    operatingTimezone: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Schedules', href: '/admin/schedules' },
];
const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const today = () => new Date().toISOString().slice(0, 10);
const emptyForm: ScheduleFormData = {
    route_id: '',
    vehicle_id: '',
    driver_id: '',
    direction: 'OUTBOUND',
    departure_time: '07:00',
    operating_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    effective_from: today(),
    effective_until: '',
    capacity_override: '',
    status: 'ACTIVE',
    notes: '',
};

export default function SchedulesPage({ schedules, routes, vehicles, drivers, operatingTimezone }: SchedulesPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingSchedule, setEditingSchedule] = useState<ManagedSchedule | null>(null);
    const [deletingSchedule, setDeletingSchedule] = useState<ManagedSchedule | null>(null);
    const form = useForm<ScheduleFormData>(emptyForm);
    const deleteForm = useForm<Record<string, never>>({});
    const columns: AdminTableColumn<ManagedSchedule>[] = [
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
        { key: 'driver', label: 'Driver', render: (schedule) => schedule.driver?.name, sortValue: (schedule) => schedule.driver?.name ?? '' },
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
            key: 'status',
            label: 'Status',
            render: (schedule) => <Badge variant={schedule.status === 'ACTIVE' ? 'default' : 'secondary'}>{schedule.status}</Badge>,
            sortValue: (schedule) => schedule.status,
        },
    ];

    function openCreate(): void {
        setEditingSchedule(null);
        form.setData(emptyForm);
        form.clearErrors();
        setFormOpen(true);
    }
    function openEdit(schedule: ManagedSchedule): void {
        setEditingSchedule(schedule);
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
            status: schedule.status,
            notes: schedule.notes ?? '',
        });
        form.clearErrors();
        setFormOpen(true);
    }
    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                form.setData(emptyForm);
                toast.success(editingSchedule ? 'Schedule updated successfully.' : 'Schedule created successfully.');
            },
        };
        if (editingSchedule) form.put(`/admin/schedules/${editingSchedule.id}`, options);
        else form.post('/admin/schedules', options);
    }
    function confirmDelete(): void {
        if (!deletingSchedule) return;
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Schedule management" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Schedule management</h1>
                        <p className="text-muted-foreground">Create reusable {operatingTimezone} shuttle schedule templates.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus /> Add schedule
                    </Button>
                </div>
                <AdminDataTable
                    data={schedules}
                    columns={columns}
                    searchPlaceholder="Search schedules..."
                    getSearchText={(schedule) =>
                        `${schedule.route?.name} ${schedule.vehicle?.plate_number} ${schedule.driver?.name} ${schedule.direction} ${schedule.status} ${schedule.operating_days.join(' ')}`
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
            </div>
            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{editingSchedule ? 'Edit schedule' : 'Add schedule'}</DialogTitle>
                        <DialogDescription>Active schedules require active route, vehicle, and driver records.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
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
                                    onValueChange={(value) => form.setData('vehicle_id', Number(value))}
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
                                                {vehicle.plate_number}
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
                            <div className="space-y-2">
                                <Label htmlFor="schedule-capacity">Capacity override (optional)</Label>
                                <Input
                                    id="schedule-capacity"
                                    type="number"
                                    min={1}
                                    value={form.data.capacity_override}
                                    onChange={(event) => form.setData('capacity_override', event.target.value ? Number(event.target.value) : '')}
                                />
                                {form.errors.capacity_override && <p className="text-destructive text-sm">{form.errors.capacity_override}</p>}
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="schedule-notes">Notes</Label>
                                <Textarea
                                    id="schedule-notes"
                                    value={form.data.notes}
                                    onChange={(event) => form.setData('notes', event.target.value)}
                                />
                                {form.errors.notes && <p className="text-destructive text-sm">{form.errors.notes}</p>}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Save schedule'}
                            </Button>
                        </DialogFooter>
                    </form>
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
