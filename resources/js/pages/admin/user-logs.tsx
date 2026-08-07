import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { History, RotateCcw, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type ActionValue = 'CREATED' | 'UPDATED' | 'DELETED';

interface UserLogEntry {
    id: number;
    occurred_at: string;
    user: {
        name: string;
        email: string | null;
    };
    action: ActionValue;
    action_label: string;
    subject_type: string;
    subject_type_label: string;
    subject_label: string;
    changed_fields: string[];
    before_values: Record<string, unknown> | null;
    after_values: Record<string, unknown> | null;
    ip_address: string | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface UserLogsPageProps {
    logs: Paginator<UserLogEntry>;
    filters: {
        date_from: string | null;
        date_to: string | null;
        action: ActionValue | null;
        subject_type: string | null;
        user_id: number | null;
        search: string | null;
    };
    actions: Array<{ value: ActionValue; label: string }>;
    subjectTypes: Array<{ value: string; label: string }>;
    users: Array<{ value: number; label: string }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'User logs', href: '/admin/user-logs' },
];

const actionStyles: Record<ActionValue, string> = {
    CREATED: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    UPDATED: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
    DELETED: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
};

function displayDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function displayValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? 'None' : value.join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function fieldLabel(field: string): string {
    return field
        .replace(/_/g, ' ')
        .replace(/\bid\b/gi, 'ID')
        .replace(/^./, (character) => character.toUpperCase());
}

function ChangeDetail({ log, onClose }: { log: UserLogEntry | null; onClose: () => void }) {
    const fields = [...new Set([...Object.keys(log?.before_values ?? {}), ...Object.keys(log?.after_values ?? {})])];

    return (
        <Dialog open={log !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[80vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {log?.action_label} · {log?.subject_type_label}
                    </DialogTitle>
                    <DialogDescription>
                        {log?.subject_label} — by {log?.user.name} on {log ? displayDateTime(log.occurred_at) : ''}
                        {log?.ip_address ? ` from ${log.ip_address}` : ''}
                    </DialogDescription>
                </DialogHeader>

                {fields.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No field-level detail was captured for this change.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Field</TableHead>
                                    <TableHead>Before</TableHead>
                                    <TableHead>After</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {fields.map((field) => (
                                    <TableRow key={field}>
                                        <TableCell className="font-medium">{fieldLabel(field)}</TableCell>
                                        <TableCell className="text-muted-foreground break-all">
                                            {displayValue(log?.before_values?.[field])}
                                        </TableCell>
                                        <TableCell className="break-all font-medium">{displayValue(log?.after_values?.[field])}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function UserLogs({ logs, filters, actions, subjectTypes, users }: UserLogsPageProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [action, setAction] = useState(filters.action ?? 'ALL');
    const [subjectType, setSubjectType] = useState(filters.subject_type ?? 'ALL');
    const [userId, setUserId] = useState(filters.user_id ? String(filters.user_id) : 'ALL');
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedLog, setSelectedLog] = useState<UserLogEntry | null>(null);

    function applyFilters(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        router.get(
            '/admin/user-logs',
            {
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                action: action === 'ALL' ? undefined : action,
                subject_type: subjectType === 'ALL' ? undefined : subjectType,
                user_id: userId === 'ALL' ? undefined : userId,
                search: search.trim() || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function resetFilters(): void {
        setDateFrom('');
        setDateTo('');
        setAction('ALL');
        setSubjectType('ALL');
        setUserId('ALL');
        setSearch('');
        router.get('/admin/user-logs', {}, { preserveScroll: true, replace: true });
    }

    function goTo(url: string | null): void {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User logs" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-8">
                <div>
                    <p className="text-brand-blue text-xs font-semibold tracking-[0.16em] uppercase">Audit trail</p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">User logs</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Every create, update and delete a user applies to accounts, employees, departments, vehicles, drivers, routes, schedules and
                        booking settings. Records here can never be edited or removed.
                    </p>
                </div>

                <Card>
                    <CardContent className="p-4 md:p-5">
                        <form onSubmit={applyFilters} className="grid gap-4 lg:grid-cols-6">
                            <div className="grid gap-2">
                                <Label htmlFor="date-from">From</Label>
                                <Input id="date-from" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="date-to">To</Label>
                                <Input id="date-to" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="action">Action</Label>
                                <Select value={action} onValueChange={setAction}>
                                    <SelectTrigger id="action">
                                        <SelectValue placeholder="All actions" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ALL">All actions</SelectItem>
                                        {actions.map((item) => (
                                            <SelectItem key={item.value} value={item.value}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="subject-type">Record type</Label>
                                <Select value={subjectType} onValueChange={setSubjectType}>
                                    <SelectTrigger id="subject-type">
                                        <SelectValue placeholder="All records" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ALL">All records</SelectItem>
                                        {subjectTypes.map((item) => (
                                            <SelectItem key={item.value} value={item.value}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user">User</Label>
                                <Select value={userId} onValueChange={setUserId}>
                                    <SelectTrigger id="user">
                                        <SelectValue placeholder="Everyone" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ALL">Everyone</SelectItem>
                                        {users.map((item) => (
                                            <SelectItem key={item.value} value={String(item.value)}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="search">Search</Label>
                                <Input
                                    id="search"
                                    placeholder="Record or user"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-2 lg:col-span-6">
                                <Button type="submit">
                                    <Search />
                                    Apply filters
                                </Button>
                                <Button type="button" variant="outline" onClick={resetFilters}>
                                    <RotateCcw />
                                    Reset
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        {logs.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                                <span className="bg-muted mb-4 flex size-14 items-center justify-center rounded-2xl">
                                    <History className="text-muted-foreground size-6" />
                                </span>
                                <h2 className="text-lg font-semibold">No activity recorded</h2>
                                <p className="text-muted-foreground mt-1 max-w-md text-sm leading-6">
                                    Nothing matches these filters yet. User changes appear here as soon as they are made.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>When</TableHead>
                                            <TableHead>User</TableHead>
                                            <TableHead>Action</TableHead>
                                            <TableHead>Record type</TableHead>
                                            <TableHead>Record</TableHead>
                                            <TableHead>Fields changed</TableHead>
                                            <TableHead className="text-right">Detail</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {logs.data.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell className="whitespace-nowrap">{displayDateTime(log.occurred_at)}</TableCell>
                                                <TableCell>
                                                    <p className="font-medium">{log.user.name}</p>
                                                    {log.user.email && <p className="text-muted-foreground text-xs">{log.user.email}</p>}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className={actionStyles[log.action]}>
                                                        {log.action_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">{log.subject_type_label}</TableCell>
                                                <TableCell className="max-w-xs truncate font-medium">{log.subject_label}</TableCell>
                                                <TableCell className="text-muted-foreground max-w-sm text-xs">
                                                    {log.changed_fields.length === 0 ? '—' : log.changed_fields.join(', ')}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button type="button" variant="outline" size="sm" onClick={() => setSelectedLog(log)}>
                                                        View
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {logs.total > 0 && (
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p className="text-muted-foreground text-sm">
                            Showing {logs.from ?? 0}–{logs.to ?? 0} of {logs.total} record{logs.total === 1 ? '' : 's'}
                        </p>
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" disabled={!logs.prev_page_url} onClick={() => goTo(logs.prev_page_url)}>
                                Previous
                            </Button>
                            <span className="text-muted-foreground text-sm">
                                Page {logs.current_page} of {logs.last_page}
                            </span>
                            <Button type="button" variant="outline" disabled={!logs.next_page_url} onClick={() => goTo(logs.next_page_url)}>
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ChangeDetail log={selectedLog} onClose={() => setSelectedLog(null)} />
        </AppLayout>
    );
}
