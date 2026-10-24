import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Hostels', href: '/hostels' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface HostelRow {
    id: number;
    name: string;
    gender: string;
    fee: string;
    is_active: boolean;
    rooms: number;
    capacity: number;
    occupied: number;
}

interface AllocationRow {
    id: number;
    student: string;
    matric_no: string | null;
    hostel: string;
    room: string;
    status: string;
    expires_at: string | null;
}

interface Props {
    session: { name: string } | null;
    hostels: HostelRow[];
    allocations: AllocationRow[];
}

export default function HostelAdmin({ session, hostels, allocations }: Props) {
    const form = useForm({ name: '', gender: 'male', fee: '' });
    const [rooms, setRooms] = useState<Record<number, { prefix: string; from: string; to: string; capacity: string }>>({});

    const roomForm = (id: number) => rooms[id] ?? { prefix: '', from: '1', to: '10', capacity: '4' };
    const setRoomForm = (id: number, field: string, value: string) => setRooms((prev) => ({ ...prev, [id]: { ...roomForm(id), [field]: value } }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Hostels" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">New hostel</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/hostels', { onSuccess: () => form.reset() });
                            }}
                            className="flex flex-wrap gap-2"
                        >
                            <Input
                                className="w-56"
                                placeholder="Name (e.g. Moremi Hall)"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            <select
                                className="bg-background rounded-md border px-2 text-sm"
                                value={form.data.gender}
                                onChange={(e) => form.setData('gender', e.target.value)}
                            >
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="mixed">Mixed</option>
                            </select>
                            <Input
                                className="w-40"
                                type="number"
                                min={0}
                                placeholder="Fee per session (₦)"
                                value={form.data.fee}
                                onChange={(e) => form.setData('fee', e.target.value)}
                            />
                            <Button type="submit" disabled={form.processing}>
                                Create
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {hostels.map((hostel) => {
                    const r = roomForm(hostel.id);
                    return (
                        <Card key={hostel.id}>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                    {hostel.name}
                                    <Badge variant="outline">{hostel.gender}</Badge>
                                    <Badge variant={hostel.is_active ? 'default' : 'destructive'}>{hostel.is_active ? 'open' : 'closed'}</Badge>
                                    <span className="text-muted-foreground text-sm font-normal">
                                        {naira(hostel.fee)} · {hostel.rooms} rooms · {hostel.occupied}/{hostel.capacity} beds taken
                                        {session ? ` (${session.name})` : ''}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-2 text-sm">
                                <Input
                                    className="h-8 w-20"
                                    placeholder="Prefix"
                                    value={r.prefix}
                                    onChange={(e) => setRoomForm(hostel.id, 'prefix', e.target.value)}
                                />
                                <Input
                                    className="h-8 w-20"
                                    type="number"
                                    value={r.from}
                                    onChange={(e) => setRoomForm(hostel.id, 'from', e.target.value)}
                                />
                                <span>to</span>
                                <Input
                                    className="h-8 w-20"
                                    type="number"
                                    value={r.to}
                                    onChange={(e) => setRoomForm(hostel.id, 'to', e.target.value)}
                                />
                                <Input
                                    className="h-8 w-24"
                                    type="number"
                                    value={r.capacity}
                                    onChange={(e) => setRoomForm(hostel.id, 'capacity', e.target.value)}
                                />
                                <span className="text-muted-foreground">beds each</span>
                                <Button size="sm" variant="outline" onClick={() => router.post(`/hostels/${hostel.id}/rooms`, r)}>
                                    Add rooms
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => router.post(`/hostels/${hostel.id}/toggle`)}>
                                    {hostel.is_active ? 'Close booking' : 'Open booking'}
                                </Button>
                            </CardContent>
                        </Card>
                    );
                })}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Current allocations ({allocations.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left">
                                    <th className="py-2 pr-2">Student</th>
                                    <th className="py-2 pr-2">Hostel / Room</th>
                                    <th className="py-2 pr-2">Status</th>
                                    <th className="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {allocations.map((a) => (
                                    <tr key={a.id} className="border-b last:border-0">
                                        <td className="py-2 pr-2">
                                            {a.student} <span className="text-muted-foreground font-mono text-xs">{a.matric_no}</span>
                                        </td>
                                        <td className="py-2 pr-2">
                                            {a.hostel}, {a.room}
                                        </td>
                                        <td className="py-2 pr-2">
                                            <Badge variant={a.status === 'confirmed' ? 'default' : 'outline'}>{a.status}</Badge>
                                            {a.expires_at && <span className="text-muted-foreground ml-2 text-xs">until {a.expires_at}</span>}
                                        </td>
                                        <td className="py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive"
                                                onClick={() => router.delete(`/hostels/allocations/${a.id}`)}
                                            >
                                                Cancel
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
