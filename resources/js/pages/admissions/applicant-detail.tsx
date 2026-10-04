import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface Doc {
    id: number;
    type: string;
    original_name: string;
    status: string;
    note: string | null;
    verified_by: { name: string } | null;
    verified_at: string | null;
}

interface Props {
    applicant: {
        id: number;
        application_no: string;
        status: string;
        caps_status: string | null;
        jamb_reg_no: string | null;
        utme_score: number | null;
        post_utme_score: string | null;
        aggregate_score: string | null;
        gender: string | null;
        date_of_birth: string | null;
        phone: string | null;
        state_of_origin: string | null;
        lga_of_origin: string | null;
        address: string | null;
        user: { name: string; email: string };
        programme: { name: string; code: string };
        admitted_programme: { name: string; code: string } | null;
        admission_list: { name: string } | null;
        documents: Doc[];
        payments: { id: number; reference: string; amount: string; status: string; gateway: string; paid_at: string | null }[];
    };
    documentTypes: Record<string, string>;
    capsStatuses: string[];
    applicationFee: number;
    applicationFeePaid: boolean;
}

export default function ApplicantDetail({ applicant, documentTypes, capsStatuses, applicationFee, applicationFeePaid }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Applicants', href: '/admissions/applicants' },
        { title: applicant.application_no, href: `/admissions/applicants/${applicant.id}` },
    ];

    const [notes, setNotes] = useState<Record<number, string>>({});

    const reviewDocument = (doc: Doc, action: 'verify' | 'reject') =>
        router.patch(`/admissions/documents/${doc.id}`, { action, note: notes[doc.id] ?? '' }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={applicant.application_no} />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            {applicant.user.name}
                            <span className="text-muted-foreground font-mono text-sm font-normal">{applicant.application_no}</span>
                            <Badge>{applicant.status}</Badge>
                            {applicationFee > 0 && (
                                <Badge variant={applicationFeePaid ? 'default' : 'destructive'}>
                                    App fee {applicationFeePaid ? 'paid' : 'unpaid'}
                                </Badge>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-1 text-sm md:grid-cols-2">
                        <div>Email: {applicant.user.email}</div>
                        <div>Phone: {applicant.phone ?? '—'}</div>
                        <div>JAMB Reg No: {applicant.jamb_reg_no ?? '—'}</div>
                        <div>
                            UTME {applicant.utme_score ?? '—'} · Post-UTME {applicant.post_utme_score ?? '—'} · Aggregate{' '}
                            <span className="font-medium">{applicant.aggregate_score ?? '—'}</span>
                        </div>
                        <div>Choice: {applicant.programme.name}</div>
                        <div>
                            Offer: {applicant.admitted_programme?.name ?? '—'}
                            {applicant.admission_list ? ` (${applicant.admission_list.name})` : ''}
                        </div>
                        <div>
                            Origin: {applicant.state_of_origin ?? '—'} / {applicant.lga_of_origin ?? '—'}
                        </div>
                        <div>
                            {applicant.gender ?? '—'} · born {applicant.date_of_birth?.slice(0, 10) ?? '—'}
                        </div>
                        <div className="md:col-span-2">Address: {applicant.address ?? '—'}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">JAMB CAPS status</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Record-keeping for the Central Admissions Processing System — update as the candidate's CAPS state changes.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <select
                            className="bg-background rounded-md border px-2 py-2 text-sm"
                            value={applicant.caps_status ?? ''}
                            onChange={(e) =>
                                router.patch(`/admissions/applicants/${applicant.id}/caps`, { caps_status: e.target.value }, { preserveState: true })
                            }
                        >
                            <option value="" disabled>
                                Set CAPS status…
                            </option>
                            {capsStatuses.map((status) => (
                                <option key={status} value={status}>
                                    {status.replace('_', ' ')}
                                </option>
                            ))}
                        </select>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Credential verification checklist</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {applicant.documents.length === 0 && (
                            <p className="text-muted-foreground text-sm">The applicant has not uploaded any documents yet.</p>
                        )}
                        {applicant.documents.map((doc) => (
                            <div key={doc.id} className="flex flex-wrap items-center gap-2 rounded border p-2 text-sm">
                                <span className="font-medium">{documentTypes[doc.type] ?? doc.type}</span>
                                <a href={`/admissions/documents/${doc.id}/download`} className="text-xs underline">
                                    {doc.original_name}
                                </a>
                                <Badge variant={doc.status === 'verified' ? 'default' : doc.status === 'rejected' ? 'destructive' : 'outline'}>
                                    {doc.status}
                                </Badge>
                                {doc.verified_by && (
                                    <span className="text-muted-foreground text-xs">
                                        by {doc.verified_by.name}
                                        {doc.note ? ` — ${doc.note}` : ''}
                                    </span>
                                )}
                                {doc.status === 'pending' && (
                                    <span className="ml-auto flex items-center gap-1">
                                        <Input
                                            className="h-8 w-44"
                                            placeholder="Note (optional)"
                                            value={notes[doc.id] ?? ''}
                                            onChange={(e) => setNotes((prev) => ({ ...prev, [doc.id]: e.target.value }))}
                                        />
                                        <Button size="sm" variant="outline" onClick={() => reviewDocument(doc, 'verify')}>
                                            Verify
                                        </Button>
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => reviewDocument(doc, 'reject')}>
                                            Reject
                                        </Button>
                                    </span>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {applicant.payments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Application-fee payments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {applicant.payments.map((payment) => (
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
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
