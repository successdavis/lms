import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Result Approvals', href: '/staff/approvals' }];

interface QueueRow {
    course_id: number;
    code: string;
    title: string;
    department: string;
    counts: Record<string, number>;
    total: number;
}

interface Props {
    semester: { name: string } | null;
    queue: QueueRow[];
    canSenateApprove: boolean;
}

export default function Approvals({ semester, queue, canSenateApprove }: Props) {
    const { auth } = usePage<SharedData>().props;
    const roles = auth.roles ?? [];
    const approve = (courseId: number, stage: string) => router.post(`/staff/approvals/${courseId}`, { stage });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Result Approvals" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{semester ? `Pending results — ${semester.name}` : 'No current semester'}</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Pipeline: pending → HOD → Faculty/School board → Senate/Academic board. Students see results after final approval.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {queue.length === 0 && <p className="text-muted-foreground text-sm">Nothing awaiting approval.</p>}
                        {queue.map((row) => (
                            <div key={row.course_id} className="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                                <span className="font-medium">{row.code}</span>
                                <span className="text-muted-foreground flex-1 text-sm">
                                    {row.title} · {row.department}
                                </span>
                                {Object.entries(row.counts).map(([status, count]) => (
                                    <Badge key={status} variant="outline">
                                        {status}: {count}
                                    </Badge>
                                ))}
                                {(row.counts['pending'] ?? 0) > 0 && (
                                    <Button size="sm" variant="outline" onClick={() => approve(row.course_id, 'hod')}>
                                        HOD approve
                                    </Button>
                                )}
                                {(row.counts['hod_approved'] ?? 0) > 0 &&
                                    (roles.includes('dean') || roles.includes('registrar') || roles.includes('super-admin')) && (
                                        <Button size="sm" variant="outline" onClick={() => approve(row.course_id, 'faculty')}>
                                            Faculty approve
                                        </Button>
                                    )}
                                {(row.counts['faculty_approved'] ?? 0) > 0 && canSenateApprove && (
                                    <Button size="sm" onClick={() => approve(row.course_id, 'senate')}>
                                        Senate approve
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
