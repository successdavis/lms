import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Transcript Requests', href: '/records/transcripts' }];

interface Row {
    id: number;
    number: string;
    destination: string;
    delivery: string;
    recipient_name: string;
    recipient_email: string | null;
    recipient_address: string | null;
    purpose: string | null;
    status: string;
    dispatch_reference: string | null;
    created_at: string;
    student: { matric_no: string | null; user: { name: string }; programme: { name: string } };
    processed_by: { name: string } | null;
}

interface Props {
    requests: { data: Row[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    status: string;
    counts: Record<string, number>;
}

const tabs = ['paid', 'processing', 'dispatched', 'awaiting_payment', 'rejected', 'all'];

export default function RecordsTranscripts({ requests, status, counts }: Props) {
    const [refs, setRefs] = useState<Record<number, string>>({});

    const act = (row: Row, action: string, extra: Record<string, string> = {}) =>
        router.patch(`/records/transcripts/${row.id}`, { action, ...extra }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transcript Requests" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="flex flex-wrap gap-2">
                    {tabs.map((tab) => (
                        <Button
                            key={tab}
                            size="sm"
                            variant={status === tab ? 'default' : 'outline'}
                            onClick={() => router.get('/records/transcripts', { status: tab }, { preserveState: true })}
                        >
                            {tab.replace('_', ' ')}
                            {tab !== 'all' && counts[tab] ? ` (${counts[tab]})` : ''}
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Requests ({requests.total})</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Paid → mark processing (compiles from Senate-approved results) → download &amp; sign the PDF → dispatch with a courier or
                            email reference.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {requests.data.length === 0 && <p className="text-muted-foreground text-sm">Nothing here.</p>}
                        {requests.data.map((row) => (
                            <div key={row.id} className="space-y-2 rounded-lg border p-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-xs">{row.number}</span>
                                    <span className="font-medium">{row.student.user.name}</span>
                                    <span className="text-muted-foreground font-mono text-xs">{row.student.matric_no}</span>
                                    <Badge variant="outline">{row.status.replace('_', ' ')}</Badge>
                                    <span className="text-muted-foreground">
                                        {row.destination} · {row.delivery.replace('_', '-')}
                                    </span>
                                </div>
                                <div className="text-muted-foreground">
                                    To: <span className="text-foreground">{row.recipient_name}</span>
                                    {row.recipient_email ? ` <${row.recipient_email}>` : ''}
                                    {row.recipient_address ? ` — ${row.recipient_address}` : ''}
                                    {row.purpose ? ` · ${row.purpose}` : ''}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {row.status === 'paid' && (
                                        <Button size="sm" onClick={() => act(row, 'process')}>
                                            Mark processing
                                        </Button>
                                    )}
                                    {['processing', 'dispatched'].includes(row.status) && (
                                        <a href={`/records/transcripts/${row.id}/pdf`}>
                                            <Button size="sm" variant="outline">
                                                Download PDF
                                            </Button>
                                        </a>
                                    )}
                                    {row.status === 'processing' && (
                                        <>
                                            <Input
                                                className="h-8 w-56"
                                                placeholder="Courier tracking / email reference"
                                                value={refs[row.id] ?? ''}
                                                onChange={(e) => setRefs((prev) => ({ ...prev, [row.id]: e.target.value }))}
                                            />
                                            <Button
                                                size="sm"
                                                disabled={!refs[row.id]}
                                                onClick={() => act(row, 'dispatch', { dispatch_reference: refs[row.id] ?? '' })}
                                            >
                                                Mark dispatched
                                            </Button>
                                        </>
                                    )}
                                    {['paid', 'processing'].includes(row.status) && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => {
                                                const remarks = window.prompt('Reason for rejection');
                                                if (remarks) act(row, 'reject', { remarks });
                                            }}
                                        >
                                            Reject
                                        </Button>
                                    )}
                                    {row.dispatch_reference && <span className="text-muted-foreground text-xs">Ref: {row.dispatch_reference}</span>}
                                    {row.processed_by && <span className="text-muted-foreground text-xs">Processed by {row.processed_by.name}</span>}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
