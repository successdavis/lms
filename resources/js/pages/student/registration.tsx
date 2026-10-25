import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Course Registration', href: '/student/registration' }];

interface CourseRow {
    id: number;
    code: string;
    title: string;
    credit_units: number;
    type?: string;
}

interface Props {
    semester: { name: string } | null;
    registrationOpen: boolean;
    curriculum: CourseRow[];
    carryovers: CourseRow[];
    registration: {
        status: string;
        total_units: number;
        registered_courses: { course: { code: string; title: string }; credit_units: number; is_carryover: boolean }[];
    } | null;
    limits: { min: number; max: number };
}

export default function Registration({ semester, registrationOpen, curriculum, carryovers, registration, limits }: Props) {
    const initialIds = useMemo(
        () => [...carryovers.map((c) => c.id), ...curriculum.filter((c) => !carryovers.some((co) => co.id === c.id)).map((c) => c.id)],
        [carryovers, curriculum],
    );

    const { data, setData, post, processing } = useForm<{ course_ids: number[] }>({ course_ids: initialIds });

    const allCourses = useMemo(() => {
        const map = new Map<number, CourseRow & { isCarryover: boolean }>();
        carryovers.forEach((c) => map.set(c.id, { ...c, isCarryover: true }));
        curriculum.forEach((c) => {
            if (!map.has(c.id)) map.set(c.id, { ...c, isCarryover: false });
        });
        return Array.from(map.values());
    }, [carryovers, curriculum]);

    const totalUnits = allCourses.filter((c) => data.course_ids.includes(c.id)).reduce((sum, c) => sum + c.credit_units, 0);

    const toggle = (id: number, checked: boolean) => {
        setData('course_ids', checked ? [...data.course_ids, id] : data.course_ids.filter((c) => c !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Course Registration" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                {registration && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                Current registration <Badge>{registration.status}</Badge>
                                <span className="text-muted-foreground text-sm font-normal">{registration.total_units} units</span>
                                <span className="ml-auto flex gap-3 text-sm font-normal">
                                    <a href="/student/print/course-form" target="_blank" rel="noreferrer" className="underline">
                                        Print course form
                                    </a>
                                    <a href="/student/print/exam-card" target="_blank" rel="noreferrer" className="underline">
                                        Print exam card
                                    </a>
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-1 text-sm md:grid-cols-2">
                                {registration.registered_courses.map((rc, i) => (
                                    <li key={i} className="flex items-center gap-2">
                                        <span className="font-medium">{rc.course.code}</span>
                                        <span className="text-muted-foreground">{rc.course.title}</span>
                                        <span className="text-muted-foreground">({rc.credit_units}u)</span>
                                        {rc.is_carryover && <Badge variant="destructive">carryover</Badge>}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {semester ? `${semester.name} — select courses` : 'No current semester configured'}
                        </CardTitle>
                        {semester && (
                            <p className="text-muted-foreground text-sm">
                                {registrationOpen ? 'Registration is open.' : 'Registration is closed.'} Unit load: min {limits.min}, max {limits.max}
                                . Carryovers are pre-selected and must be registered first.
                            </p>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {allCourses.map((course) => (
                            <label key={course.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <Checkbox
                                    checked={data.course_ids.includes(course.id)}
                                    onCheckedChange={(checked) => toggle(course.id, checked === true)}
                                    disabled={!registrationOpen}
                                />
                                <span className="font-medium">{course.code}</span>
                                <span className="text-muted-foreground flex-1 text-sm">{course.title}</span>
                                <span className="text-muted-foreground text-sm">{course.credit_units} units</span>
                                {course.isCarryover ? (
                                    <Badge variant="destructive">carryover</Badge>
                                ) : (
                                    course.type && <Badge variant="outline">{course.type}</Badge>
                                )}
                            </label>
                        ))}

                        <div className="flex items-center justify-between pt-2">
                            <div className="text-sm">
                                Total:{' '}
                                <span className={totalUnits > limits.max || totalUnits < limits.min ? 'text-destructive' : ''}>
                                    {totalUnits} units
                                </span>
                            </div>
                            <Button onClick={() => post('/student/registration')} disabled={!registrationOpen || processing}>
                                {registration ? 'Update registration' : 'Submit registration'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
