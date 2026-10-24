import { FlashMessages } from '@/components/portal/flash-messages';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

interface Item {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string;
}

interface Props {
    notifications: { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
}

export default function Notifications({ notifications }: Props) {
    const unread = notifications.data.some((n) => !n.read);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">Notifications ({notifications.total})</CardTitle>
                        {unread && (
                            <Button size="sm" variant="outline" onClick={() => router.post('/notifications/read-all')}>
                                Mark all as read
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {notifications.data.length === 0 && <p className="text-muted-foreground text-sm">You have no notifications yet.</p>}
                        {notifications.data.map((n) => (
                            <Link
                                key={n.id}
                                href={`/notifications/${n.id}`}
                                className={`hover:bg-accent block rounded-lg border p-3 text-sm ${n.read ? 'opacity-70' : 'border-l-primary border-l-4'}`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className={n.read ? '' : 'font-semibold'}>{n.title}</span>
                                    <span className="text-muted-foreground shrink-0 text-xs">{n.at}</span>
                                </div>
                                <p className="text-muted-foreground mt-1">{n.body}</p>
                            </Link>
                        ))}
                        <div className="flex flex-wrap gap-1 pt-2">
                            {notifications.links.map((link, i) =>
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
