import { Card, CardContent } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Info } from 'lucide-react';

export interface ReportKpi {
    label: string;
    value: string | number;
    hint?: string;
}

/** Keeps 5, 6 and 7 KPIs from orphaning a single tile on the last row. */
function gridClasses(count: number): string {
    if (count <= 4) {
        return 'sm:grid-cols-2 lg:grid-cols-4';
    }

    if (count % 3 === 0) {
        return 'sm:grid-cols-2 lg:grid-cols-3';
    }

    return 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6';
}

export function ReportKpiCards({ kpis }: { kpis: ReportKpi[] }) {
    if (kpis.length === 0) {
        return null;
    }

    return (
        <div className={cn('grid gap-3', gridClasses(kpis.length))}>
            {kpis.map((kpi) => (
                <Card key={kpi.label} className="min-w-0">
                    <CardContent className="p-4">
                        <div className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                            <span className="truncate">{kpi.label}</span>
                            {kpi.hint && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <button type="button" aria-label={`What ${kpi.label} means`} className="shrink-0 print:hidden">
                                            <Info className="size-3.5 opacity-60 transition-opacity hover:opacity-100" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent className="max-w-64 text-xs leading-5">{kpi.hint}</TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                        <p className="mt-1.5 text-2xl font-semibold tabular-nums">{kpi.value}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
