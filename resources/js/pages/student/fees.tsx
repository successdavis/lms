import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fees & Payments', href: '/student/fees' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface Invoice {
    id: number;
    number: string;
    total: string;
    amount_paid: string;
    status: string;
    academic_session: { name: string };
    items: { id: number; description: string; amount: string }[];
    payments: { id: number; reference: string; amount: string; status: string; gateway: string; paid_at: string | null }[];
}

interface Props {
    invoices: Invoice[];
    session: { name: string } | null;
    hasCurrentInvoice: boolean;
}

export default function Fees({ invoices, session, hasCurrentInvoice }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fees & Payments" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                {session && !hasCurrentInvoice && (
                    <Card>
                        <CardContent className="flex items-center justify-between py-4">
                            <p className="text-sm">No invoice yet for the {session.name} session.</p>
                            <Button onClick={() => router.post('/student/fees/generate')}>Generate invoice</Button>
                        </CardContent>
                    </Card>
                )}

                {invoices.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center">No invoices yet.</CardContent>
                    </Card>
                )}

                {invoices.map((invoice) => (
                    <Card key={invoice.id}>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                {invoice.number}
                                <span className="text-muted-foreground text-sm font-normal">{invoice.academic_session.name}</span>
                                <Badge variant={invoice.status === 'paid' ? 'default' : invoice.status === 'part_paid' ? 'outline' : 'destructive'}>
                                    {invoice.status}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <table className="w-full text-sm">
                                <tbody>
                                    {invoice.items.map((item) => (
                                        <tr key={item.id} className="border-b last:border-0">
                                            <td className="py-1.5">{item.description}</td>
                                            <td className="py-1.5 text-right">{naira(item.amount)}</td>
                                        </tr>
                                    ))}
                                    <tr className="font-medium">
                                        <td className="py-1.5">Total</td>
                                        <td className="py-1.5 text-right">{naira(invoice.total)}</td>
                                    </tr>
                                    <tr className="text-muted-foreground">
                                        <td className="py-1.5">Paid</td>
                                        <td className="py-1.5 text-right">{naira(invoice.amount_paid)}</td>
                                    </tr>
                                </tbody>
                            </table>

                            {invoice.status !== 'paid' && (
                                <Button onClick={() => router.post(`/student/fees/${invoice.id}/pay`)}>
                                    Pay {naira(Number(invoice.total) - Number(invoice.amount_paid))}
                                </Button>
                            )}

                            {invoice.payments.length > 0 && (
                                <div>
                                    <div className="text-muted-foreground mb-1 text-xs font-medium">Payments</div>
                                    <ul className="space-y-1 text-sm">
                                        {invoice.payments.map((payment) => (
                                            <li key={payment.id} className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-xs">{payment.reference}</span>
                                                <span>{naira(payment.amount)}</span>
                                                <Badge variant={payment.status === 'successful' ? 'default' : 'outline'}>{payment.status}</Badge>
                                                <span className="text-muted-foreground text-xs">
                                                    {payment.gateway}
                                                    {payment.paid_at ? ` · ${new Date(payment.paid_at).toLocaleString()}` : ''}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
