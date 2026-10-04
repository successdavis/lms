import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Courses', href: '/lecturer/courses' }];

interface Allocation {
    course: { id: number; code: string; title: string; credit_units: number };
    is_coordinator: boolean;
    registered_students: number;
}

interface Props {
    semester: { name: string } | null;
    allocations: Allocation[];
}

export default function LecturerCourses({ semester, allocations }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Courses" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{semester ? `Allocated courses — ${semester.name}` : 'No current semester'}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {allocations.length === 0 && <p className="text-muted-foreground text-sm">No course allocations this semester.</p>}
                        {allocations.map((allocation) => (
                            <Link
                                key={allocation.course.id}
                                href={`/lecturer/courses/${allocation.course.id}`}
                                className="hover:bg-accent flex items-center gap-3 rounded-lg border p-3"
                            >
                                <span className="font-medium">{allocation.course.code}</span>
                                <span className="text-muted-foreground flex-1 text-sm">{allocation.course.title}</span>
                                {allocation.is_coordinator && <Badge variant="outline">coordinator</Badge>}
                                <span className="text-muted-foreground text-sm">{allocation.registered_students} students</span>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
