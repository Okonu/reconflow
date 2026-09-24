import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export function CategoryChart({ data, dataKey, nameKey }: { data: Record<string, string | number>[]; dataKey: string; nameKey: string }) {
    return (
        <div className="h-64 w-full">
            <ResponsiveContainer>
                <BarChart data={data} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 4 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="var(--color-border)" />
                    <XAxis type="number" allowDecimals={false} tick={{ fontSize: 12 }} />
                    <YAxis type="category" dataKey={nameKey} width={170} tick={{ fontSize: 12 }} />
                    <Tooltip />
                    <Bar dataKey={dataKey} fill="var(--color-primary)" radius={[0, 3, 3, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
