import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Students', href: '/admin/students' }];

interface StudentRow {
    id: number;
    matric_no: string | null;
    status: string;
    user: { name: string; email: string };
    programme: { name: string; code: string };
    level: { name: string };
}

interface Props {
    students: {
        data: StudentRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search: string };
}

export default function Students({ students, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/students', { search }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Students" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base">
                            <span>Student records ({students.total})</span>
                        </CardTitle>
                        <form onSubmit={submitSearch} className="flex max-w-sm gap-2">
                            <Input placeholder="Search matric no or name…" value={search} onChange={(e) => setSearch(e.target.value)} />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left">
                                    <th className="py-2 pr-2">Matric No</th>
                                    <th className="py-2 pr-2">Name</th>
                                    <th className="py-2 pr-2">Programme</th>
                                    <th className="py-2 pr-2">Level</th>
                                    <th className="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.data.map((student) => (
                                    <tr key={student.id} className="border-b last:border-0">
                                        <td className="py-2 pr-2 font-mono text-xs">{student.matric_no ?? '—'}</td>
                                        <td className="py-2 pr-2">
                                            <div>{student.user.name}</div>
                                            <div className="text-muted-foreground text-xs">{student.user.email}</div>
                                        </td>
                                        <td className="py-2 pr-2">{student.programme.name}</td>
                                        <td className="py-2 pr-2">{student.level.name}</td>
                                        <td className="py-2">
                                            <Badge variant={student.status === 'active' ? 'default' : 'destructive'}>{student.status}</Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="flex flex-wrap gap-1 pt-4">
                            {students.links.map((link, i) =>
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
