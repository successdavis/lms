import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Transcripts', href: '/student/transcripts' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface TranscriptRow {
    id: number;
    number: string;
    destination: string;
    delivery: string;
    recipient_name: string;
    fee: string;
    status: string;
    dispatch_reference: string | null;
    dispatched_at: string | null;
    remarks: string | null;
    created_at: string;
    invoice: { id: number; number: string; status: string } | null;
}

interface Props {
    requests: TranscriptRow[];
    fees: Record<string, Record<string, number>>;
    hasApprovedResults: boolean;
}

const statusLabels: Record<string, string> = {
    awaiting_payment: 'awaiting payment',
    paid: 'in records queue',
    processing: 'being processed',
    dispatched: 'dispatched',
    rejected: 'rejected',
};

export default function Transcripts({ requests, fees, hasApprovedResults }: Props) {
    const form = useForm({
        destination: 'local',
        delivery: 'e_copy',
        recipient_name: '',
        recipient_email: '',
        recipient_address: '',
        purpose: '',
    });

    const fee = fees[form.data.destination]?.[form.data.delivery] ?? 0;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/student/transcripts', { onSuccess: () => form.reset('recipient_name', 'recipient_email', 'recipient_address', 'purpose') });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transcripts" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Request an official transcript</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Official transcripts are sent directly to the receiving institution or organisation, never to you. Only results approved
                            by Senate/Academic Board appear on it.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {!hasApprovedResults ? (
                            <p className="text-muted-foreground text-sm">You have no approved results yet, so a transcript cannot be issued.</p>
                        ) : (
                            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1">
                                    <Label>Destination</Label>
                                    <select
                                        className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                        value={form.data.destination}
                                        onChange={(e) => form.setData('destination', e.target.value)}
                                    >
                                        <option value="local">Within Nigeria</option>
                                        <option value="international">Outside Nigeria</option>
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label>Delivery</Label>
                                    <select
                                        className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                        value={form.data.delivery}
                                        onChange={(e) => form.setData('delivery', e.target.value)}
                                    >
                                        <option value="e_copy">E-copy (emailed to recipient)</option>
                                        <option value="hard_copy">Hard copy (courier)</option>
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label>Receiving institution / organisation</Label>
                                    <Input value={form.data.recipient_name} onChange={(e) => form.setData('recipient_name', e.target.value)} />
                                </div>
                                {form.data.delivery === 'e_copy' ? (
                                    <div className="space-y-1">
                                        <Label>Recipient's official email</Label>
                                        <Input
                                            type="email"
                                            value={form.data.recipient_email}
                                            onChange={(e) => form.setData('recipient_email', e.target.value)}
                                        />
                                    </div>
                                ) : (
                                    <div className="space-y-1">
                                        <Label>Recipient's postal address</Label>
                                        <Input
                                            value={form.data.recipient_address}
                                            onChange={(e) => form.setData('recipient_address', e.target.value)}
                                        />
                                    </div>
                                )}
                                <div className="space-y-1 md:col-span-2">
                                    <Label>Purpose (optional)</Label>
                                    <Input
                                        placeholder="e.g. Postgraduate admission"
                                        value={form.data.purpose}
                                        onChange={(e) => form.setData('purpose', e.target.value)}
                                    />
                                </div>
                                <div className="flex items-center gap-3 md:col-span-2">
                                    <Button type="submit" disabled={form.processing}>
                                        Request transcript
                                    </Button>
                                    <span className="text-sm">
                                        Fee: <span className="font-semibold">{naira(fee)}</span>
                                    </span>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My requests</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {requests.length === 0 && <p className="text-muted-foreground text-sm">No transcript requests yet.</p>}
                        {requests.map((request) => (
                            <div key={request.id} className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                                <span className="font-mono text-xs">{request.number}</span>
                                <span className="font-medium">{request.recipient_name}</span>
                                <span className="text-muted-foreground">
                                    {request.destination} · {request.delivery.replace('_', '-')} · {naira(request.fee)}
                                </span>
                                <Badge
                                    variant={request.status === 'dispatched' ? 'default' : request.status === 'rejected' ? 'destructive' : 'outline'}
                                >
                                    {statusLabels[request.status] ?? request.status}
                                </Badge>
                                {request.dispatch_reference && (
                                    <span className="text-muted-foreground text-xs">Ref: {request.dispatch_reference}</span>
                                )}
                                {request.remarks && <span className="text-destructive text-xs">{request.remarks}</span>}
                                {request.status === 'awaiting_payment' && request.invoice && (
                                    <Button size="sm" className="ml-auto" onClick={() => router.post(`/student/fees/${request.invoice!.id}/pay`)}>
                                        Pay {naira(request.fee)}
                                    </Button>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
