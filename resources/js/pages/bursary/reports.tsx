import { FlashMessages } from '@/components/portal/flash-messages';
import { StatCard } from '@/components/portal/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Revenue Reports', href: '/bursary/reports' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface Props {
    sessions: { id: number; name: string; is_current: boolean }[];
    selectedSession: number | null;
    summary: {
        invoiced: number;
        collected: number;
        outstanding: number;
        collectionRate: number;
        invoiceCount: number;
        fullyPaidCount: number;
    };
    byFeeType: { fee_type: string; invoiced: number; count: number }[];
    byGateway: { gateway: string; collected: number; count: number }[];
}

export default function BursaryReports({ sessions, selectedSession, summary, byFeeType, byGateway }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Revenue Reports" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="flex items-center gap-2">
                    <span className="text-sm">Session</span>
                    <select
                        className="bg-background rounded-md border px-2 py-1 text-sm"
                        value={selectedSession ?? ''}
                        onChange={(e) => router.get('/bursary/reports', { session: e.target.value }, { preserveState: true })}
                    >
                        {sessions.map((session) => (
                            <option key={session.id} value={session.id}>
                                {session.name}
                                {session.is_current ? ' (current)' : ''}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard label="Invoiced" value={naira(summary.invoiced)} hint={`${summary.invoiceCount} invoices`} />
                    <StatCard label="Collected" value={naira(summary.collected)} hint={`${summary.fullyPaidCount} fully paid`} />
                    <StatCard label="Outstanding" value={naira(summary.outstanding)} />
                    <StatCard label="Collection rate" value={`${summary.collectionRate}%`} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Invoiced by fee type</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <tbody>
                                    {byFeeType.map((row) => (
                                        <tr key={row.fee_type} className="border-b last:border-0">
                                            <td className="py-2">{row.fee_type}</td>
                                            <td className="text-muted-foreground py-2 text-right">{row.count}×</td>
                                            <td className="py-2 text-right font-medium">{naira(row.invoiced)}</td>
                                        </tr>
                                    ))}
                                    {byFeeType.length === 0 && (
                                        <tr>
                                            <td className="text-muted-foreground py-4 text-center">No invoices this session.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Collected by gateway</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <tbody>
                                    {byGateway.map((row) => (
                                        <tr key={row.gateway} className="border-b last:border-0">
                                            <td className="py-2 capitalize">{row.gateway}</td>
                                            <td className="text-muted-foreground py-2 text-right">{row.count}×</td>
                                            <td className="py-2 text-right font-medium">{naira(row.collected)}</td>
                                        </tr>
                                    ))}
                                    {byGateway.length === 0 && (
                                        <tr>
                                            <td className="text-muted-foreground py-4 text-center">No successful payments this session.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
