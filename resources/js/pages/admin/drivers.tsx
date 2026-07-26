import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
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

type DriverStatus = 'ACTIVE' | 'INACTIVE';
type ManagedDriver = {
    id: number;
    name: string;
    employee_id: string;
    contact_number: string;
    license_number: string;
    license_expires_at: string;
    status: DriverStatus;
    notes: string | null;
};
type DriverFormData = Omit<ManagedDriver, 'id'>;

interface DriversPageProps {
    drivers: ManagedDriver[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Drivers', href: '/admin/drivers' },
];
const emptyForm: DriverFormData = {
    name: '',
    employee_id: '',
    contact_number: '',
    license_number: '',
    license_expires_at: '',
    status: 'ACTIVE',
    notes: '',
};

export default function DriversPage({ drivers }: DriversPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingDriver, setEditingDriver] = useState<ManagedDriver | null>(null);
    const [deletingDriver, setDeletingDriver] = useState<ManagedDriver | null>(null);
    const form = useForm<DriverFormData>(emptyForm);
    const deleteForm = useForm<Record<string, never>>({});
    const columns: AdminTableColumn<ManagedDriver>[] = [
        { key: 'name', label: 'Driver', render: (driver) => <span className="font-medium">{driver.name}</span>, sortValue: (driver) => driver.name },
        { key: 'employee_id', label: 'Employee / contractor ID', render: (driver) => driver.employee_id, sortValue: (driver) => driver.employee_id },
        { key: 'contact_number', label: 'Contact', render: (driver) => driver.contact_number, sortValue: (driver) => driver.contact_number },
        {
            key: 'license_expires_at',
            label: 'License expiry',
            render: (driver) => new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(driver.license_expires_at)),
            sortValue: (driver) => driver.license_expires_at,
        },
        {
            key: 'status',
            label: 'Status',
            render: (driver) => <Badge variant={driver.status === 'ACTIVE' ? 'default' : 'secondary'}>{driver.status}</Badge>,
            sortValue: (driver) => driver.status,
        },
    ];

    function openCreate(): void {
        setEditingDriver(null);
        form.setData(emptyForm);
        form.clearErrors();
        setFormOpen(true);
    }
    function openEdit(driver: ManagedDriver): void {
        setEditingDriver(driver);
        form.setData({
            name: driver.name,
            employee_id: driver.employee_id,
            contact_number: driver.contact_number,
            license_number: driver.license_number,
            license_expires_at: driver.license_expires_at.slice(0, 10),
            status: driver.status,
            notes: driver.notes ?? '',
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
                toast.success(editingDriver ? 'Driver updated successfully.' : 'Driver created successfully.');
            },
        };
        if (editingDriver) form.put(`/admin/drivers/${editingDriver.id}`, options);
        else form.post('/admin/drivers', options);
    }
    function confirmDelete(): void {
        if (!deletingDriver) return;
        deleteForm.delete(`/admin/drivers/${deletingDriver.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingDriver(null);
                toast.success('Driver deleted successfully.');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Driver management" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Driver management</h1>
                        <p className="text-muted-foreground">Manage driver assignments and license compliance.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus /> Add driver
                    </Button>
                </div>
                <AdminDataTable
                    data={drivers}
                    columns={columns}
                    searchPlaceholder="Search drivers..."
                    getSearchText={(driver) =>
                        `${driver.name} ${driver.employee_id} ${driver.contact_number} ${driver.license_number} ${driver.status}`
                    }
                    onEdit={openEdit}
                    onDelete={setDeletingDriver}
                    filterOptions={[
                        { value: 'ALL', label: 'All statuses' },
                        { value: 'ACTIVE', label: 'Active' },
                        { value: 'INACTIVE', label: 'Inactive' },
                    ]}
                    getFilterValue={(driver) => driver.status}
                />
            </div>

            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editingDriver ? 'Edit driver' : 'Add driver'}</DialogTitle>
                        <DialogDescription>Record the operational and compliance details for this driver.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="driver-name">Full name</Label>
                                <Input id="driver-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                                {form.errors.name && <p className="text-destructive text-sm">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="driver-employee-id">Employee / contractor ID</Label>
                                <Input
                                    id="driver-employee-id"
                                    value={form.data.employee_id}
                                    onChange={(event) => form.setData('employee_id', event.target.value)}
                                />
                                {form.errors.employee_id && <p className="text-destructive text-sm">{form.errors.employee_id}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="driver-contact">Contact number</Label>
                                <Input
                                    id="driver-contact"
                                    value={form.data.contact_number}
                                    onChange={(event) => form.setData('contact_number', event.target.value)}
                                />
                                {form.errors.contact_number && <p className="text-destructive text-sm">{form.errors.contact_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="driver-license">License number</Label>
                                <Input
                                    id="driver-license"
                                    value={form.data.license_number}
                                    onChange={(event) => form.setData('license_number', event.target.value)}
                                />
                                {form.errors.license_number && <p className="text-destructive text-sm">{form.errors.license_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="driver-license-expiry">License expiry date</Label>
                                <Input
                                    id="driver-license-expiry"
                                    type="date"
                                    value={form.data.license_expires_at}
                                    onChange={(event) => form.setData('license_expires_at', event.target.value)}
                                />
                                {form.errors.license_expires_at && <p className="text-destructive text-sm">{form.errors.license_expires_at}</p>}
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label>Status</Label>
                                <Select value={form.data.status} onValueChange={(value: DriverStatus) => form.setData('status', value)}>
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
                                <Label htmlFor="driver-notes">Notes</Label>
                                <Textarea
                                    id="driver-notes"
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
                                {form.processing ? 'Saving...' : 'Save driver'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={Boolean(deletingDriver)} onOpenChange={(open) => !open && setDeletingDriver(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete driver?</DialogTitle>
                        <DialogDescription>
                            This permanently removes {deletingDriver?.name}. Drivers referenced by schedules cannot be deleted.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteForm.errors.driver && <p className="text-destructive text-sm">{deleteForm.errors.driver}</p>}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingDriver(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                            {deleteForm.processing ? 'Deleting...' : 'Delete driver'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
