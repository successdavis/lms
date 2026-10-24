import { FlashMessages } from '@/components/portal/flash-messages';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leave Approvals', href: '/staff/leave/approvals' }];

interface Row {
    id: number;
    type: string;
    starts_on: string;
    ends_on: string;
    days: number;
    reason: string | null;
    staff: { staff_no: string; user: { name: string }; department: { name: string } | null };
}

export default function LeaveApprovals({ requests }: { requests: Row[] }) {
    const [notes, setNotes] = useState<Record<number, string>>({});

    const act = (row: Row, action: 'approve' | 'reject') =>
        router.patch(`/staff/leave/${row.id}`, { action, note: notes[row.id] ?? '' }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Approvals" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Pending leave requests ({requests.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {requests.length === 0 && <p className="text-muted-foreground text-sm">Nothing awaiting approval.</p>}
                        {requests.map((r) => (
                            <div key={r.id} className="space-y-2 rounded-lg border p-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{r.staff.user.name}</span>
                                    <span className="text-muted-foreground font-mono text-xs">{r.staff.staff_no}</span>
                                    <span className="text-muted-foreground">{r.staff.department?.name ?? 'Non-teaching unit'}</span>
                                    <span className="capitalize">{r.type}</span>
                                    <span className="text-muted-foreground">
                                        {r.starts_on.slice(0, 10)} → {r.ends_on.slice(0, 10)} · {r.days} day(s)
                                    </span>
                                </div>
                                {r.reason && <div className="text-muted-foreground">{r.reason}</div>}
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        className="h-8 w-64"
                                        placeholder="Note (optional)"
                                        value={notes[r.id] ?? ''}
                                        onChange={(e) => setNotes((prev) => ({ ...prev, [r.id]: e.target.value }))}
                                    />
                                    <Button size="sm" onClick={() => act(r, 'approve')}>
                                        Approve
                                    </Button>
                                    <Button size="sm" variant="ghost" className="text-destructive" onClick={() => act(r, 'reject')}>
                                        Reject
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
