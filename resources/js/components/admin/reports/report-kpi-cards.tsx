import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ReportKpiCardsProps {
    kpis: { label: string; value: string | number }[];
}

export function ReportKpiCards({ kpis }: ReportKpiCardsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {kpis.map((kpi) => (
                <Card key={kpi.label}>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">{kpi.label}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-2xl font-semibold tabular-nums">{kpi.value}</CardContent>
                </Card>
            ))}
        </div>
    );
}
