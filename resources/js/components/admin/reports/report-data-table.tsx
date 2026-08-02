import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export interface ReportColumn {
    key: string;
    label: string;
}
export interface ReportRows {
    data: Record<string, string | number | null>[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface ReportDataTableProps {
    columns: ReportColumn[];
    rows: ReportRows;
    onPageChange: (page: number) => void;
}

export function ReportDataTable({ columns, rows, onPageChange }: ReportDataTableProps) {
    return (
        <div className="min-w-0 space-y-4">
            <div className="overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead key={column.key}>{column.label}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="text-muted-foreground h-24 text-center">
                                    No records found for the selected filters.
                                </TableCell>
                            </TableRow>
                        ) : (
                            rows.data.map((row, index) => (
                                <TableRow key={`${rows.current_page}-${index}`}>
                                    {columns.map((column) => (
                                        <TableCell key={column.key}>{row[column.key] ?? '—'}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="text-muted-foreground flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between print:hidden">
                <span>{rows.total === 0 ? '0 records' : `${rows.from}-${rows.to} of ${rows.total}`}</span>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={rows.current_page <= 1}
                        onClick={() => onPageChange(rows.current_page - 1)}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={rows.current_page >= rows.last_page}
                        onClick={() => onPageChange(rows.current_page + 1)}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
