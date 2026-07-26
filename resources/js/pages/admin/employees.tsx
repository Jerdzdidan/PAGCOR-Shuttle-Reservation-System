import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Eye, Plus, RefreshCw, Upload } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type ManagedEmployee = {
    employee_id: number;
    name: string;
    email: string;
    contact_number: string | null;
    department: string | null;
    position: string | null;
    priority_status: 'REGULAR' | 'PRIORITY';
    qr_login_url: string;
    created_at: string;
};

type EmployeeFormData = {
    name: string;
    email: string;
    contact_number: string;
    department: string;
    position: string;
    priority_status: 'REGULAR' | 'PRIORITY';
};

interface EmployeesPageProps {
    employees: ManagedEmployee[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
];

const emptyForm: EmployeeFormData = {
    name: '',
    email: '',
    contact_number: '',
    department: '',
    position: '',
    priority_status: 'REGULAR',
};

export default function EmployeesPage({ employees }: EmployeesPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [importInputKey, setImportInputKey] = useState(0);
    const [editingEmployee, setEditingEmployee] = useState<ManagedEmployee | null>(null);
    const [viewingEmployee, setViewingEmployee] = useState<ManagedEmployee | null>(null);
    const [deletingEmployee, setDeletingEmployee] = useState<ManagedEmployee | null>(null);
    const [regeneratingEmployee, setRegeneratingEmployee] = useState<ManagedEmployee | null>(null);
    const form = useForm<EmployeeFormData>(emptyForm);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const deleteForm = useForm<Record<string, never>>({});
    const regenerateForm = useForm({});

    const columns: AdminTableColumn<ManagedEmployee>[] = [
        {
            key: 'employee_id',
            label: 'Employee ID',
            render: (employee) => <span className="font-medium">#{employee.employee_id}</span>,
            sortValue: (employee) => employee.employee_id,
        },
        { key: 'name', label: 'Name', render: (employee) => employee.name, sortValue: (employee) => employee.name },
        {
            key: 'department',
            label: 'Department',
            render: (employee) => employee.department || '—',
            sortValue: (employee) => employee.department ?? '',
        },
        {
            key: 'position',
            label: 'Position',
            render: (employee) => employee.position || '—',
            sortValue: (employee) => employee.position ?? '',
        },
        {
            key: 'priority_status',
            label: 'Priority',
            render: (employee) => (
                <Badge variant={employee.priority_status === 'PRIORITY' ? 'default' : 'secondary'}>
                    {employee.priority_status === 'PRIORITY' ? 'Priority' : 'Regular'}
                </Badge>
            ),
            sortValue: (employee) => employee.priority_status,
        },
        { key: 'email', label: 'Email', render: (employee) => employee.email, sortValue: (employee) => employee.email },
    ];

    function openCreate(): void {
        setEditingEmployee(null);
        form.setData(emptyForm);
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(employee: ManagedEmployee): void {
        setEditingEmployee(employee);
        form.setData({
            name: employee.name,
            email: employee.email,
            contact_number: employee.contact_number ?? '',
            department: employee.department ?? '',
            position: employee.position ?? '',
            priority_status: employee.priority_status,
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
                toast.success(editingEmployee ? 'Employee updated successfully.' : 'Employee created successfully.');
            },
        };

        if (editingEmployee) {
            form.put(`/admin/employees/${editingEmployee.employee_id}`, options);
        } else {
            form.post('/admin/employees', options);
        }
    }

    function submitImport(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        importForm.post('/admin/employees/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset();
                setImportInputKey((key) => key + 1);
                toast.success('Employees imported successfully.');
            },
        });
    }

