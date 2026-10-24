import { FlashMessages } from '@/components/portal/flash-messages';
import { StatCard } from '@/components/portal/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Leave', href: '/staff/leave' }];

interface LeaveRow {
    id: number;
    type: string;
    starts_on: string;
    ends_on: string;
    days: number;
    reason: string | null;
    status: string;
    note: string | null;
    reviewed_by: { name: string } | null;
}

interface Props {
    requests: LeaveRow[];
    entitlement: number;
    used: number;
    types: string[];
    pendingApprovals: number;
}

export default function Leave({ requests, entitlement, used, types, pendingApprovals }: Props) {
    const form = useForm({ type: 'annual', starts_on: '', ends_on: '', reason: '' });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Leave" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard label="Annual entitlement" value={`${entitlement} days`} hint="Working days per calendar year" />
                    <StatCard label="Committed this year" value={`${used} days`} hint="Approved + pending annual leave" />
                    <StatCard
                        label="Remaining"
                        value={`${Math.max(0, entitlement - used)} days`}
                        hint={pendingApprovals > 0 ? `${pendingApprovals} request(s) awaiting your approval` : undefined}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Apply for leave</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/staff/leave', { onSuccess: () => form.reset('starts_on', 'ends_on', 'reason') });
                            }}
                            className="grid gap-4 md:grid-cols-4"
                        >
                            <div className="space-y-1">
                                <Label>Type</Label>
                                <select
                                    className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                    value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}
                                >
                                    {types.map((type) => (
                                        <option key={type} value={type}>
                                            {type}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1">
                                <Label>From</Label>
                                <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                            </div>
                            <div className="space-y-1">
                                <Label>To</Label>
                                <Input type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} />
                            </div>
                            <div className="space-y-1">
                                <Label>Reason</Label>
                                <Input value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                            </div>
                            <div className="md:col-span-4">
                                <Button type="submit" disabled={form.processing}>
                                    Submit request
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My requests</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {requests.length === 0 && <p className="text-muted-foreground text-sm">No leave requests yet.</p>}
                        {requests.map((r) => (
                            <div key={r.id} className="flex flex-wrap items-center gap-2 rounded-lg border p-2 text-sm">
                                <span className="font-medium capitalize">{r.type}</span>
                                <span className="text-muted-foreground">
                                    {r.starts_on.slice(0, 10)} → {r.ends_on.slice(0, 10)} · {r.days} working day(s)
                                </span>
                                <Badge variant={r.status === 'approved' ? 'default' : r.status === 'rejected' ? 'destructive' : 'outline'}>
                                    {r.status}
                                </Badge>
                                {r.reviewed_by && <span className="text-muted-foreground text-xs">by {r.reviewed_by.name}</span>}
                                {r.note && <span className="text-muted-foreground text-xs">— {r.note}</span>}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
