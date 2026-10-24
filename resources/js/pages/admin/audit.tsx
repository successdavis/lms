import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Audit Log', href: '/admin/audit' }];

type Values = Record<string, unknown> | null;

interface Log {
    id: number;
    event: string;
    type: string;
    subject_id: number | null;
    old: Values;
    new: Values;
    description: string | null;
    actor: string;
    ip: string | null;
    at: string;
}

interface Filters {
    type: string;
    event: string;
    subject_id: number | null;
    actor: string;
    from: string;
    to: string;
}

interface Props {
    logs: { data: Log[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: Filters;
    types: string[];
}

const show = (value: unknown) => (value === null || value === undefined ? '∅' : typeof value === 'object' ? JSON.stringify(value) : String(value));

function Diff({ old, next }: { old: Values; next: Values }) {
    const keys = Array.from(new Set([...Object.keys(old ?? {}), ...Object.keys(next ?? {})]));

    if (keys.length === 0) return null;

    return (
        <table className="mt-1 w-full text-xs">
            <tbody>
                {keys.map((key) => (
                    <tr key={key} className="align-top">
                        <td className="text-muted-foreground w-40 py-0.5 pr-2 font-mono">{key}</td>
                        {old && <td className="py-0.5 pr-2 break-all text-red-700 line-through dark:text-red-400">{show(old[key])}</td>}
                        {next && <td className="py-0.5 break-all text-green-700 dark:text-green-400">{show(next[key])}</td>}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export default function Audit({ logs, filters, types }: Props) {
    const [local, setLocal] = useState<Filters>(filters);
    const apply = (overrides: Partial<Filters> = {}) => router.get('/admin/audit', { ...local, ...overrides }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log" />
            <div className="flex flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Audit log ({logs.total})</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Append-only record of changes to results, fees, payments, admissions and records. Entries cannot be edited or deleted.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <select
                                className="bg-background rounded-md border px-2 text-sm"
                                value={local.type}
                                onChange={(e) => {
                                    setLocal({ ...local, type: e.target.value });
                                    apply({ type: e.target.value });
                                }}
                            >
                                <option value="">Any record type</option>
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                            <Input
                                className="w-28"
                                placeholder="Record id"
                                value={local.subject_id ?? ''}
                                onChange={(e) => setLocal({ ...local, subject_id: Number(e.target.value) || null })}
                            />
                            <Input
                                className="w-44"
                                placeholder="Event (e.g. updated)"
                                value={local.event}
                                onChange={(e) => setLocal({ ...local, event: e.target.value })}
                            />
                            <Input
                                className="w-44"
                                placeholder="Actor name/email"
                                value={local.actor}
                                onChange={(e) => setLocal({ ...local, actor: e.target.value })}
                            />
                            <Input className="w-40" type="date" value={local.from} onChange={(e) => setLocal({ ...local, from: e.target.value })} />
                            <Input className="w-40" type="date" value={local.to} onChange={(e) => setLocal({ ...local, to: e.target.value })} />
                            <Button variant="outline" onClick={() => apply()}>
                                Filter
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {logs.data.length === 0 && <p className="text-muted-foreground text-sm">No entries match.</p>}
                        {logs.data.map((log) => (
                            <div key={log.id} className="rounded-lg border p-2 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={log.event === 'deleted' ? 'destructive' : 'outline'}>{log.event}</Badge>
                                    <span className="font-medium">
                                        {log.type}
                                        {log.subject_id ? ` #${log.subject_id}` : ''}
                                    </span>
                                    <span className="text-muted-foreground">
                                        by {log.actor} · {log.at}
                                        {log.ip ? ` · ${log.ip}` : ''}
                                    </span>
                                </div>
                                {log.description && <div className="mt-1">{log.description}</div>}
                                <Diff old={log.old} next={log.new} />
                            </div>
                        ))}
                        <div className="flex flex-wrap gap-1 pt-2">
                            {logs.links.map((link, i) =>
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
            </div>
        </AppLayout>
    );
}
