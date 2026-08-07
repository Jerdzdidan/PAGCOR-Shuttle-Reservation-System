import { AdminDataTable, type AdminTableColumn } from '@/components/admin/admin-data-table';
import { EmployeeActivitySheet } from '@/components/admin/employee-activity-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Download, Eye, Plus, ShieldOff, Upload } from 'lucide-react';
import { QRCodeCanvas, QRCodeSVG } from 'qrcode.react';
import { useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

type ManagedEmployee = {
    employee_id: number;
    employee_code: string;
    name: string;
    email: string;
    contact_number: string | null;
    department: string | null;
    position: string | null;
    priority_status: 'REGULAR' | 'PRIORITY';
    status: 'ACTIVE' | 'INACTIVE';
    qr_login_url: string;
    created_at: string;
    future_reservations_count: number;
    future_waitlist_count: number;
};

type EmployeeFormData = {
    name: string;
    email: string;
    contact_number: string;
    department: string;
    position: string;
    priority_status: 'REGULAR' | 'PRIORITY';
    status: 'ACTIVE' | 'INACTIVE';
};

type ManagedDepartment = {
    id: number;
    name: string;
};

interface EmployeesPageProps {
    employees: ManagedEmployee[];
    departments: ManagedDepartment[];
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
    status: 'ACTIVE',
};
const noDepartmentValue = '__NO_DEPARTMENT__';

function priorityLabel(employee: ManagedEmployee): string {
    return employee.priority_status === 'PRIORITY' ? 'Priority person' : 'Regular employee';
}

function downloadFileName(employee: ManagedEmployee): string {
    const normalizedName = employee.name
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

    return `${normalizedName || 'employee'}-${employee.employee_code}-qr.png`;
}

function drawCenteredFittedText(context: CanvasRenderingContext2D, text: string, y: number, maximumWidth: number, initialFontSize: number): void {
    let fontSize = initialFontSize;

    context.textAlign = 'center';
    context.textBaseline = 'middle';

    do {
        context.font = `700 ${fontSize}px Arial, sans-serif`;
        fontSize -= 2;
    } while (context.measureText(text).width > maximumWidth && fontSize >= 28);

    context.fillText(text, 500, y);
}

export default function EmployeesPage({ employees, departments }: EmployeesPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [importInputKey, setImportInputKey] = useState(0);
    const [editingEmployee, setEditingEmployee] = useState<ManagedEmployee | null>(null);
    const [viewingEmployee, setViewingEmployee] = useState<ManagedEmployee | null>(null);
    const [deletingEmployee, setDeletingEmployee] = useState<ManagedEmployee | null>(null);
    const [deactivatingEmployee, setDeactivatingEmployee] = useState<ManagedEmployee | null>(null);
    const [activityEmployee, setActivityEmployee] = useState<ManagedEmployee | null>(null);
    const qrCanvasRef = useRef<HTMLCanvasElement>(null);
    const form = useForm<EmployeeFormData>(emptyForm);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const deleteForm = useForm<Record<string, never>>({});
    const deactivateForm = useForm<{ confirmed: boolean }>({ confirmed: true });

    const columns: AdminTableColumn<ManagedEmployee>[] = [
        {
            key: 'employee_id',
            label: 'Employee ID',
            render: (employee) => <span className="font-medium tabular-nums">{employee.employee_code}</span>,
            sortValue: (employee) => employee.employee_code,
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
        {
            key: 'status',
            label: 'Access',
            render: (employee) => (
                <Badge variant={employee.status === 'ACTIVE' ? 'outline' : 'secondary'}>{employee.status === 'ACTIVE' ? 'Active' : 'Inactive'}</Badge>
            ),
            sortValue: (employee) => employee.status,
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
            status: employee.status,
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

    function openDeactivate(employee: ManagedEmployee): void {
        deactivateForm.setData('confirmed', true);
        deactivateForm.clearErrors();
        setFormOpen(false);
        setDeactivatingEmployee(employee);
    }

    function confirmDeactivate(): void {
        if (!deactivatingEmployee) {
            return;
        }

        deactivateForm.post(`/admin/employees/${deactivatingEmployee.employee_id}/deactivate`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeactivatingEmployee(null);
                toast.success('Employee travel resolved and QR access deactivated.');
            },
        });
    }

    function downloadEmployeeQr(employee: ManagedEmployee): void {
        const qrCanvas = qrCanvasRef.current;

        if (!qrCanvas) {
            toast.error('The employee QR code is not ready yet.');
            return;
        }

        const downloadCanvas = document.createElement('canvas');
        downloadCanvas.width = 1000;
        downloadCanvas.height = 1240;

        const context = downloadCanvas.getContext('2d');

        if (!context) {
            toast.error('The QR image could not be prepared for download.');
            return;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, downloadCanvas.width, downloadCanvas.height);

        context.fillStyle = '#10213d';
        context.fillRect(0, 0, downloadCanvas.width, 140);

        context.fillStyle = '#ffffff';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.font = '700 38px Arial, sans-serif';
        context.fillText('PAGCOR', 500, 50);
        context.font = '600 25px Arial, sans-serif';
        context.fillText('SHUTTLE RESERVATION SYSTEM', 500, 96);

        context.imageSmoothingEnabled = false;
        context.drawImage(qrCanvas, 130, 180, 740, 740);

        context.fillStyle = '#10213d';
        drawCenteredFittedText(context, employee.name, 990, 820, 52);

        context.fillStyle = '#61708a';
        context.font = '500 27px Arial, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(`Employee ID ${employee.employee_code}`, 500, 1045);

        const label = priorityLabel(employee);
        context.font = '700 25px Arial, sans-serif';
        const badgeWidth = Math.max(250, context.measureText(label).width + 76);
        const badgeX = (downloadCanvas.width - badgeWidth) / 2;

        context.fillStyle = employee.priority_status === 'PRIORITY' ? '#fff1c2' : '#e8f1ff';
        context.beginPath();
        context.roundRect(badgeX, 1080, badgeWidth, 62, 31);
        context.fill();

        context.fillStyle = employee.priority_status === 'PRIORITY' ? '#805800' : '#174ea6';
        context.fillText(label, 500, 1111);

        context.fillStyle = '#7b879b';
        context.font = '500 22px Arial, sans-serif';
        context.fillText('Scan to securely sign in', 500, 1190);

        downloadCanvas.toBlob((blob) => {
            if (!blob) {
                toast.error('The QR image could not be generated.');
                return;
            }

            const downloadUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = downloadFileName(employee);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 0);
            toast.success('Employee QR image downloaded.');
        }, 'image/png');
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
                        `${employee.employee_code} ${employee.employee_id} ${employee.name} ${employee.email} ${employee.contact_number ?? ''} ${employee.department ?? ''} ${employee.position ?? ''} ${employee.priority_status} ${employee.status}`
                    }
                    getRowKey={(employee) => employee.employee_id}
                    onView={setViewingEmployee}
                    onEdit={openEdit}
                    onDelete={(employee) => {
                        deleteForm.clearErrors();
                        setDeletingEmployee(employee);
                    }}
                    extraActions={[{ label: 'Activity', onSelect: setActivityEmployee }]}
                />
            </div>

            <Dialog open={formOpen} onOpenChange={(open) => !form.processing && setFormOpen(open)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editingEmployee ? 'Edit employee' : 'Add employee'}</DialogTitle>
                        <DialogDescription>
                            {editingEmployee
                                ? 'Update this employee’s information.'
                                : 'The YY-00000 employee ID and permanent QR code will be generated automatically.'}
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
                                <Select
                                    value={form.data.department || noDepartmentValue}
                                    onValueChange={(value) => form.setData('department', value === noDepartmentValue ? '' : value)}
                                >
                                    <SelectTrigger id="employee-department">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={noDepartmentValue}>No department</SelectItem>
                                        {departments.map((department) => (
                                            <SelectItem key={department.id} value={department.name}>
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">Department choices are maintained in Department management.</p>
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
                            {editingEmployee && (
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>QR access status</Label>
                                    <Select value={form.data.status} onValueChange={(value: 'ACTIVE' | 'INACTIVE') => form.setData('status', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ACTIVE">Active — may sign in and board</SelectItem>
                                            <SelectItem value="INACTIVE">Inactive — access blocked</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">
                                        The permanent QR stays unchanged. Employees with future reservations or waitlist entries cannot be made
                                        inactive until those entries are resolved.
                                    </p>
                                    {form.errors.status && <p className="text-destructive text-sm">{form.errors.status}</p>}
                                </div>
                            )}
                        </div>
                        {editingEmployee?.status === 'ACTIVE' && (
                            <div className="flex flex-col gap-3 rounded-lg border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950/20">
                                <div>
                                    <p className="text-sm font-medium">Need to deactivate this employee?</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Resolve {editingEmployee.future_reservations_count} future{' '}
                                        {editingEmployee.future_reservations_count === 1 ? 'reservation' : 'reservations'} and{' '}
                                        {editingEmployee.future_waitlist_count}{' '}
                                        {editingEmployee.future_waitlist_count === 1 ? 'waitlist entry' : 'waitlist entries'} in one action.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={() => openDeactivate(editingEmployee)}
                                >
                                    <ShieldOff />
                                    Resolve & deactivate
                                </Button>
                            </div>
                        )}
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
                            Department names must already exist in Department management. Name and email are required; priority_status defaults to
                            REGULAR. Imported employees are always created active.
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
                <DialogContent className="max-h-[calc(100svh-2rem)] overflow-y-auto sm:max-w-2xl">
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
                                    title={`QR code for employee ${viewingEmployee.employee_code}`}
                                />
                                <div className="hidden" aria-hidden="true">
                                    <QRCodeCanvas ref={qrCanvasRef} value={viewingEmployee.qr_login_url} size={768} level="H" marginSize={4} />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold tabular-nums">Employee ID {viewingEmployee.employee_code}</p>
                                    <p className="text-xs text-neutral-600">Scan to securely sign in</p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => downloadEmployeeQr(viewingEmployee)}
                                >
                                    <Download />
                                    Download QR
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
                                <div>
                                    <dt className="text-muted-foreground">QR access</dt>
                                    <dd className="mt-1">
                                        <Badge variant={viewingEmployee.status === 'ACTIVE' ? 'outline' : 'secondary'}>
                                            {viewingEmployee.status === 'ACTIVE' ? 'Active' : 'Inactive'}
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

            <EmployeeActivitySheet
                employeeId={activityEmployee?.employee_id ?? null}
                employeeName={activityEmployee?.name ?? ''}
                open={activityEmployee !== null}
                onOpenChange={(open) => !open && setActivityEmployee(null)}
            />

            <Dialog
                open={deactivatingEmployee !== null}
                onOpenChange={(open) => !open && !deactivateForm.processing && setDeactivatingEmployee(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Resolve travel and deactivate?</DialogTitle>
                        <DialogDescription>
                            This blocks {deactivatingEmployee?.name ?? 'this employee'} from QR login and boarding. Their permanent QR and historical
                            records remain unchanged.
                        </DialogDescription>
                    </DialogHeader>
                    {deactivatingEmployee && (
                        <div className="grid gap-3 text-sm">
                            <div className="rounded-lg border p-3">
                                <p className="font-medium">
                                    {deactivatingEmployee.future_reservations_count} future{' '}
                                    {deactivatingEmployee.future_reservations_count === 1 ? 'reservation' : 'reservations'}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Each reservation will be cancelled. Any released seat will be offered to the next eligible waitlisted employee
                                    using the existing priority-first queue.
                                </p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="font-medium">
                                    {deactivatingEmployee.future_waitlist_count} future{' '}
                                    {deactivatingEmployee.future_waitlist_count === 1 ? 'waitlist entry' : 'waitlist entries'}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Each remaining queue entry will be withdrawn and recorded in the activity ledger.
                                </p>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                The server rechecks all future entries when you confirm, so newly created travel is included atomically.
                            </p>
                        </div>
                    )}
                    {deactivateForm.errors.confirmed && <p className="text-destructive text-sm">{deactivateForm.errors.confirmed}</p>}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeactivatingEmployee(null)} disabled={deactivateForm.processing}>
                            Keep active
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDeactivate} disabled={deactivateForm.processing}>
                            <ShieldOff />
                            {deactivateForm.processing ? 'Resolving...' : 'Resolve & deactivate'}
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
