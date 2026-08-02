import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type ManagedDepartment = {
    id: number;
    name: string;
    employees_count: number;
    created_at: string;
};

interface DepartmentsPageProps {
    departments: ManagedDepartment[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/admin/departments' },
];

export default function DepartmentsPage({ departments }: DepartmentsPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState<ManagedDepartment | null>(null);
    const [deletingDepartment, setDeletingDepartment] = useState<ManagedDepartment | null>(null);
    const form = useForm({ name: '' });
    const deleteForm = useForm<Record<string, never>>({});

    const columns: AdminTableColumn<ManagedDepartment>[] = [
        {
            key: 'name',
            label: 'Department',
            render: (department) => <span className="font-medium">{department.name}</span>,
            sortValue: (department) => department.name,
        },
        {
            key: 'employees_count',
            label: 'Employees',
            render: (department) => (
                <Badge variant={department.employees_count > 0 ? 'secondary' : 'outline'}>
                    {department.employees_count.toLocaleString()} {department.employees_count === 1 ? 'employee' : 'employees'}
                </Badge>
            ),
            sortValue: (department) => department.employees_count,
        },
    ];

    function openCreate(): void {
        setEditingDepartment(null);
        form.setData('name', '');
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(department: ManagedDepartment): void {
        setEditingDepartment(department);
        form.setData('name', department.name);
        form.clearErrors();
        setFormOpen(true);
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                form.setData('name', '');
                toast.success(editingDepartment ? 'Department updated successfully.' : 'Department created successfully.');
            },
        };

        if (editingDepartment) {
            form.put(`/admin/departments/${editingDepartment.id}`, options);
        } else {
            form.post('/admin/departments', options);
        }
    }

    function confirmDelete(): void {
        if (!deletingDepartment) {
            return;
        }

        deleteForm.delete(`/admin/departments/${deletingDepartment.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingDepartment(null);
                toast.success('Department deleted successfully.');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Department management" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Department management</h1>
                        <p className="text-muted-foreground">Maintain the department choices used by employee records and operational filters.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus />
                        Add department
                    </Button>
                </div>

                <AdminDataTable
                    data={departments}
                    columns={columns}
                    searchPlaceholder="Search departments..."
                    getSearchText={(department) => department.name}
                    onEdit={openEdit}
                    onDelete={(department) => {
                        deleteForm.clearErrors();
                        setDeletingDepartment(department);
                    }}
                />
            </div>

            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingDepartment ? 'Edit department' : 'Add department'}</DialogTitle>
                        <DialogDescription>
                            {editingDepartment
                                ? 'Renaming this department also updates all current employee records assigned to it.'
                                : 'This department becomes available in employee dropdowns after it is saved.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="department-name">Department name</Label>
                            <Input
                                id="department-name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                autoFocus
                            />
                            {form.errors.name && <p className="text-destructive text-sm">{form.errors.name}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)} disabled={form.processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Save department'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={deletingDepartment !== null} onOpenChange={(open) => !open && setDeletingDepartment(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete department?</DialogTitle>
                        <DialogDescription>
                            {deletingDepartment?.employees_count
                                ? `${deletingDepartment.name} still has ${deletingDepartment.employees_count.toLocaleString()} assigned ${deletingDepartment.employees_count === 1 ? 'employee' : 'employees'}. Reassign them before deleting this department.`
                                : `This permanently removes ${deletingDepartment?.name ?? 'this department'} from future employee selections.`}
                        </DialogDescription>
                    </DialogHeader>
                    {deleteForm.errors.department && <p className="text-destructive text-sm">{deleteForm.errors.department}</p>}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeletingDepartment(null)} disabled={deleteForm.processing}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                            disabled={deleteForm.processing || Boolean(deletingDepartment?.employees_count)}
                        >
                            {deleteForm.processing ? 'Deleting...' : 'Delete department'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
