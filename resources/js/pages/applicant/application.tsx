import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Application', href: '/apply' }];

interface Applicant {
    application_no: string;
    status: string;
    programme: { name: string; code: string };
    admitted_programme: { name: string; code: string } | null;
    admission_list: { name: string; published_at: string | null } | null;
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
    programme_id: number;
}

interface Props {
    cycle: { session: string; open: boolean; closes_at: string | null } | null;
    applicant: Applicant | null;
    programmes: { id: number; name: string; code: string }[];
}

const statusHints: Record<string, string> = {
    draft: 'Complete the form and submit before the window closes.',
    submitted: 'Submitted — await post-UTME screening by the admissions office.',
    screened: 'Screened — your aggregate has been computed. Await the admission lists.',
    admitted: 'Congratulations! You have been offered admission. Accept the offer below.',
    accepted: 'Offer accepted — the admissions office will matriculate you shortly.',
    matriculated: 'You have been matriculated. Log in afresh to access the student portal.',
    rejected: 'You were not offered admission in this cycle.',
};

export default function Application({ cycle, applicant, programmes }: Props) {
    const editable = cycle?.open && (!applicant || applicant.status === 'draft');

    const form = useForm({
        programme_id: applicant?.programme_id ?? '',
        jamb_reg_no: applicant?.jamb_reg_no ?? '',
        utme_score: applicant?.utme_score ?? '',
        gender: applicant?.gender ?? '',
        date_of_birth: applicant?.date_of_birth?.slice(0, 10) ?? '',
        phone: applicant?.phone ?? '',
        state_of_origin: applicant?.state_of_origin ?? '',
        lga_of_origin: applicant?.lga_of_origin ?? '',
        address: applicant?.address ?? '',
    });

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/apply');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Application" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                {!cycle && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center">No admission cycle is currently configured.</CardContent>
                    </Card>
                )}

                {cycle && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                {cycle.session} Admission Exercise
                                <Badge variant={cycle.open ? 'default' : 'destructive'}>{cycle.open ? 'open' : 'closed'}</Badge>
                                {applicant && <Badge variant="outline">{applicant.status}</Badge>}
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">
                                {applicant ? statusHints[applicant.status] : 'Fill the application form below to begin.'}
                                {cycle.closes_at && cycle.open ? ` Window closes ${cycle.closes_at}.` : ''}
                            </p>
                        </CardHeader>
                        {applicant && (
                            <CardContent className="space-y-2 text-sm">
                                <div className="grid gap-1 md:grid-cols-2">
                                    <div>
                                        Application No: <span className="font-mono">{applicant.application_no}</span>
                                    </div>
                                    <div>Choice: {applicant.programme.name}</div>
                                    {applicant.aggregate_score && <div>Aggregate score: {applicant.aggregate_score}</div>}
                                    {applicant.admitted_programme && applicant.status !== 'draft' && (
                                        <div>
                                            Offered: <span className="font-medium">{applicant.admitted_programme.name}</span>
                                            {applicant.admission_list && ` (${applicant.admission_list.name})`}
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2 pt-2">
                                    {applicant.status === 'draft' && <Button onClick={() => router.post('/apply/submit')}>Submit application</Button>}
                                    {applicant.status === 'admitted' && (
                                        <Button onClick={() => router.post('/apply/accept')}>Accept admission offer</Button>
                                    )}
                                    {['admitted', 'accepted', 'matriculated'].includes(applicant.status) && (
                                        <a href="/apply/print/admission-letter" target="_blank" rel="noreferrer">
                                            <Button variant="outline">Print admission letter</Button>
                                        </a>
                                    )}
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                {cycle && editable && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Application form</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={save} className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1">
                                    <Label>Programme (first choice)</Label>
                                    <select
                                        className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                        value={form.data.programme_id}
                                        onChange={(e) => form.setData('programme_id', e.target.value)}
                                    >
                                        <option value="">Select programme…</option>
                                        {programmes.map((programme) => (
                                            <option key={programme.id} value={programme.id}>
                                                {programme.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label>JAMB Registration No</Label>
                                    <Input value={form.data.jamb_reg_no} onChange={(e) => form.setData('jamb_reg_no', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label>UTME Score (0–400)</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={400}
                                        value={form.data.utme_score}
                                        onChange={(e) => form.setData('utme_score', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label>Gender</Label>
                                    <select
                                        className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                        value={form.data.gender}
                                        onChange={(e) => form.setData('gender', e.target.value)}
                                    >
                                        <option value="">Select…</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label>Date of Birth</Label>
                                    <Input
                                        type="date"
                                        value={form.data.date_of_birth}
                                        onChange={(e) => form.setData('date_of_birth', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label>Phone</Label>
                                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label>State of Origin</Label>
                                    <Input value={form.data.state_of_origin} onChange={(e) => form.setData('state_of_origin', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label>LGA of Origin</Label>
                                    <Input value={form.data.lga_of_origin} onChange={(e) => form.setData('lga_of_origin', e.target.value)} />
                                </div>
                                <div className="space-y-1 md:col-span-2">
                                    <Label>Contact Address</Label>
                                    <Input value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                                </div>
                                <div className="md:col-span-2">
                                    <Button type="submit" disabled={form.processing}>
                                        {applicant ? 'Update draft' : 'Save application'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
