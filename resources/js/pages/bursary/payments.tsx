import { FlashMessages } from '@/components/portal/flash-messages';
import { StatCard } from '@/components/portal/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Payments', href: '/bursary/payments' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface PaymentRow {
    id: number;
    reference: string;
    rrr: string | null;
    amount: string;
    status: string;
    gateway: string;
    channel: string | null;
    paid_at: string | null;
    created_at: string;
    student: { matric_no: string | null; user: { name: string } };
    invoice: { number: string };
}

interface Props {
    payments: {
        data: PaymentRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { status: string; gateway: string; search: string };
    totals: { successful: string | number; pending: string | number };
}

export default function BursaryPayments({ payments, filters, totals }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const bankForm = useForm({ invoice_number: '', amount: '', reference: '' });

    const applyFilters = (overrides: Partial<Props['filters']> = {}) =>
        router.get('/bursary/payments', { ...filters, search, ...overrides }, { preserveState: true });

    const recordBankPayment = (e: React.FormEvent) => {
        e.preventDefault();
        bankForm.post('/bursary/payments', { onSuccess: () => bankForm.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="grid gap-4 md:grid-cols-2">
                    <StatCard label="Total collected (successful)" value={naira(totals.successful)} />
                    <StatCard
                        label="Awaiting confirmation (pending)"
                        value={naira(totals.pending)}
                        hint="Bank-branch and unverified gateway payments"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Record bank-teller payment</CardTitle>
                        <p className="text-muted-foreground text-sm">For deposits made at a bank branch against an invoice number.</p>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={recordBankPayment} className="grid gap-2 md:grid-cols-4">
                            <Input
                                placeholder="Invoice number"
                                value={bankForm.data.invoice_number}
                                onChange={(e) => bankForm.setData('invoice_number', e.target.value)}
                            />
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                placeholder="Amount (₦)"
                                value={bankForm.data.amount}
                                onChange={(e) => bankForm.setData('amount', e.target.value)}
                            />
                            <Input
                                placeholder="Teller/deposit slip no."
                                value={bankForm.data.reference}
                                onChange={(e) => bankForm.setData('reference', e.target.value)}
                            />
                            <Button type="submit" disabled={bankForm.processing}>
                                Record payment
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">All payments ({payments.total})</CardTitle>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                className="max-w-xs"
                                placeholder="Search reference, RRR, matric no…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            />
                            <select
                                className="bg-background rounded-md border px-2 text-sm"
                                value={filters.status}
                                onChange={(e) => applyFilters({ status: e.target.value })}
                            >
                                <option value="">Any status</option>
                                {['pending', 'successful', 'failed', 'reversed'].map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="bg-background rounded-md border px-2 text-sm"
                                value={filters.gateway}
                                onChange={(e) => applyFilters({ gateway: e.target.value })}
                            >
                                <option value="">Any gateway</option>
                                {['paystack', 'remita', 'flutterwave', 'interswitch', 'bank'].map((gateway) => (
                                    <option key={gateway} value={gateway}>
                                        {gateway}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left">
                                    <th className="py-2 pr-2">Reference</th>
                                    <th className="py-2 pr-2">Student</th>
                                    <th className="py-2 pr-2">Invoice</th>
                                    <th className="py-2 pr-2 text-right">Amount</th>
                                    <th className="py-2 pr-2">Gateway</th>
                                    <th className="py-2 pr-2">Status</th>
                                    <th className="py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((payment) => (
                                    <tr key={payment.id} className="border-b last:border-0">
                                        <td className="py-2 pr-2 font-mono text-xs">
                                            {payment.reference}
                                            {payment.rrr && <div className="text-muted-foreground">RRR {payment.rrr}</div>}
                                        </td>
                                        <td className="py-2 pr-2">
                                            <div>{payment.student.user.name}</div>
                                            <div className="text-muted-foreground font-mono text-xs">{payment.student.matric_no}</div>
                                        </td>
                                        <td className="py-2 pr-2 font-mono text-xs">{payment.invoice.number}</td>
                                        <td className="py-2 pr-2 text-right">{naira(payment.amount)}</td>
                                        <td className="py-2 pr-2 capitalize">{payment.gateway}</td>
                                        <td className="py-2 pr-2">
                                            <Badge
                                                variant={
                                                    payment.status === 'successful'
                                                        ? 'default'
                                                        : payment.status === 'pending'
                                                          ? 'outline'
                                                          : 'destructive'
                                                }
                                            >
                                                {payment.status}
                                            </Badge>
                                        </td>
                                        <td className="py-2">
                                            {payment.status === 'pending' && (
                                                <div className="flex gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => router.patch(`/bursary/payments/${payment.id}`, { action: 'confirm' })}
                                                    >
                                                        Confirm
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() => router.patch(`/bursary/payments/${payment.id}`, { action: 'fail' })}
                                                    >
                                                        Fail
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="flex flex-wrap gap-1 pt-4">
                            {payments.links.map((link, i) =>
                                link.url ? (
                                    <Button
                                        key={i}
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        onClick={() => router.get(link.url!)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : null,
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
