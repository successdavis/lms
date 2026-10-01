import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fee Management', href: '/bursary/fees' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface Structure {
    id: number;
    amount: string;
    entry_mode: string | null;
    indigene_scope: string | null;
    programme: { name: string; code: string } | null;
    level: { name: string } | null;
}

interface FeeType {
    id: number;
    name: string;
    code: string;
    blocks_registration: boolean;
    is_recurring: boolean;
    structures: Structure[];
}

interface Props {
    sessions: { id: number; name: string; is_current: boolean }[];
    selectedSession: number | null;
    feeTypes: FeeType[];
    programmes: { id: number; name: string; code: string }[];
    levels: { id: number; name: string; code: string }[];
}

export default function BursaryFees({ sessions, selectedSession, feeTypes, programmes, levels }: Props) {
    const typeForm = useForm({ name: '', code: '', blocks_registration: false, is_recurring: true });
    const structureForm = useForm({
        fee_type_id: '',
        academic_session_id: String(selectedSession ?? ''),
        programme_id: '',
        level_id: '',
        indigene_scope: '',
        amount: '',
    });

    const submitType = (e: React.FormEvent) => {
        e.preventDefault();
        typeForm.post('/bursary/fees/types', { onSuccess: () => typeForm.reset() });
    };

    const submitStructure = (e: React.FormEvent) => {
        e.preventDefault();
        structureForm
            .transform((data) => ({
                ...data,
                programme_id: data.programme_id || null,
                level_id: data.level_id || null,
                indigene_scope: data.indigene_scope || null,
            }))
            .post('/bursary/fees/structures', { onSuccess: () => structureForm.reset('amount') });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fee Management" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="flex items-center gap-2">
                    <Label htmlFor="session" className="text-sm">
                        Session
                    </Label>
                    <select
                        id="session"
                        className="bg-background rounded-md border px-2 py-1 text-sm"
                        value={selectedSession ?? ''}
                        onChange={(e) => router.get('/bursary/fees', { session: e.target.value }, { preserveState: true })}
                    >
                        {sessions.map((session) => (
                            <option key={session.id} value={session.id}>
                                {session.name}
                                {session.is_current ? ' (current)' : ''}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">New fee type</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitType} className="space-y-3">
                                <Input
                                    placeholder="Name (e.g. Hostel Fee)"
                                    value={typeForm.data.name}
                                    onChange={(e) => typeForm.setData('name', e.target.value)}
                                />
                                <Input
                                    placeholder="Code (e.g. HST)"
                                    value={typeForm.data.code}
                                    onChange={(e) => typeForm.setData('code', e.target.value)}
                                />
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={typeForm.data.blocks_registration}
                                        onCheckedChange={(c) => typeForm.setData('blocks_registration', c === true)}
                                    />
                                    Blocks course registration until paid
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={typeForm.data.is_recurring}
                                        onCheckedChange={(c) => typeForm.setData('is_recurring', c === true)}
                                    />
                                    Charged every session (uncheck for one-off, e.g. acceptance fee)
                                </label>
                                <Button type="submit" disabled={typeForm.processing}>
                                    Create fee type
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">New fee rule</CardTitle>
                            <p className="text-muted-foreground text-sm">Most specific rule wins: programme &gt; level &gt; indigene scope.</p>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitStructure} className="space-y-3">
                                <select
                                    className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                    value={structureForm.data.fee_type_id}
                                    onChange={(e) => structureForm.setData('fee_type_id', e.target.value)}
                                >
                                    <option value="">Fee type…</option>
                                    {feeTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                        </option>
                                    ))}
                                </select>
                                <div className="grid grid-cols-2 gap-2">
                                    <select
                                        className="bg-background rounded-md border px-2 py-2 text-sm"
                                        value={structureForm.data.programme_id}
                                        onChange={(e) => structureForm.setData('programme_id', e.target.value)}
                                    >
                                        <option value="">All programmes</option>
                                        {programmes.map((programme) => (
                                            <option key={programme.id} value={programme.id}>
                                                {programme.code}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        className="bg-background rounded-md border px-2 py-2 text-sm"
                                        value={structureForm.data.level_id}
                                        onChange={(e) => structureForm.setData('level_id', e.target.value)}
                                    >
                                        <option value="">All levels</option>
                                        {levels.map((level) => (
                                            <option key={level.id} value={level.id}>
                                                {level.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <select
                                    className="bg-background w-full rounded-md border px-2 py-2 text-sm"
                                    value={structureForm.data.indigene_scope}
                                    onChange={(e) => structureForm.setData('indigene_scope', e.target.value)}
                                >
                                    <option value="">Indigenes & non-indigenes</option>
                                    <option value="indigene">Indigenes only</option>
                                    <option value="non_indigene">Non-indigenes only</option>
                                </select>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    placeholder="Amount (₦)"
                                    value={structureForm.data.amount}
                                    onChange={(e) => structureForm.setData('amount', e.target.value)}
                                />
                                <Button type="submit" disabled={structureForm.processing}>
                                    Add rule
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                {feeTypes.map((type) => (
                    <Card key={type.id}>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                {type.name} <span className="text-muted-foreground text-sm font-normal">({type.code})</span>
                                {type.blocks_registration && <Badge variant="destructive">blocks registration</Badge>}
                                <Badge variant="outline">{type.is_recurring ? 'per session' : 'one-off'}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {type.structures.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No rules for this session.</p>
                            ) : (
                                <ul className="space-y-1">
                                    {type.structures.map((structure) => (
                                        <li key={structure.id} className="flex flex-wrap items-center gap-2 rounded border p-2 text-sm">
                                            <span className="font-semibold">{naira(structure.amount)}</span>
                                            <span className="text-muted-foreground">
                                                {structure.programme ? structure.programme.code : 'All programmes'} ·{' '}
                                                {structure.level ? structure.level.name : 'All levels'} ·{' '}
                                                {structure.indigene_scope ? structure.indigene_scope.replace('_', '-') : 'everyone'}
                                            </span>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive ml-auto"
                                                onClick={() => router.delete(`/bursary/fees/structures/${structure.id}`)}
                                            >
                                                Remove
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
