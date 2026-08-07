import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { ServiceActivitySheet } from '@/components/admin/service-activity-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type VehicleStatus = 'ACTIVE' | 'MAINTENANCE' | 'INACTIVE';
type ManagedVehicle = {
    id: number;
    plate_number: string;
    vehicle_type: string;
    capacity: number;
    status: VehicleStatus;
    notes: string | null;
};
type VehicleFormData = Omit<ManagedVehicle, 'id'>;

interface VehiclesPageProps {
    vehicles: ManagedVehicle[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vehicles', href: '/admin/vehicles' },
];
const emptyForm: VehicleFormData = {
    plate_number: '',
    vehicle_type: '',
    capacity: 1,
    status: 'ACTIVE',
    notes: '',
};

function statusVariant(status: VehicleStatus): 'default' | 'secondary' | 'destructive' {
    if (status === 'ACTIVE') return 'default';
    if (status === 'MAINTENANCE') return 'secondary';
    return 'destructive';
}

export default function VehiclesPage({ vehicles }: VehiclesPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingVehicle, setEditingVehicle] = useState<ManagedVehicle | null>(null);
    const [deletingVehicle, setDeletingVehicle] = useState<ManagedVehicle | null>(null);
    const [activityVehicle, setActivityVehicle] = useState<ManagedVehicle | null>(null);
    const form = useForm<VehicleFormData>(emptyForm);
    const deleteForm = useForm<Record<string, never>>({});

    const columns: AdminTableColumn<ManagedVehicle>[] = [
        {
            key: 'plate_number',
            label: 'Plate number',
            render: (vehicle) => <span className="font-medium">{vehicle.plate_number}</span>,
            sortValue: (vehicle) => vehicle.plate_number,
        },
        { key: 'vehicle_type', label: 'Type', render: (vehicle) => vehicle.vehicle_type, sortValue: (vehicle) => vehicle.vehicle_type },
        { key: 'capacity', label: 'Capacity', render: (vehicle) => `${vehicle.capacity} seats`, sortValue: (vehicle) => vehicle.capacity },
        {
            key: 'status',
            label: 'Status',
            render: (vehicle) => <Badge variant={statusVariant(vehicle.status)}>{vehicle.status}</Badge>,
            sortValue: (vehicle) => vehicle.status,
        },
    ];

    function openCreate(): void {
        setEditingVehicle(null);
        form.setData(emptyForm);
        form.clearErrors();
        setFormOpen(true);
    }
    function openEdit(vehicle: ManagedVehicle): void {
        setEditingVehicle(vehicle);
        form.setData({
            plate_number: vehicle.plate_number,
            vehicle_type: vehicle.vehicle_type,
            capacity: vehicle.capacity,
            status: vehicle.status,
            notes: vehicle.notes ?? '',
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
                toast.success(editingVehicle ? 'Vehicle updated successfully.' : 'Vehicle created successfully.');
            },
        };
        if (editingVehicle) form.put(`/admin/vehicles/${editingVehicle.id}`, options);
        else form.post('/admin/vehicles', options);
    }
    function confirmDelete(): void {
        if (!deletingVehicle) return;
        deleteForm.delete(`/admin/vehicles/${deletingVehicle.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingVehicle(null);
                toast.success('Vehicle deleted successfully.');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vehicle management" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Vehicle management</h1>
                        <p className="text-muted-foreground">Configure the shuttle fleet and passenger capacity.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus /> Add vehicle
                    </Button>
                </div>
                <AdminDataTable
                    data={vehicles}
                    columns={columns}
                    searchPlaceholder="Search vehicles..."
                    getSearchText={(vehicle) => `${vehicle.plate_number} ${vehicle.vehicle_type} ${vehicle.status}`}
                    onEdit={openEdit}
                    onDelete={setDeletingVehicle}
                    extraActions={[{ label: 'Activity', onSelect: setActivityVehicle }]}
                    filterOptions={[
                        { value: 'ALL', label: 'All statuses' },
                        { value: 'ACTIVE', label: 'Active' },
                        { value: 'MAINTENANCE', label: 'Maintenance' },
                        { value: 'INACTIVE', label: 'Inactive' },
                    ]}
                    getFilterValue={(vehicle) => vehicle.status}
                />
            </div>

            <ServiceActivitySheet
                subject="VEHICLE"
                endpoint="/admin/vehicles"
                subjectId={activityVehicle?.id ?? null}
                subjectLabel={activityVehicle?.plate_number ?? ''}
                open={activityVehicle !== null}
                onOpenChange={(open) => !open && setActivityVehicle(null)}
            />

            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editingVehicle ? 'Edit vehicle' : 'Add vehicle'}</DialogTitle>
                        <DialogDescription>Maintain the vehicle details used by shuttle schedules.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="vehicle-plate">Plate number</Label>
                                <Input
                                    id="vehicle-plate"
                                    value={form.data.plate_number}
                                    onChange={(event) => form.setData('plate_number', event.target.value.toUpperCase())}
                                />
                                {form.errors.plate_number && <p className="text-destructive text-sm">{form.errors.plate_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="vehicle-type">Vehicle type</Label>
                                <Input
                                    id="vehicle-type"
                                    placeholder="Bus, van, minibus"
                                    value={form.data.vehicle_type}
                                    onChange={(event) => form.setData('vehicle_type', event.target.value)}
                                />
                                {form.errors.vehicle_type && <p className="text-destructive text-sm">{form.errors.vehicle_type}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="vehicle-capacity">Passenger capacity</Label>
                                <Input
                                    id="vehicle-capacity"
                                    type="number"
                                    min={1}
                                    value={form.data.capacity}
                                    onChange={(event) => form.setData('capacity', Number(event.target.value))}
                                />
                                {form.errors.capacity && <p className="text-destructive text-sm">{form.errors.capacity}</p>}
                            </div>
                            {editingVehicle && (
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Status</Label>
                                    <Select value={form.data.status} onValueChange={(value: VehicleStatus) => form.setData('status', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ACTIVE">Active</SelectItem>
                                            <SelectItem value="MAINTENANCE">Maintenance</SelectItem>
                                            <SelectItem value="INACTIVE">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.status && <p className="text-destructive text-sm">{form.errors.status}</p>}
                                </div>
                            )}
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="vehicle-notes">Notes</Label>
                                <Textarea
                                    id="vehicle-notes"
                                    value={form.data.notes ?? ''}
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
                                {form.processing ? 'Saving...' : 'Save vehicle'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={Boolean(deletingVehicle)} onOpenChange={(open) => !open && setDeletingVehicle(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete vehicle?</DialogTitle>
                        <DialogDescription>
                            This permanently removes {deletingVehicle?.plate_number}. Vehicles referenced by schedules cannot be deleted.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteForm.errors.vehicle && <p className="text-destructive text-sm">{deleteForm.errors.vehicle}</p>}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingVehicle(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                            {deleteForm.processing ? 'Deleting...' : 'Delete vehicle'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
