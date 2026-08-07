import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart3 } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface ReportChart {
    label: string;
    /** `timeseries` plots one bar per day; `ranking` plots the top performers. */
    kind: 'timeseries' | 'ranking';
    data: { label: string; value: number }[];
}

function formatDayTick(value: string): string {
    const parsed = new Date(`${value.slice(0, 10)}T00:00:00`);

    return Number.isNaN(parsed.getTime()) ? value : new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' }).format(parsed);
}

function ChartTooltip({
    active,
    payload,
    label,
    isTimeseries,
}: {
    active?: boolean;
    payload?: { value?: number }[];
    label?: string;
    isTimeseries: boolean;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-md">
            <p className="font-medium">{isTimeseries && label ? formatDayTick(label) : label}</p>
            <p className="text-muted-foreground mt-0.5 tabular-nums">{(payload[0]?.value ?? 0).toLocaleString('en-PH')}</p>
        </div>
    );
}

export function ReportTrendChart({ chart }: { chart: ReportChart }) {
    const isTimeseries = chart.kind === 'timeseries';

    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{chart.label}</CardTitle>
            </CardHeader>
            <CardContent className="h-64">
                {chart.data.length === 0 ? (
                    <div className="text-muted-foreground flex h-full flex-col items-center justify-center gap-2 text-sm">
                        <BarChart3 className="size-5 opacity-50" />
                        Nothing to plot for this period.
                    </div>
                ) : (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chart.data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
                            <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="var(--border)" />
                            <XAxis
                                dataKey="label"
                                tickLine={false}
                                axisLine={false}
                                minTickGap={16}
                                tickMargin={8}
                                tick={{ fontSize: 11 }}
                                tickFormatter={(value: string) => (isTimeseries ? formatDayTick(value) : value)}
                            />
                            <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} tick={{ fontSize: 11 }} />
                            <Tooltip cursor={{ fill: 'var(--muted)', opacity: 0.4 }} content={<ChartTooltip isTimeseries={isTimeseries} />} />
                            <Bar dataKey="value" fill="var(--primary)" radius={[4, 4, 0, 0]} maxBarSize={56} />
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
