import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Applicants', href: '/admissions/applicants' }];

interface ApplicantRow {
    id: number;
    application_no: string;
    jamb_reg_no: string | null;
    status: string;
    utme_score: number | null;
    post_utme_score: string | null;
    aggregate_score: string | null;
    user: { name: string; email: string };
    programme: { code: string };
    admission_list: { name: string } | null;
}

interface Props {
    cycle: { id: number; session: string; utme_weight: number; post_utme_weight: number; default_cutoff: string } | null;
    applicants: {
        data: ApplicantRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    } | null;
    filters: { status: string; programme: number | null; search: string };
    programmes: { id: number; code: string; name: string }[];
    counts: Record<string, number>;
}

export default function Applicants({ cycle, applicants, filters, programmes, counts }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [scores, setScores] = useState<Record<number, string>>({});

    const applyFilters = (overrides: Record<string, unknown> = {}) =>
        router.get('/admissions/applicants', { ...filters, search, ...overrides }, { preserveState: true });

    const saveScore = (applicant: ApplicantRow) => {
        const value = scores[applicant.id] ?? applicant.post_utme_score ?? '';
        if (value === '') return;
        router.patch(`/admissions/applicants/${applicant.id}/score`, { post_utme_score: Number(value) }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Applicants" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                {!cycle && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center">No active admission cycle.</CardContent>
                    </Card>
                )}

                {cycle && applicants && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                {cycle.session} applicants ({applicants.total})
                                {Object.entries(counts).map(([status, total]) => (
                                    <Badge key={status} variant="outline">
                                        {status}: {total}
                                    </Badge>
                                ))}
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">
                                Aggregate = UTME/400 × {cycle.utme_weight} + Post-UTME/100 × {cycle.post_utme_weight}. Default cutoff:{' '}
                                {cycle.default_cutoff}.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Input
                                    className="max-w-xs"
                                    placeholder="Search name, application or JAMB no…"
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
                                    {['draft', 'submitted', 'screened', 'admitted', 'accepted', 'matriculated', 'rejected'].map((status) => (
                                        <option key={status} value={status}>
                                            {status}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className="bg-background rounded-md border px-2 text-sm"
                                    value={filters.programme ?? ''}
                                    onChange={(e) => applyFilters({ programme: e.target.value })}
                                >
                                    <option value="">Any programme</option>
                                    {programmes.map((programme) => (
                                        <option key={programme.id} value={programme.id}>
                                            {programme.code}
                                        </option>
                                    ))}
                                </select>
                                <Button variant="outline" onClick={() => router.post('/admissions/screen')}>
                                    Screen submitted applicants
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left">
                                        <th className="py-2 pr-2">Applicant</th>
                                        <th className="py-2 pr-2">Programme</th>
                                        <th className="py-2 pr-2 text-right">UTME</th>
                                        <th className="py-2 pr-2">Post-UTME</th>
                                        <th className="py-2 pr-2 text-right">Aggregate</th>
                                        <th className="py-2 pr-2">Status</th>
                                        <th className="py-2">List</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {applicants.data.map((applicant) => (
                                        <tr key={applicant.id} className="border-b last:border-0">
                                            <td className="py-2 pr-2">
                                                <div>{applicant.user.name}</div>
                                                <div className="text-muted-foreground font-mono text-xs">
                                                    {applicant.application_no}
                                                    {applicant.jamb_reg_no ? ` · ${applicant.jamb_reg_no}` : ''}
                                                </div>
                                            </td>
                                            <td className="py-2 pr-2">{applicant.programme.code}</td>
                                            <td className="py-2 pr-2 text-right">{applicant.utme_score ?? '—'}</td>
                                            <td className="py-2 pr-2">
                                                {['submitted', 'screened'].includes(applicant.status) ? (
                                                    <div className="flex items-center gap-1">
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            className="h-8 w-20"
                                                            value={scores[applicant.id] ?? applicant.post_utme_score ?? ''}
                                                            onChange={(e) => setScores((prev) => ({ ...prev, [applicant.id]: e.target.value }))}
                                                        />
                                                        <Button size="sm" variant="outline" onClick={() => saveScore(applicant)}>
                                                            Save
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    (applicant.post_utme_score ?? '—')
                                                )}
                                            </td>
                                            <td className="py-2 pr-2 text-right font-medium">{applicant.aggregate_score ?? '—'}</td>
                                            <td className="py-2 pr-2">
                                                <Badge
                                                    variant={
                                                        ['admitted', 'accepted', 'matriculated'].includes(applicant.status)
                                                            ? 'default'
                                                            : applicant.status === 'rejected'
                                                              ? 'destructive'
                                                              : 'outline'
                                                    }
                                                >
                                                    {applicant.status}
                                                </Badge>
                                            </td>
                                            <td className="py-2">{applicant.admission_list?.name ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="flex flex-wrap gap-1 pt-4">
                                {applicants.links.map((link, i) =>
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
                )}
            </div>
        </AppLayout>
    );
}
