import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SIWES', href: '/student/siwes' }];

interface Placement {
    id: number;
    employer_name: string;
    employer_address: string;
    industry_supervisor_name: string;
    industry_supervisor_phone: string;
    starts_on: string;
    ends_on: string;
    status: string;
    logbook_score: string | null;
    report_score: string | null;
    visit_score: string | null;
    total_score: string | null;
    is_passed: boolean | null;
    academic_session?: { name: string };
}

interface Props {
    session: { name: string } | null;
    placement: Placement | null;
    minWeeks: number;
    history: Placement[];
}

export default function Siwes({ session, placement, minWeeks, history }: Props) {
    const editable = !placement || ['pending', 'rejected'].includes(placement.status);

    const form = useForm({
        employer_name: placement?.employer_name ?? '',
        employer_address: placement?.employer_address ?? '',
        industry_supervisor_name: placement?.industry_supervisor_name ?? '',
        industry_supervisor_phone: placement?.industry_supervisor_phone ?? '',
        starts_on: placement?.starts_on?.slice(0, 10) ?? '',
        ends_on: placement?.ends_on?.slice(0, 10) ?? '',
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SIWES" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            Industrial Training (SIWES) {session ? `— ${session.name}` : ''}
                            {placement && <Badge variant={placement.status === 'rejected' ? 'destructive' : 'outline'}>{placement.status}</Badge>}
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Register your placement (minimum {minWeeks} weeks). Keep your ITF logbook signed weekly by your industry supervisor; it
                            carries 40% of your SIWES grade.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {placement?.status === 'graded' && (
                            <p className="mb-4 text-sm">
                                Graded: logbook {placement.logbook_score}/40, report {placement.report_score}/40, visits {placement.visit_score}/20 —{' '}
                                <span className="font-semibold">
                                    {placement.total_score}/100 ({placement.is_passed ? 'pass' : 'fail — repeat the attachment'})
                                </span>
                            </p>
                        )}
                        {editable ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post('/student/siwes');
                                }}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                <div className="space-y-1">
                                    <Label>Employer / organisation</Label>
                                    <Input value={form.data.employer_name} onChange={(e) => form.setData('employer_name', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label>Employer address</Label>
                                    <Input value={form.data.employer_address} onChange={(e) => form.setData('employer_address', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label>Industry supervisor</Label>
                                    <Input
                                        value={form.data.industry_supervisor_name}
                                        onChange={(e) => form.setData('industry_supervisor_name', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label>Supervisor phone</Label>
                                    <Input
                                        value={form.data.industry_supervisor_phone}
                                        onChange={(e) => form.setData('industry_supervisor_phone', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label>Start date</Label>
                                    <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label>End date</Label>
                                    <Input type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} />
                                </div>
                                <div className="md:col-span-2">
                                    <Button type="submit" disabled={form.processing}>
                                        {placement ? 'Update placement' : 'Submit placement'}
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            placement && (
                                <div className="grid gap-1 text-sm md:grid-cols-2">
                                    <div>Employer: {placement.employer_name}</div>
                                    <div>Address: {placement.employer_address}</div>
                                    <div>
                                        Supervisor: {placement.industry_supervisor_name} ({placement.industry_supervisor_phone})
                                    </div>
                                    <div>
                                        Period: {placement.starts_on.slice(0, 10)} → {placement.ends_on.slice(0, 10)}
                                    </div>
                                </div>
                            )
                        )}
                    </CardContent>
                </Card>

                {history.length > 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Previous placements</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {history
                                .filter((h) => h.id !== placement?.id)
                                .map((h) => (
                                    <div key={h.id}>
                                        {h.academic_session?.name}: {h.employer_name} — {h.status}
                                        {h.total_score ? ` (${h.total_score}/100)` : ''}
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
