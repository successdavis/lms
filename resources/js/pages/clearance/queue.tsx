import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clearance Queue', href: '/clearance' }];

interface Item {
    id: number;
    unit: string;
    label: string;
    student: { name: string; matric_no: string | null; programme: string; level: string };
    blockers: string[];
}

export default function ClearanceQueue({ items }: { items: Item[] }) {
    const [notes, setNotes] = useState<Record<number, string>>({});

    const act = (item: Item, action: 'clear' | 'reject') =>
        router.patch(`/clearance/items/${item.id}`, { action, note: notes[item.id] ?? '' }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clearance Queue" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Awaiting your sign-off ({items.length})</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Only units you sign for are shown. Items with outstanding fees or carryovers cannot be cleared until resolved.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {items.length === 0 && <p className="text-muted-foreground text-sm">Nothing awaiting your sign-off.</p>}
                        {items.map((item) => (
                            <div key={item.id} className="space-y-2 rounded-lg border p-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">{item.label}</Badge>
                                    <span className="font-medium">{item.student.name}</span>
                                    <span className="text-muted-foreground font-mono text-xs">{item.student.matric_no}</span>
                                    <span className="text-muted-foreground">
                                        {item.student.programme} · {item.student.level}
                                    </span>
                                </div>
                                {item.blockers.map((blocker, i) => (
                                    <div key={i} className="text-destructive text-xs">
                                        {blocker}
                                    </div>
                                ))}
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        className="h-8 w-64"
                                        placeholder="Note (required to reject)"
                                        value={notes[item.id] ?? ''}
                                        onChange={(e) => setNotes((prev) => ({ ...prev, [item.id]: e.target.value }))}
                                    />
                                    <Button size="sm" disabled={item.blockers.length > 0} onClick={() => act(item, 'clear')}>
                                        Clear
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive"
                                        disabled={!notes[item.id]}
                                        onClick={() => act(item, 'reject')}
                                    >
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
