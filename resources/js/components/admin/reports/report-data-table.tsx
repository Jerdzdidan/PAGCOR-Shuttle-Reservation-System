import type { AttendanceStatus, ServiceLifecycleStatus } from '@/components/admin/service-operation-types';
import { AttendanceStatusBadge, ServiceStatusBadge } from '@/components/admin/service-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, ArrowUpDown, SearchX } from 'lucide-react';

export type ReportColumnType =
    | 'text'
    | 'numeric'
    | 'decimal'
    | 'percent'
    | 'date'
    | 'time'
    | 'datetime'
    | 'status'
    | 'attendance'
    | 'badge'
    | 'notes';

export interface ReportColumn {
    key: string;
    label: string;
    type: ReportColumnType;
}

export interface ReportRows {
    data: Record<string, string | number | null>[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface ReportSort {
    key: string | null;
    direction: 'asc' | 'desc';
}

interface ReportDataTableProps {
    columns: ReportColumn[];
    rows: ReportRows;
    sort: ReportSort;
    onPageChange: (page: number) => void;
    onSortChange: (key: string) => void;
    onReset: () => void;
}

const serviceStatuses = new Set(['SCHEDULED', 'AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED']);
const attendanceStatuses = new Set(['PENDING', 'BOARDED', 'NO_SHOW', 'SERVICE_NOT_OPERATED']);

/** Turns SCREAMING_SNAKE enum values into readable labels. */
function humanize(value: string): string {
    return value
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatNumber(value: string | number): string {
    const numeric = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(numeric) ? numeric.toLocaleString('en-PH') : String(value);
}

function formatDate(value: string): string {
    const parsed = new Date(`${value.slice(0, 10)}T00:00:00`);

    return Number.isNaN(parsed.getTime())
        ? value
        : new Intl.DateTimeFormat('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(parsed);
}

function formatDateTime(value: string): string {
    const parsed = new Date(value.includes('T') ? value : value.replace(' ', 'T'));

    return Number.isNaN(parsed.getTime())
        ? value
        : new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(parsed);
}

function formatTime(value: string): string {
    const [hourValue, minute = '00'] = value.split(':');
    const hour = Number(hourValue);

    return Number.isFinite(hour) ? `${hour % 12 || 12}:${minute} ${hour >= 12 ? 'PM' : 'AM'}` : value;
}

function isNumericColumn(type: ReportColumnType): boolean {
    return type === 'numeric' || type === 'decimal' || type === 'percent';
}

function ReportCell({ column, value }: { column: ReportColumn; value: string | number | null }) {
    if (value === null || value === '' || value === '—') {
        return <span className="text-muted-foreground">—</span>;
    }

    const text = String(value);

    switch (column.type) {
        case 'status':
            return serviceStatuses.has(text) ? (
                <ServiceStatusBadge status={text as ServiceLifecycleStatus} />
            ) : (
                <Badge variant="outline">{humanize(text)}</Badge>
            );
        case 'attendance':
            return attendanceStatuses.has(text) ? (
                <AttendanceStatusBadge status={text as AttendanceStatus} />
            ) : (
                <Badge variant="outline">{humanize(text)}</Badge>
            );
        case 'badge':
            return (
                <Badge variant="secondary" className="font-normal">
                    {humanize(text)}
                </Badge>
            );
        case 'numeric':
        case 'decimal':
            return <span className="tabular-nums">{formatNumber(value)}</span>;
        case 'percent':
            return <span className="tabular-nums">{text}</span>;
        case 'date':
            return <span className="whitespace-nowrap">{formatDate(text)}</span>;
        case 'datetime':
            return <span className="whitespace-nowrap">{formatDateTime(text)}</span>;
        case 'time':
            return <span className="whitespace-nowrap tabular-nums">{formatTime(text)}</span>;
        case 'notes':
            return (
                <span className="block max-w-56 truncate" title={text}>
                    {text}
                </span>
            );
        default:
            return <span>{text}</span>;
    }
}

function PageNumbers({ rows, onPageChange }: { rows: ReportRows; onPageChange: (page: number) => void }) {
    const current = rows.current_page;
    const last = rows.last_page;
    const pages = new Set<number>([1, last, current, current - 1, current + 1]);
    const visible = [...pages].filter((page) => page >= 1 && page <= last).sort((left, right) => left - right);

    return (
        <div className="flex flex-wrap items-center gap-1">
            <Button type="button" size="sm" variant="outline" disabled={current <= 1} onClick={() => onPageChange(current - 1)}>
                Previous
            </Button>
            {visible.map((page, index) => (
                <span key={page} className="flex items-center gap-1">
                    {index > 0 && visible[index - 1] !== page - 1 && <span className="text-muted-foreground px-1">…</span>}
                    <Button
                        type="button"
                        size="sm"
                        variant={page === current ? 'default' : 'outline'}
                        className="min-w-9"
                        onClick={() => onPageChange(page)}
                    >
                        {page}
                    </Button>
                </span>
            ))}
            <Button type="button" size="sm" variant="outline" disabled={current >= last} onClick={() => onPageChange(current + 1)}>
                Next
            </Button>
        </div>
    );
}

export function ReportDataTable({ columns, rows, sort, onPageChange, onSortChange, onReset }: ReportDataTableProps) {
    if (rows.total === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-16 text-center">
                <span className="bg-muted mb-4 flex size-14 items-center justify-center rounded-2xl">
                    <SearchX className="text-muted-foreground size-6" />
                </span>
                <h3 className="text-lg font-semibold">No records in this period</h3>
                <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-6">
                    Nothing matches the current date range and filters. Try widening the dates or clearing a filter.
                </p>
                <Button type="button" variant="outline" className="mt-5 print:hidden" onClick={onReset}>
                    Reset filters
                </Button>
            </div>
        );
    }

    return (
        <div className="min-w-0 space-y-4">
            <div className="overflow-x-auto rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => {
                                const isSorted = sort.key === column.key;
                                const SortIcon = isSorted ? (sort.direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown;

                                return (
                                    <TableHead key={column.key} className={cn(isNumericColumn(column.type) && 'text-right')}>
                                        <button
                                            type="button"
                                            onClick={() => onSortChange(column.key)}
                                            className={cn(
                                                'hover:text-foreground inline-flex items-center gap-1.5 whitespace-nowrap transition-colors print:hidden',
                                                isSorted && 'text-foreground font-semibold',
                                            )}
                                        >
                                            {column.label}
                                            <SortIcon className={cn('size-3.5', isSorted ? 'opacity-100' : 'opacity-40')} />
                                        </button>
                                        <span className="hidden print:inline">{column.label}</span>
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.data.map((row, index) => (
                            <TableRow key={`${rows.current_page}-${index}`}>
                                {columns.map((column) => (
                                    <TableCell key={column.key} className={cn(isNumericColumn(column.type) && 'text-right')}>
                                        <ReportCell column={column} value={row[column.key] ?? null} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <div className="text-muted-foreground flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between print:hidden">
                <span>
                    Showing {rows.from?.toLocaleString('en-PH')}–{rows.to?.toLocaleString('en-PH')} of {rows.total.toLocaleString('en-PH')} records
                </span>
                <PageNumbers rows={rows} onPageChange={onPageChange} />
            </div>
        </div>
    );
}
