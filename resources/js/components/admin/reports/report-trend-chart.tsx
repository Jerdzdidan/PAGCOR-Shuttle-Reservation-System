import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface ReportTrendChartProps {
    chart: { label: string; data: { label: string; value: number }[] };
}

export function ReportTrendChart({ chart }: ReportTrendChartProps) {
    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader>
                <CardTitle className="text-base">{chart.label}</CardTitle>
            </CardHeader>
            <CardContent className="h-72">
                {chart.data.length === 0 ? (
                    <div className="text-muted-foreground flex h-full items-center justify-center text-sm">
                        No chart data for the selected filters.
                    </div>
                ) : (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chart.data}>
                            <CartesianGrid vertical={false} strokeDasharray="3 3" />
                            <XAxis dataKey="label" tickLine={false} axisLine={false} minTickGap={24} />
                            <YAxis allowDecimals={false} tickLine={false} axisLine={false} />
                            <Tooltip />
                            <Bar dataKey="value" fill="var(--primary)" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
