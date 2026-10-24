import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Hostel', href: '/student/hostel' }];

const naira = (value: string | number) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 }).format(Number(value));

interface Props {
    session: { name: string } | null;
    hostels: { id: number; name: string; gender: string; fee: string; capacity: number; available: number }[];
    allocation: {
        status: string;
        hostel: string;
        room: string;
        expires_at: string | null;
        invoice: { id: number; number: string; status: string; total: string } | null;
    } | null;
}

export default function Hostel({ session, hostels, allocation }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Hostel" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                {allocation && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                {allocation.hostel}, Room {allocation.room}
                                <Badge variant={allocation.status === 'confirmed' ? 'default' : 'outline'}>{allocation.status}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3 text-sm">
                            {allocation.status === 'reserved' && (
                                <>
                                    <span>
                                        Reserved until <span className="font-medium">{allocation.expires_at}</span>. Unpaid reservations lapse and the
                                        bed space is released.
                                    </span>
                                    {allocation.invoice && (
                                        <Button onClick={() => router.post(`/student/fees/${allocation.invoice!.id}/pay`)}>
                                            Pay {naira(allocation.invoice.total)}
                                        </Button>
                                    )}
                                </>
                            )}
                            {allocation.status === 'confirmed' && <span>Your bed space is confirmed for the {session?.name} session.</span>}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Available hostels {session ? `— ${session.name}` : ''}</CardTitle>
                        <p className="text-muted-foreground text-sm">Hostel booking opens once your school fees are paid.</p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {hostels.length === 0 && <p className="text-muted-foreground text-sm">No hostels are open for booking.</p>}
                        {hostels.map((hostel) => (
                            <div key={hostel.id} className="flex flex-wrap items-center gap-3 rounded-lg border p-3 text-sm">
                                <span className="font-medium">{hostel.name}</span>
                                <Badge variant="outline">{hostel.gender}</Badge>
                                <span className="text-muted-foreground">
                                    {hostel.available} of {hostel.capacity} bed spaces free · {naira(hostel.fee)}/session
                                </span>
                                {!allocation && (
                                    <Button
                                        size="sm"
                                        className="ml-auto"
                                        disabled={hostel.available === 0}
                                        onClick={() => router.post(`/student/hostel/${hostel.id}`)}
                                    >
                                        {hostel.available === 0 ? 'Full' : 'Book a bed space'}
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
