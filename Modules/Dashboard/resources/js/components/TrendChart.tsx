import { Bar, CartesianGrid, ComposedChart, Legend, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface Point {
    date: string;
    match_rate: number | null;
    exceptions: number | null;
}

export function TrendChart({ points }: { points: Point[] }) {
    const data = points.map((p) => ({ ...p, label: p.date.slice(5) }));

    return (
        <div className="h-72 w-full">
            <ResponsiveContainer>
                <ComposedChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
                    <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                    <YAxis yAxisId="rate" domain={[80, 100]} tick={{ fontSize: 12 }} unit="%" width={48} />
                    <YAxis yAxisId="count" orientation="right" tick={{ fontSize: 12 }} width={40} allowDecimals={false} />
                    <Tooltip />
                    <Legend />
                    <Bar yAxisId="count" dataKey="exceptions" name="Exceptions" fill="var(--color-chart-2, #f59e0b)" radius={[3, 3, 0, 0]} />
                    <Line yAxisId="rate" type="monotone" dataKey="match_rate" name="Match rate %" stroke="var(--color-primary)" strokeWidth={2} dot={false} connectNulls />
                </ComposedChart>
            </ResponsiveContainer>
        </div>
    );
}
