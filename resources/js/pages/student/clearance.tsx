import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Final Clearance', href: '/student/clearance' }];

interface Item {
    unit: string;
    label: string;
    status: string;
    note: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
}

interface Props {
    eligible: boolean;
    clearance: { status: string; cleared_at: string | null; items: Item[] } | null;
}

export default function Clearance({ eligible, clearance }: Props) {
    const hasRejections = clearance?.items.some((item) => item.status === 'rejected') ?? false;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Final Clearance" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                            Final-year clearance
                            {clearance && (
                                <Badge variant={clearance.status === 'cleared' ? 'default' : 'outline'}>
                                    {clearance.status === 'cleared' ? `cleared ${clearance.cleared_at ?? ''}` : 'in progress'}
                                </Badge>
                            )}
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Every unit must sign off before you can be placed on the graduation list, collect your statement of result, or be
                            mobilized for NYSC.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {!clearance && !eligible && <p className="text-muted-foreground text-sm">Clearance opens in your final year of study.</p>}
                        {!clearance && eligible && <Button onClick={() => router.post('/student/clearance')}>Start clearance</Button>}

                        {clearance?.items.map((item) => (
                            <div key={item.unit} className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                                <span className="font-medium">{item.label}</span>
                                <Badge variant={item.status === 'cleared' ? 'default' : item.status === 'rejected' ? 'destructive' : 'outline'}>
                                    {item.status}
                                </Badge>
                                {item.reviewed_by && (
                                    <span className="text-muted-foreground text-xs">
                                        {item.reviewed_by}, {item.reviewed_at}
                                    </span>
                                )}
                                {item.note && <span className="text-destructive w-full text-xs">{item.note}</span>}
                            </div>
                        ))}

                        {hasRejections && (
                            <Button variant="outline" onClick={() => router.post('/student/clearance/resubmit')}>
                                I have resolved the issues — resubmit rejected units
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
