import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, BusFront, ClipboardCheck, ShieldCheck, type LucideIcon } from 'lucide-react';

interface CatalogReport {
    key: string;
    slug: string;
    url: string;
    title: string;
    answers: string;
    description: string;
    columns: string[];
}

interface CatalogGroup {
    key: string;
    label: string;
    description: string;
    reports: CatalogReport[];
}

interface ReportsIndexProps {
    groups: CatalogGroup[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
];

const groupIcons: Record<string, LucideIcon> = {
    operations: ClipboardCheck,
    ridership: BarChart3,
    assets: BusFront,
    audit: ShieldCheck,
};

function ReportCard({ report }: { report: CatalogReport }) {
    return (
        <Link
            href={report.url}
            prefetch
            className="group focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden"
        >
            <Card className="hover:border-primary/40 h-full transition-all hover:-translate-y-0.5 hover:shadow-md">
                <CardContent className="flex h-full flex-col gap-3 p-5">
                    <div className="flex items-start justify-between gap-3">
                        <h3 className="group-hover:text-primary font-semibold transition-colors">{report.title}</h3>
                        <ArrowRight className="text-muted-foreground group-hover:text-primary mt-0.5 size-4 shrink-0 transition-transform group-hover:translate-x-0.5" />
                    </div>
                    <p className="text-sm leading-6 font-medium">{report.answers}</p>
                    <p className="text-muted-foreground text-xs leading-5">{report.description}</p>
                    <div className="mt-auto flex flex-wrap gap-1.5 pt-1">
                        {report.columns.map((column) => (
                            <Badge key={column} variant="secondary" className="font-normal">
                                {column}
                            </Badge>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function ReportsIndex({ groups }: ReportsIndexProps) {
    const reportCount = groups.reduce((total, group) => total + group.reports.length, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
                    <p className="text-muted-foreground mt-1 max-w-2xl">
                        {reportCount} reports grouped by the question they answer. Every report can be filtered by date and exported to CSV, Excel, or
                        PDF.
                    </p>
                </div>

                {groups.map((group) => {
                    const Icon = groupIcons[group.key] ?? BarChart3;

                    return (
                        <section key={group.key} className="space-y-4">
                            <div className="flex items-start gap-3">
                                <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl">
                                    <Icon className="size-4.5" />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="font-semibold">{group.label}</h2>
                                    <p className="text-muted-foreground text-sm">{group.description}</p>
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {group.reports.map((report) => (
                                    <ReportCard key={report.key} report={report} />
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>
        </AppLayout>
    );
}
