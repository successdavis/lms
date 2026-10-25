import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Staff Register', href: '/hr/staff' }];

interface StaffRow {
    id: number;
    staff_no: string;
    type: string;
    designation: string | null;
    salary_structure: string | null;
    grade_level: number | null;
    step: number | null;
    rank_since: string | null;
    ippis_number: string | null;
    bank_name: string | null;
    account_number: string | null;
    is_active: boolean;
    user: { name: string; email: string };
    department: { name: string } | null;
}

type Editable = Pick<
    StaffRow,
    'designation' | 'salary_structure' | 'grade_level' | 'step' | 'rank_since' | 'ippis_number' | 'bank_name' | 'account_number'
>;

interface Props {
    staff: { data: StaffRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: { search: string };
    structures: string[];
}

export default function StaffRegister({ staff, filters, structures }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<number | null>(null);
    const [form, setForm] = useState<Editable | null>(null);

    const startEdit = (row: StaffRow) => {
        setEditing(row.id);
        setForm({
            designation: row.designation,
            salary_structure: row.salary_structure,
            grade_level: row.grade_level,
            step: row.step,
            rank_since: row.rank_since?.slice(0, 10) ?? null,
            ippis_number: row.ippis_number,
            bank_name: row.bank_name,
            account_number: row.account_number,
        });
    };

    const save = (id: number) => router.patch(`/hr/staff/${id}`, { ...form }, { preserveState: true, onSuccess: () => setEditing(null) });

    const field = (key: keyof Editable, placeholder: string, type = 'text') => (
        <Input
            className="h-8"
            type={type}
            placeholder={placeholder}
            value={(form?.[key] as string | number | null) ?? ''}
            onChange={(e) => setForm((prev) => ({ ...(prev as Editable), [key]: e.target.value === '' ? null : e.target.value }))}
        />
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Staff Register" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Payroll</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Export active staff for upload to IPPIS (or your payroll system), or apply the annual step increment to every active staff
                            member below the top step.
                        </p>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <a href="/hr/payroll/export">
                            <Button variant="outline">Download payroll CSV</Button>
                        </a>
                        <Button
                            variant="outline"
                            onClick={() =>
                                window.confirm('Apply the annual step increment to all active staff?') && router.post('/hr/payroll/step-increment')
                            }
                        >
                            Apply annual step increment
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Staff ({staff.total})</CardTitle>
                        <div className="flex max-w-sm gap-2">
                            <Input
                                placeholder="Search staff no or name…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && router.get('/hr/staff', { search }, { preserveState: true })}
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {staff.data.map((row) => (
                            <div key={row.id} className="rounded-lg border p-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{row.user.name}</span>
                                    <span className="text-muted-foreground font-mono text-xs">{row.staff_no}</span>
                                    <Badge variant="outline">{row.type.replace('_', '-')}</Badge>
                                    {!row.is_active && <Badge variant="destructive">inactive</Badge>}
                                    <span className="text-muted-foreground">
                                        {row.designation ?? '—'} · {row.department?.name ?? 'Non-teaching'} · {row.salary_structure ?? '—'} GL{' '}
                                        {row.grade_level ?? '—'}/{row.step ?? '—'}
                                        {row.ippis_number ? ` · IPPIS ${row.ippis_number}` : ''}
                                    </span>
                                    {editing !== row.id && (
                                        <Button size="sm" variant="ghost" className="ml-auto" onClick={() => startEdit(row)}>
                                            Edit
                                        </Button>
                                    )}
                                </div>
                                {editing === row.id && form && (
                                    <div className="mt-2 grid gap-2 md:grid-cols-4">
                                        {field('designation', 'Designation')}
                                        <select
                                            className="bg-background h-8 rounded-md border px-2 text-sm"
                                            value={form.salary_structure ?? ''}
                                            onChange={(e) => setForm({ ...form, salary_structure: e.target.value || null })}
                                        >
                                            <option value="">Salary structure…</option>
                                            {structures.map((s) => (
                                                <option key={s} value={s}>
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                        {field('grade_level', 'Grade level', 'number')}
                                        {field('step', 'Step', 'number')}
                                        {field('rank_since', 'In rank since', 'date')}
                                        {field('ippis_number', 'IPPIS number')}
                                        {field('bank_name', 'Bank')}
                                        {field('account_number', 'NUBAN (10 digits)')}
                                        <div className="flex gap-2 md:col-span-4">
                                            <Button size="sm" onClick={() => save(row.id)}>
                                                Save
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => setEditing(null)}>
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                        <div className="flex flex-wrap gap-1 pt-2">
                            {staff.links.map((link, i) =>
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
