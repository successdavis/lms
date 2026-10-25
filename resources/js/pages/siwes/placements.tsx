import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SIWES Placements', href: '/siwes/placements' }];

interface Row {
    id: number;
    employer_name: string;
    employer_address: string;
    industry_supervisor_name: string;
    industry_supervisor_phone: string;
    starts_on: string;
    ends_on: string;
    status: string;
    total_score: string | null;
    is_passed: boolean | null;
    student: { matric_no: string | null; user: { name: string }; programme: { code: string } };
    academic_session: { name: string };
}

interface Props {
    placements: Row[];
    status: string;
    passMark: number;
}

export default function Placements({ placements, status, passMark }: Props) {
    const [scores, setScores] = useState<Record<number, { logbook_score: string; report_score: string; visit_score: string }>>({});
    const score = (id: number) => scores[id] ?? { logbook_score: '', report_score: '', visit_score: '' };
    const setScore = (id: number, field: string, value: string) => setScores((prev) => ({ ...prev, [id]: { ...score(id), [field]: value } }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SIWES Placements" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="flex flex-wrap gap-2">
                    {['pending', 'approved', 'graded', 'rejected', 'all'].map((tab) => (
                        <Button
                            key={tab}
                            size="sm"
                            variant={status === tab ? 'default' : 'outline'}
                            onClick={() => router.get('/siwes/placements', { status: tab }, { preserveState: true })}
                        >
                            {tab}
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Placements ({placements.length})</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Grade after the attachment ends: ITF logbook /40, report /40, supervision visits /20. Pass mark {passMark}.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {placements.length === 0 && <p className="text-muted-foreground text-sm">Nothing here.</p>}
                        {placements.map((p) => {
                            const s = score(p.id);
                            return (
                                <div key={p.id} className="space-y-2 rounded-lg border p-3 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{p.student.user.name}</span>
                                        <span className="text-muted-foreground font-mono text-xs">{p.student.matric_no}</span>
                                        <span className="text-muted-foreground">
                                            {p.student.programme.code} · {p.academic_session.name}
                                        </span>
                                        <Badge variant="outline">{p.status}</Badge>
                                        {p.total_score && (
                                            <Badge variant={p.is_passed ? 'default' : 'destructive'}>
                                                {p.total_score}/100 {p.is_passed ? 'pass' : 'fail'}
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {p.employer_name}, {p.employer_address} · Supervisor {p.industry_supervisor_name} (
                                        {p.industry_supervisor_phone}) · {p.starts_on.slice(0, 10)} → {p.ends_on.slice(0, 10)}
                                    </div>
                                    {p.status === 'pending' && (
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => router.patch(`/siwes/placements/${p.id}`, { action: 'approve' })}>
                                                Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive"
                                                onClick={() => router.patch(`/siwes/placements/${p.id}`, { action: 'reject' })}
                                            >
                                                Reject
                                            </Button>
                                        </div>
                                    )}
                                    {['approved', 'graded'].includes(p.status) && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Input
                                                className="h-8 w-24"
                                                type="number"
                                                placeholder="Logbook /40"
                                                value={s.logbook_score}
                                                onChange={(e) => setScore(p.id, 'logbook_score', e.target.value)}
                                            />
                                            <Input
                                                className="h-8 w-24"
                                                type="number"
                                                placeholder="Report /40"
                                                value={s.report_score}
                                                onChange={(e) => setScore(p.id, 'report_score', e.target.value)}
                                            />
                                            <Input
                                                className="h-8 w-24"
                                                type="number"
                                                placeholder="Visits /20"
                                                value={s.visit_score}
                                                onChange={(e) => setScore(p.id, 'visit_score', e.target.value)}
                                            />
                                            <Button size="sm" variant="outline" onClick={() => router.patch(`/siwes/placements/${p.id}/grade`, s)}>
                                                {p.status === 'graded' ? 'Re-grade' : 'Grade'}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
