import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type ReactNode } from 'react';

export function StatCard({ label, value, hint }: { label: string; value: ReactNode; hint?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-muted-foreground text-sm font-medium">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold">{value}</div>
                {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
            </CardContent>
        </Card>
    );
}
