import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { ServiceActivitySheet } from '@/components/admin/service-activity-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type RouteStatus = 'ACTIVE' | 'INACTIVE';
type ManagedRoute = { id: number; name: string; origin: string; destination: string; status: RouteStatus };
type RouteFormData = Omit<ManagedRoute, 'id'>;
interface RoutesPageProps {
    routes: ManagedRoute[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Routes', href: '/admin/routes' },
];
const emptyForm: RouteFormData = { name: '', origin: '', destination: '', status: 'ACTIVE' };

export default function RoutesPage({ routes }: RoutesPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingRoute, setEditingRoute] = useState<ManagedRoute | null>(null);
    const [deletingRoute, setDeletingRoute] = useState<ManagedRoute | null>(null);
    const [activityRoute, setActivityRoute] = useState<ManagedRoute | null>(null);
    const form = useForm<RouteFormData>(emptyForm);
    const deleteForm = useForm<Record<string, never>>({});
    const columns: AdminTableColumn<ManagedRoute>[] = [
        {
            key: 'name',
            label: 'Route name',
            render: (route) => <span className="font-medium">{route.name}</span>,
            sortValue: (route) => route.name,
        },
        { key: 'origin', label: 'Origin', render: (route) => route.origin, sortValue: (route) => route.origin },
        { key: 'destination', label: 'Destination', render: (route) => route.destination, sortValue: (route) => route.destination },
        {
            key: 'status',
            label: 'Status',
            render: (route) => <Badge variant={route.status === 'ACTIVE' ? 'default' : 'secondary'}>{route.status}</Badge>,
            sortValue: (route) => route.status,
        },
    ];

    function openCreate(): void {
        setEditingRoute(null);
        form.setData(emptyForm);
        form.clearErrors();
        setFormOpen(true);
    }
    function openEdit(route: ManagedRoute): void {
        setEditingRoute(route);
        form.setData({ name: route.name, origin: route.origin, destination: route.destination, status: route.status });
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
                toast.success(editingRoute ? 'Route updated successfully.' : 'Route created successfully.');
            },
        };
        if (editingRoute) form.put(`/admin/routes/${editingRoute.id}`, options);
        else form.post('/admin/routes', options);
    }
    function confirmDelete(): void {
        if (!deletingRoute) return;
        deleteForm.delete(`/admin/routes/${deletingRoute.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingRoute(null);
                toast.success('Route deleted successfully.');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Route management" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Route management</h1>
                        <p className="text-muted-foreground">Manage endpoint-based shuttle routes.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus /> Add route
                    </Button>
                </div>
                <AdminDataTable
                    data={routes}
                    columns={columns}
                    searchPlaceholder="Search routes..."
                    getSearchText={(route) => `${route.name} ${route.origin} ${route.destination} ${route.status}`}
                    onEdit={openEdit}
                    onDelete={setDeletingRoute}
                    extraActions={[{ label: 'Activity', onSelect: setActivityRoute }]}
                    filterOptions={[
                        { value: 'ALL', label: 'All statuses' },
                        { value: 'ACTIVE', label: 'Active' },
                        { value: 'INACTIVE', label: 'Inactive' },
                    ]}
                    getFilterValue={(route) => route.status}
                />
            </div>

            <ServiceActivitySheet
                subject="ROUTE"
                endpoint="/admin/routes"
                subjectId={activityRoute?.id ?? null}
                subjectLabel={activityRoute?.name ?? ''}
                open={activityRoute !== null}
                onOpenChange={(open) => !open && setActivityRoute(null)}
            />
            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingRoute ? 'Edit route' : 'Add route'}</DialogTitle>
                        <DialogDescription>Define the route endpoints used by reusable schedules.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="route-name">Route name</Label>
                                <Input id="route-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                                {form.errors.name && <p className="text-destructive text-sm">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="route-origin">Origin</Label>
                                <Input id="route-origin" value={form.data.origin} onChange={(event) => form.setData('origin', event.target.value)} />
                                {form.errors.origin && <p className="text-destructive text-sm">{form.errors.origin}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="route-destination">Destination</Label>
                                <Input
                                    id="route-destination"
                                    value={form.data.destination}
                                    onChange={(event) => form.setData('destination', event.target.value)}
                                />
                                {form.errors.destination && <p className="text-destructive text-sm">{form.errors.destination}</p>}
                            </div>
                            {editingRoute && (
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Status</Label>
                                    <Select value={form.data.status} onValueChange={(value: RouteStatus) => form.setData('status', value)}>
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
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Save route'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={Boolean(deletingRoute)} onOpenChange={(open) => !open && setDeletingRoute(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete route?</DialogTitle>
                        <DialogDescription>
                            This permanently removes {deletingRoute?.name}. Routes referenced by schedules cannot be deleted.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteForm.errors.route && <p className="text-destructive text-sm">{deleteForm.errors.route}</p>}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingRoute(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                            {deleteForm.processing ? 'Deleting...' : 'Delete route'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
