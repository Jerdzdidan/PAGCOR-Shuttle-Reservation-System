import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';

export interface SwitcherReport {
    key: string;
    slug: string;
    url: string;
    title: string;
    category_label: string;
}

export function ReportSwitcher({ reports, currentKey }: { reports: SwitcherReport[]; currentKey: string }) {
    const current = reports.find((report) => report.key === currentKey);
    const categories = [...new Set(reports.map((report) => report.category_label))];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="justify-between gap-2 sm:min-w-56">
                    <span className="truncate">{current?.title ?? 'Select report'}</span>
                    <ChevronsUpDown className="size-4 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-h-96 w-72 overflow-y-auto">
                {categories.map((category, index) => (
                    <div key={category}>
                        {index > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-muted-foreground text-xs tracking-wider uppercase">{category}</DropdownMenuLabel>
                        {reports
                            .filter((report) => report.category_label === category)
                            .map((report) => (
                                <DropdownMenuItem
                                    key={report.key}
                                    onClick={() => router.get(report.url, {}, { preserveScroll: true })}
                                    className="gap-2"
                                >
                                    <Check className={report.key === currentKey ? 'size-4 opacity-100' : 'size-4 opacity-0'} />
                                    <span className="truncate">{report.title}</span>
                                </DropdownMenuItem>
                            ))}
                    </div>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
