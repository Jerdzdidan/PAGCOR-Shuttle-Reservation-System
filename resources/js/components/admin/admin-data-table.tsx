import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowDown, ArrowUp, ArrowUpDown, MoreHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface AdminTableColumn<T> {
    key: string;
    label: string;
    className?: string;
    render: (item: T) => React.ReactNode;
    sortValue?: (item: T) => string | number;
}

interface AdminDataTableProps<T extends object> {
    data: T[];
    columns: AdminTableColumn<T>[];
    searchPlaceholder: string;
    getSearchText: (item: T) => string;
    getRowKey?: (item: T) => React.Key;
    onView?: (item: T) => void;
    onEdit: (item: T) => void;
    onDelete: (item: T) => void;
    deleteDisabled?: (item: T) => boolean;
    filterOptions?: { value: string; label: string }[];
    getFilterValue?: (item: T) => string;
}

export function AdminDataTable<T extends object>({
    data,
    columns,
    searchPlaceholder,
    getSearchText,
    getRowKey,
    onView,
    onEdit,
    onDelete,
    deleteDisabled,
    filterOptions,
    getFilterValue,
}: AdminDataTableProps<T>) {
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
    const [page, setPage] = useState(1);
    const [filter, setFilter] = useState('ALL');
    const pageSize = 10;

    const filtered = useMemo(() => {
        const normalized = search.trim().toLowerCase();
        const matches = data.filter((item) => {
            const matchesSearch = !normalized || getSearchText(item).toLowerCase().includes(normalized);
            const matchesFilter = filter === 'ALL' || !getFilterValue || getFilterValue(item) === filter;

            return matchesSearch && matchesFilter;
        });
        const column = columns.find((candidate) => candidate.key === sortKey);

        if (column?.sortValue) {
            matches.sort((left, right) => {
                const leftValue = column.sortValue?.(left) ?? '';
                const rightValue = column.sortValue?.(right) ?? '';
                const comparison = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true });

                return sortDirection === 'asc' ? comparison : -comparison;
            });
        }

        return matches;
    }, [columns, data, filter, getFilterValue, getSearchText, search, sortDirection, sortKey]);

    const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
    const currentPage = Math.min(page, pageCount);
    const visibleRows = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);

    function changeSort(key: string): void {
        if (sortKey === key) {
            setSortDirection((direction) => (direction === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDirection('asc');
        }
        setPage(1);
    }

    function sortIcon(key: string): React.ReactNode {
        if (sortKey !== key) return <ArrowUpDown className="size-3.5" />;
        return sortDirection === 'asc' ? <ArrowUp className="size-3.5" /> : <ArrowDown className="size-3.5" />;
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 sm:flex-row">
                <Input
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setPage(1);
                    }}
                    placeholder={searchPlaceholder}
                    className="max-w-sm"
                />
                {filterOptions && getFilterValue && (
                    <Select
                        value={filter}
                        onValueChange={(value) => {
                            setFilter(value);
                            setPage(1);
                        }}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Filter status" />
                        </SelectTrigger>
                        <SelectContent>
                            {filterOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </div>
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead key={column.key} className={column.className}>
                                    {column.sortValue ? (
                                        <Button type="button" variant="ghost" size="sm" className="-ml-3 h-8" onClick={() => changeSort(column.key)}>
                                            {column.label}
                                            {sortIcon(column.key)}
                                        </Button>
                                    ) : (
                                        column.label
                                    )}
                                </TableHead>
                            ))}
                            <TableHead className="w-16 text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {visibleRows.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={columns.length + 1} className="text-muted-foreground h-24 text-center">
                                    No records found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            visibleRows.map((item) => (
                                <TableRow key={getRowKey ? getRowKey(item) : (item as { id: number }).id}>
                                    {columns.map((column) => (
                                        <TableCell key={column.key} className={column.className}>
                                            {column.render(item)}
                                        </TableCell>
                                    ))}
                                    <TableCell className="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button type="button" variant="ghost" size="icon" aria-label="Open actions">
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {onView && <DropdownMenuItem onClick={() => onView(item)}>View</DropdownMenuItem>}
                                                <DropdownMenuItem onClick={() => onEdit(item)}>Edit</DropdownMenuItem>
                                                <DropdownMenuItem disabled={deleteDisabled?.(item)} onClick={() => onDelete(item)}>
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="text-muted-foreground flex items-center justify-between text-sm">
                <span>
                    {filtered.length === 0 ? 0 : (currentPage - 1) * pageSize + 1}-{Math.min(currentPage * pageSize, filtered.length)} of{' '}
                    {filtered.length}
                </span>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={currentPage <= 1}
                        onClick={() => setPage((value) => Math.max(1, value - 1))}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={currentPage >= pageCount}
                        onClick={() => setPage((value) => Math.min(pageCount, value + 1))}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
