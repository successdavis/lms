import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Graduation & NYSC', href: '/records/graduation' }];

interface Row {
    id: number;
    name: string;
    matric_no: string | null;
    programme: string;
    award: string;
    cgpa: string | null;
    classification: string | null;
    graduated_on: string | null;
    nysc_batch: string | null;
    nysc_eligible: string;
    carryovers: number;
}

export default function Graduation({ students }: { students: Row[] }) {
    const [selected, setSelected] = useState<number[]>([]);
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const [batch, setBatch] = useState('');

    const pending = students.filter((s) => !s.graduated_on);
    const graduated = students.filter((s) => s.graduated_on);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Graduation & NYSC" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Cleared — awaiting graduation ({pending.length})</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Students who completed final clearance. Graduating requires no outstanding carryovers; record the Senate / Academic Board
                            approval date.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {pending.length === 0 && <p className="text-muted-foreground text-sm">No cleared students awaiting graduation.</p>}
                        {pending.map((s) => (
                            <label key={s.id} className="flex flex-wrap items-center gap-3 rounded-lg border p-2 text-sm">
                                <Checkbox
                                    checked={selected.includes(s.id)}
                                    disabled={s.carryovers > 0}
                                    onCheckedChange={(c) => setSelected(c ? [...selected, s.id] : selected.filter((id) => id !== s.id))}
                                />
                                <span className="font-medium">{s.name}</span>
                                <span className="text-muted-foreground font-mono text-xs">{s.matric_no}</span>
                                <span className="text-muted-foreground">{s.programme}</span>
                                <span>
                                    CGPA {s.cgpa ?? '—'} {s.classification ? `· ${s.classification}` : ''}
                                </span>
                                {s.carryovers > 0 && <Badge variant="destructive">{s.carryovers} carryover(s)</Badge>}
                            </label>
                        ))}
                        {pending.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 pt-2">
                                <Input type="date" className="w-44" value={date} onChange={(e) => setDate(e.target.value)} />
                                <Button
                                    disabled={selected.length === 0}
                                    onClick={() =>
                                        router.post(
                                            '/records/graduation',
                                            { student_ids: selected, graduated_on: date },
                                            { onSuccess: () => setSelected([]) },
                                        )
                                    }
                                >
                                    Graduate selected ({selected.length})
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Graduates ({graduated.length})</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Export the NYSC mobilization list for a batch. Graduates not yet in a batch are stamped with it, so nobody is mobilized
                            twice. Graduates aged 30+ are listed for exemption; ND graduates are excluded.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <Input className="w-56" placeholder="Batch, e.g. 2026 Batch A" value={batch} onChange={(e) => setBatch(e.target.value)} />
                            <a href={batch ? `/records/graduation/nysc?batch=${encodeURIComponent(batch)}` : undefined}>
                                <Button variant="outline" disabled={!batch}>
                                    Download NYSC list (CSV)
                                </Button>
                            </a>
                        </div>
                        {graduated.map((s) => (
                            <div key={s.id} className="flex flex-wrap items-center gap-3 rounded-lg border p-2 text-sm">
                                <span className="font-medium">{s.name}</span>
                                <span className="text-muted-foreground font-mono text-xs">{s.matric_no}</span>
                                <span className="text-muted-foreground">
                                    {s.award} · {s.classification ?? '—'} · graduated {s.graduated_on}
                                </span>
                                <Badge variant={s.nysc_eligible === 'Mobilize' ? 'default' : 'outline'}>{s.nysc_eligible}</Badge>
                                {s.nysc_batch && <Badge variant="outline">{s.nysc_batch}</Badge>}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