    function confirmDelete(): void {
        if (!deletingEmployee) {
            return;
        }

        deleteForm.delete(`/admin/employees/${deletingEmployee.employee_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingEmployee(null);
                toast.success('Employee deleted successfully.');
            },
        });
    }

    function confirmQrRegeneration(): void {
        if (!regeneratingEmployee) {
            return;
        }

        regenerateForm.post(`/admin/employees/${regeneratingEmployee.employee_id}/qr/regenerate`, {
            preserveScroll: true,
            onSuccess: () => {
                setRegeneratingEmployee(null);
                setViewingEmployee(null);
                toast.success('Employee QR code regenerated. The previous QR code can no longer be used.');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employee management" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Employee management</h1>
                        <p className="text-muted-foreground">Manage employee records, imports, and unique QR identification.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => {
                                importForm.clearErrors();
                                setImportOpen(true);
                            }}
                        >
                            <Upload />
                            Import employees
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus />
                            Add employee
                        </Button>
                    </div>
                </div>

                <AdminDataTable
                    data={employees}
                    columns={columns}
                    searchPlaceholder="Search employees..."
                    getSearchText={(employee) =>
                        `${employee.employee_id} ${employee.name} ${employee.email} ${employee.contact_number ?? ''} ${employee.department ?? ''} ${employee.position ?? ''} ${employee.priority_status}`
                    }
                    getRowKey={(employee) => employee.employee_id}
                    onView={setViewingEmployee}
                    onEdit={openEdit}
                    onDelete={(employee) => {
                        deleteForm.clearErrors();
                        setDeletingEmployee(employee);
                    }}
                />
            </div>

            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editingEmployee ? 'Edit employee' : 'Add employee'}</DialogTitle>
                        <DialogDescription>
                            {editingEmployee ? 'Update this employee’s information.' : 'The employee ID and QR code will be generated automatically.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="employee-name">Name</Label>
                                <Input
                                    id="employee-name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    autoComplete="name"
                                />
                                {form.errors.name && <p className="text-destructive text-sm">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="employee-email">Email</Label>
                                <Input
                                    id="employee-email"
                                    type="email"
                                    required
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    autoComplete="email"
                                />
                                {form.errors.email && <p className="text-destructive text-sm">{form.errors.email}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="employee-contact">Contact number</Label>
                                <Input
                                    id="employee-contact"
                                    value={form.data.contact_number}
                                    onChange={(event) => form.setData('contact_number', event.target.value)}
                                />
                                {form.errors.contact_number && <p className="text-destructive text-sm">{form.errors.contact_number}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="employee-department">Department</Label>
                                <Input
                                    id="employee-department"
                                    value={form.data.department}
                                    onChange={(event) => form.setData('department', event.target.value)}
                                />
                                {form.errors.department && <p className="text-destructive text-sm">{form.errors.department}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="employee-position">Position</Label>
                                <Input
                                    id="employee-position"
                                    value={form.data.position}
                                    onChange={(event) => form.setData('position', event.target.value)}
                                />
                                {form.errors.position && <p className="text-destructive text-sm">{form.errors.position}</p>}
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label>Reservation priority</Label>
                                <Select
                                    value={form.data.priority_status}
                                    onValueChange={(value: 'REGULAR' | 'PRIORITY') => form.setData('priority_status', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="REGULAR">Regular employee</SelectItem>
                                        <SelectItem value="PRIORITY">Priority person</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    Priority persons may reserve configured priority seats and move ahead of regular employees in a full-shuttle
                                    queue.
                                </p>
                                {form.errors.priority_status && <p className="text-destructive text-sm">{form.errors.priority_status}</p>}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)} disabled={form.processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : 'Save employee'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={importOpen} onOpenChange={(open) => !importForm.processing && setImportOpen(open)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Import employees</DialogTitle>
                        <DialogDescription>
                            Upload an XLSX, XLS, or CSV file with these headings: name, email, contact_number, department, position, priority_status.
                            Name and email are required; priority_status may be REGULAR or PRIORITY and defaults to REGULAR.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitImport} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="employee-import">Employee file</Label>
                            <Input
                                key={importInputKey}
                                id="employee-import"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)}
                            />
                            {importForm.errors.file && <p className="text-destructive text-sm">{importForm.errors.file}</p>}
                            {importForm.progress && (
                                <div className="space-y-1">
                                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                                        <div className="bg-primary h-full transition-all" style={{ width: `${importForm.progress.percentage}%` }} />
                                    </div>
                                    <p className="text-muted-foreground text-xs">{importForm.progress.percentage}% uploaded</p>
                                </div>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)} disabled={importForm.processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={importForm.processing || !importForm.data.file}>
                                <Upload />
                                {importForm.processing ? 'Importing...' : 'Import employees'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={viewingEmployee !== null} onOpenChange={(open) => !open && setViewingEmployee(null)}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Eye className="size-5" />
                            Employee details
                        </DialogTitle>
                        <DialogDescription>Unique employee information and QR identification.</DialogDescription>
                    </DialogHeader>
                    {viewingEmployee && (
                        <div className="grid gap-6 sm:grid-cols-[220px_1fr] sm:items-center">
                            <div className="flex flex-col items-center gap-3 rounded-lg border bg-white p-3 text-center text-black">
                                <QRCodeSVG
                                    value={viewingEmployee.qr_login_url}
                                    size={192}
                                    level="H"
                                    marginSize={4}
                                    title={`QR code for employee ${viewingEmployee.employee_id}`}
                                />
                                <div>
                                    <p className="text-sm font-semibold">Employee #{viewingEmployee.employee_id}</p>
                                    <p className="text-xs text-neutral-600">Scan to securely sign in</p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => setRegeneratingEmployee(viewingEmployee)}
                                >
                                    <RefreshCw />
                                    Regenerate QR
                                </Button>
                            </div>
                            <dl className="grid gap-4 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Name</dt>
                                    <dd className="font-medium">{viewingEmployee.name}</dd>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">Department</dt>
                                        <dd>{viewingEmployee.department || '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Position</dt>
                                        <dd>{viewingEmployee.position || '—'}</dd>
                                    </div>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Reservation priority</dt>
                                    <dd className="mt-1">
                                        <Badge variant={viewingEmployee.priority_status === 'PRIORITY' ? 'default' : 'secondary'}>
                                            {viewingEmployee.priority_status === 'PRIORITY' ? 'Priority person' : 'Regular employee'}
                                        </Badge>
                                    </dd>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">Email</dt>
                                        <dd className="break-all">{viewingEmployee.email}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Contact number</dt>
                                        <dd>{viewingEmployee.contact_number || '—'}</dd>
                                    </div>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Created</dt>
                                    <dd>{new Intl.DateTimeFormat('en-PH', { dateStyle: 'long' }).format(new Date(viewingEmployee.created_at))}</dd>
                                </div>
                            </dl>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={regeneratingEmployee !== null} onOpenChange={(open) => !open && setRegeneratingEmployee(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Regenerate employee QR?</DialogTitle>
                        <DialogDescription>
                            The current QR code for {regeneratingEmployee?.name ?? 'this employee'} will immediately stop working. Print or issue the
                            new QR code afterward.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setRegeneratingEmployee(null)} disabled={regenerateForm.processing}>
                            Keep current QR
                        </Button>
                        <Button type="button" onClick={confirmQrRegeneration} disabled={regenerateForm.processing}>
                            <RefreshCw />
                            {regenerateForm.processing ? 'Regenerating...' : 'Regenerate QR'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deletingEmployee !== null} onOpenChange={(open) => !open && setDeletingEmployee(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete employee?</DialogTitle>
                        <DialogDescription>
                            This permanently removes {deletingEmployee?.name ?? 'this employee'} and their QR identification.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        {deleteForm.errors.employee && <p className="text-destructive mr-auto text-sm">{deleteForm.errors.employee}</p>}
                        <Button type="button" variant="outline" onClick={() => setDeletingEmployee(null)} disabled={deleteForm.processing}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                            {deleteForm.processing ? 'Deleting...' : 'Delete employee'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
