import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Promotions', href: '/hr/promotions' }];

interface Candidate {
    id: number;
    name: string;
    staff_no: string;
    department: string | null;
    designation: string | null;
    grade_level: number | null;
    years_in_rank: number;
    proposal: { to_designation: string | null; to_grade_level: number };
}

interface PromotionRow {
    id: number;
    from_designation: string | null;
    to_designation: string | null;
    from_grade_level: number | null;
    to_grade_level: number;
    effective_on: string;
    status: string;
    justification: string | null;
    note: string | null;
    staff: { staff_no: string; user: { name: string }; department: { name: string } | null };
    recommended_by: { name: string };
    decided_by: { name: string } | null;
}

interface Props {
    candidates: Candidate[];
    minYears: number;
    canDecide: boolean;
    promotions: PromotionRow[];
}

export default function Promotions({ candidates, minYears, canDecide, promotions }: Props) {
    const form = useForm({ staff_id: '', effective_on: '', to_grade_level: '', justification: '' });
    const selected = candidates.find((c) => String(c.id) === form.data.staff_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Promotions" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recommend a promotion</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Academic staff move one rank up the ladder; others move one grade level. Staff need {minYears} years in rank by the
                            effective date. A different HR/registry officer approves.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/hr/promotions', { onSuccess: () => form.reset() });
                            }}
                            className="grid gap-2 md:grid-cols-4"
                        >
                            <select
                                className="bg-background rounded-md border px-2 py-2 text-sm md:col-span-2"
                                value={form.data.staff_id}
                                onChange={(e) => form.setData('staff_id', e.target.value)}
                            >
                                <option value="">Select staff…</option>
                                {candidates.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} ({c.staff_no}) — {c.designation ?? 'no designation'}, {c.years_in_rank} yr(s) in rank
                                    </option>
                                ))}
                            </select>
                            <Input type="date" value={form.data.effective_on} onChange={(e) => form.setData('effective_on', e.target.value)} />
                            <Input
                                type="number"
                                placeholder={selected ? `GL ${selected.proposal.to_grade_level}` : 'New grade level'}
                                value={form.data.to_grade_level}
                                onChange={(e) => form.setData('to_grade_level', e.target.value)}
                            />
                            {selected && (
                                <p className="text-sm md:col-span-4">
                                    Proposed: <span className="font-medium">{selected.designation ?? '—'}</span> →{' '}
                                    <span className="font-medium">{selected.proposal.to_designation ?? 'top of ladder'}</span>, GL{' '}
                                    {selected.grade_level ?? '—'} → {form.data.to_grade_level || selected.proposal.to_grade_level}
                                    {selected.years_in_rank < minYears && (
                                        <span className="text-destructive"> · only {selected.years_in_rank} year(s) in rank</span>
                                    )}
                                </p>
                            )}
                            <Input
                                className="md:col-span-3"
                                placeholder="Justification (publications, service, teaching)"
                                value={form.data.justification}
                                onChange={(e) => form.setData('justification', e.target.value)}
                            />
                            <Button type="submit" disabled={form.processing || !form.data.staff_id}>
                                Recommend
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Promotion records</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {promotions.length === 0 && <p className="text-muted-foreground text-sm">No promotions recorded yet.</p>}
                        {promotions.map((p) => (
                            <div key={p.id} className="space-y-1 rounded-lg border p-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{p.staff.user.name}</span>
                                    <span className="text-muted-foreground font-mono text-xs">{p.staff.staff_no}</span>
                                    <span>
                                        {p.from_designation ?? '—'} → {p.to_designation ?? '—'} · GL {p.from_grade_level ?? '—'} → {p.to_grade_level}
                                    </span>
                                    <span className="text-muted-foreground">effective {p.effective_on.slice(0, 10)}</span>
                                    <Badge variant={p.status === 'approved' ? 'default' : p.status === 'rejected' ? 'destructive' : 'outline'}>
                                        {p.status}
                                    </Badge>
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    Recommended by {p.recommended_by.name}
                                    {p.decided_by ? ` · decided by ${p.decided_by.name}` : ''}
                                    {p.note ? ` — ${p.note}` : ''}
                                </div>
                                {p.justification && <div className="text-muted-foreground">{p.justification}</div>}
                                {canDecide && p.status === 'pending' && (
                                    <div className="flex gap-2 pt-1">
                                        <Button size="sm" onClick={() => router.patch(`/hr/promotions/${p.id}`, { action: 'approve' })}>
                                            Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => {
                                                const note = window.prompt('Reason for rejection') ?? '';
                                                router.patch(`/hr/promotions/${p.id}`, { action: 'reject', note });
                                            }}
                                        >
                                            Reject
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
