import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, MoreHorizontal, Plus, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type SortKey = 'name' | 'email' | 'created_at';

type ManagedUser = Pick<User, 'id' | 'name' | 'email' | 'email_verified_at' | 'created_at'>;

interface UsersPageProps extends SharedData {
    users: ManagedUser[];
}

type UserFormData = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
];

const emptyForm: UserFormData = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
};

function SortButton({
    label,
    column,
    sortKey,
    direction,
    onSort,
}: {
    label: string;
    column: SortKey;
    sortKey: SortKey;
    direction: 'asc' | 'desc';
    onSort: (column: SortKey) => void;
}) {
    const icon =
        sortKey !== column ? (
            <ArrowUpDown className="size-3.5" />
        ) : direction === 'asc' ? (
            <ArrowUp className="size-3.5" />
        ) : (
            <ArrowDown className="size-3.5" />
        );

    return (
        <Button type="button" variant="ghost" size="sm" className="-ml-3 h-8 gap-1.5" onClick={() => onSort(column)}>
            {label}
            {icon}
        </Button>
    );
}

export default function UsersPage({ users }: UsersPageProps) {
    const { auth } = usePage<SharedData>().props;
    const [formOpen, setFormOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [deletingUser, setDeletingUser] = useState<ManagedUser | null>(null);
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<SortKey>('created_at');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const [currentPage, setCurrentPage] = useState(1);
    const pageSize = 10;
    const form = useForm<UserFormData>(emptyForm);
    const deleteForm = useForm<Record<string, never>>({});

    const filteredUsers = useMemo(() => {
        const query = search.trim().toLowerCase();
        const matchingUsers = users.filter((user) => {
            if (!query) {
                return true;
            }

            return `${user.name} ${user.email}`.toLowerCase().includes(query);
        });

        return [...matchingUsers].sort((left, right) => {
            const leftValue = left[sortKey] ?? '';
            const rightValue = right[sortKey] ?? '';
            const comparison = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true });

            return sortDirection === 'asc' ? comparison : -comparison;
        });
    }, [search, sortDirection, sortKey, users]);

    const pageCount = Math.max(1, Math.ceil(filteredUsers.length / pageSize));
    const pageUsers = filteredUsers.slice((currentPage - 1) * pageSize, currentPage * pageSize);

    useEffect(() => {
        setCurrentPage(1);
    }, [search]);

    useEffect(() => {
        if (currentPage > pageCount) {
            setCurrentPage(pageCount);
        }
    }, [currentPage, pageCount]);

    const openCreate = () => {
        setEditingUser(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (user: ManagedUser) => {
        setEditingUser(user);
        form.setData({
            name: user.name,
            email: user.email,
            password: '',
            password_confirmation: '',
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const closeForm = () => {
        if (!form.processing) {
            setFormOpen(false);
            form.clearErrors();
        }
    };

    const submitForm = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingUser) {
            form.put(`/admin/users/${editingUser.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setFormOpen(false);
                    form.reset();
                    toast.success('Administrator updated successfully.');
                },
            });

            return;
        }

        form.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                form.reset();
                toast.success('Administrator created successfully.');
            },
        });
    };

    const confirmDelete = () => {
        if (!deletingUser) {
            return;
        }

        deleteForm.delete(`/admin/users/${deletingUser.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeletingUser(null);
                toast.success('Administrator deleted successfully.');
            },
        });
    };

    const toggleSort = (column: SortKey) => {
        if (sortKey === column) {
            setSortDirection((current) => (current === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(column);
            setSortDirection('asc');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User management" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">User management</h1>
                        <p className="text-muted-foreground">Create, update, and manage administrator accounts.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus />
                        Add administrator
                    </Button>
                </div>

                <div className="bg-card text-card-foreground rounded-lg border shadow-xs">
                    <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search administrators..."
                                className="pl-9"
                                aria-label="Search administrators"
                            />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {filteredUsers.length} {filteredUsers.length === 1 ? 'administrator' : 'administrators'}
                        </p>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <SortButton label="Name" column="name" sortKey={sortKey} direction={sortDirection} onSort={toggleSort} />
                                </TableHead>
                                <TableHead>
                                    <SortButton label="Email" column="email" sortKey={sortKey} direction={sortDirection} onSort={toggleSort} />
                                </TableHead>
                                <TableHead>
                                    <SortButton label="Created" column="created_at" sortKey={sortKey} direction={sortDirection} onSort={toggleSort} />
                                </TableHead>
                                <TableHead className="w-12">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pageUsers.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="h-32 text-center">
                                        <p className="font-medium">No administrators found</p>
                                        <p className="text-muted-foreground mt-1 text-sm">Try a different search or add an administrator.</p>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                pageUsers.map((user) => {
                                    const isCurrentUser = user.id === auth.user.id;

                                    return (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">{user.name}</TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                {new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(user.created_at))}
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon" aria-label={`Actions for ${user.name}`}>
                                                            <MoreHorizontal />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuItem onSelect={() => openEdit(user)}>Edit administrator</DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            disabled={isCurrentUser}
                                                            className="text-destructive focus:text-destructive"
                                                            onSelect={() => setDeletingUser(user)}
                                                        >
                                                            Delete administrator
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>

                    <div className="flex items-center justify-between border-t p-4">
                        <p className="text-muted-foreground text-sm">
                            {filteredUsers.length === 0
                                ? '0'
                                : `${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, filteredUsers.length)}`}{' '}
                            of {filteredUsers.length}
                        </p>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={() => setCurrentPage((page) => page - 1)} disabled={currentPage === 1}>
                                <ChevronLeft />
                                Previous
                            </Button>
                            <span className="text-muted-foreground text-sm">
                                Page {currentPage} of {pageCount}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage((page) => page + 1)}
                                disabled={currentPage === pageCount}
                            >
                                Next
                                <ChevronRight />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog open={formOpen} onOpenChange={(open) => (open ? setFormOpen(true) : closeForm())}>
                <DialogContent className="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle>{editingUser ? 'Edit administrator' : 'Add administrator'}</DialogTitle>
                        <DialogDescription>
                            {editingUser
                                ? 'Update this administrator account.'
                                : 'Create an administrator account for the shuttle reservation system.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitForm} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="user-name">Name</Label>
                            <Input
                                id="user-name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                autoComplete="name"
                            />
                            {form.errors.name && <p className="text-destructive text-sm">{form.errors.name}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="user-email">Email</Label>
                            <Input
                                id="user-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                autoComplete="email"
                            />
                            {form.errors.email && <p className="text-destructive text-sm">{form.errors.email}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="user-password">
                                Password {editingUser && <span className="text-muted-foreground font-normal">(leave blank to keep current)</span>}
                            </Label>
                            <Input
                                id="user-password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                autoComplete="new-password"
                            />
                            {form.errors.password && <p className="text-destructive text-sm">{form.errors.password}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="user-password-confirmation">Confirm password</Label>
                            <Input
                                id="user-password-confirmation"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                autoComplete="new-password"
                            />
                            {form.errors.password_confirmation && <p className="text-destructive text-sm">{form.errors.password_confirmation}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeForm} disabled={form.processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving...' : editingUser ? 'Save changes' : 'Create administrator'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={deletingUser !== null} onOpenChange={(open) => !open && setDeletingUser(null)}>
                <DialogContent className="sm:max-w-[440px]">
                    <DialogHeader>
                        <DialogTitle>Delete administrator?</DialogTitle>
                        <DialogDescription>
                            This permanently removes {deletingUser?.name ?? 'this administrator'} and their account access. This action cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteForm.errors.user && <p className="text-destructive text-sm">{deleteForm.errors.user}</p>}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeletingUser(null)} disabled={deleteForm.processing}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete} disabled={deleteForm.processing}>
                            {deleteForm.processing ? 'Deleting...' : 'Delete administrator'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
