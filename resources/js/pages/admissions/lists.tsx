import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admission Lists', href: '/admissions/lists' }];

interface ListRow {
    id: number;
    name: string;
    published_at: string | null;
    applicants_count: number;
    accepted_count: number;
    matriculated_count: number;
}

interface Props {
    cycle: { id: number; session: string; default_cutoff: string } | null;
    lists: ListRow[];
    awaitingAdmission: number;
}

export default function AdmissionLists({ cycle, lists, awaitingAdmission }: Props) {
    const form = useForm({ name: '' });

    const createList = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admissions/lists', { onSuccess: () => form.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admission Lists" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                {!cycle && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center">No active admission cycle.</CardContent>
                    </Card>
                )}

                {cycle && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {cycle.session} admission lists
                                    <span className="text-muted-foreground ml-2 text-sm font-normal">
                                        {awaitingAdmission} screened applicant(s) awaiting admission
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={createList} className="flex max-w-md gap-2">
                                    <Input
                                        placeholder="List name (e.g. Merit List, Supplementary List)"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                    />
                                    <Button type="submit" disabled={form.processing}>
                                        Create list
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {lists.map((list) => (
                            <Card key={list.id}>
                                <CardHeader>
                                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                        {list.name}
                                        {list.published_at ? <Badge>published</Badge> : <Badge variant="outline">unpublished</Badge>}
                                        <span className="text-muted-foreground text-sm font-normal">
                                            {list.applicants_count} admitted · {list.accepted_count} accepted · {list.matriculated_count} matriculated
                                        </span>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    <Button variant="outline" onClick={() => router.post(`/admissions/lists/${list.id}/admit`, { mode: 'auto' })}>
                                        Admit all above cutoff ({cycle.default_cutoff})
                                    </Button>
                                    {!list.published_at && (
                                        <Button variant="outline" onClick={() => router.post(`/admissions/lists/${list.id}/publish`)}>
                                            Publish list
                                        </Button>
                                    )}
                                    {list.accepted_count > 0 && (
                                        <Button onClick={() => router.post(`/admissions/lists/${list.id}/matriculate`)}>
                                            Matriculate accepted ({list.accepted_count})
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
