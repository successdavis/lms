import { FlashMessages } from '@/components/portal/flash-messages';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface StudentRow {
    registered_course_id: number;
    matric_no: string | null;
    name: string;
    is_carryover: boolean;
    ca_score: string | null;
    exam_score: string | null;
    total_score: string | null;
    grade_letter: string | null;
    status: string;
    locked: boolean;
}

interface Props {
    course: { id: number; code: string; title: string; credit_units: number; ca_weight: number; exam_weight: number };
    semester: { name: string };
    students: StudentRow[];
}

export default function Scoresheet({ course, semester, students }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Courses', href: '/lecturer/courses' },
        { title: course.code, href: `/lecturer/courses/${course.id}` },
    ];

    const [scores, setScores] = useState<Record<number, { ca: string; exam: string }>>(() =>
        Object.fromEntries(students.map((s) => [s.registered_course_id, { ca: s.ca_score ?? '', exam: s.exam_score ?? '' }])),
    );
    const [saving, setSaving] = useState(false);

    const update = (id: number, field: 'ca' | 'exam', value: string) => {
        setScores((prev) => ({ ...prev, [id]: { ...prev[id], [field]: value } }));
    };

    const submit = () => {
        const payload = students
            .filter((s) => !s.locked)
            .filter((s) => scores[s.registered_course_id].ca !== '' && scores[s.registered_course_id].exam !== '')
            .map((s) => ({
                registered_course_id: s.registered_course_id,
                ca_score: Number(scores[s.registered_course_id].ca),
                exam_score: Number(scores[s.registered_course_id].exam),
            }));

        setSaving(true);
        router.post(`/lecturer/courses/${course.id}/scores`, { scores: payload }, { onFinish: () => setSaving(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Score sheet — ${course.code}`} />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {course.code} — {course.title}
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            {semester.name} · {course.credit_units} units · CA /{course.ca_weight} + Exam /{course.exam_weight}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left">
                                    <th className="py-2 pr-2">Matric No</th>
                                    <th className="py-2 pr-2">Name</th>
                                    <th className="py-2 pr-2">CA</th>
                                    <th className="py-2 pr-2">Exam</th>
                                    <th className="py-2 pr-2 text-right">Total</th>
                                    <th className="py-2 pr-2 text-center">Grade</th>
                                    <th className="py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.map((student) => (
                                    <tr key={student.registered_course_id} className="border-b last:border-0">
                                        <td className="py-2 pr-2 font-mono text-xs">
                                            {student.matric_no}
                                            {student.is_carryover && (
                                                <Badge variant="destructive" className="ml-1">
                                                    CO
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="py-2 pr-2">{student.name}</td>
                                        <td className="py-2 pr-2">
                                            <Input
                                                type="number"
                                                min={0}
                                                max={course.ca_weight}
                                                className="h-8 w-20"
                                                value={scores[student.registered_course_id].ca}
                                                onChange={(e) => update(student.registered_course_id, 'ca', e.target.value)}
                                                disabled={student.locked}
                                            />
                                        </td>
                                        <td className="py-2 pr-2">
                                            <Input
                                                type="number"
                                                min={0}
                                                max={course.exam_weight}
                                                className="h-8 w-20"
                                                value={scores[student.registered_course_id].exam}
                                                onChange={(e) => update(student.registered_course_id, 'exam', e.target.value)}
                                                disabled={student.locked}
                                            />
                                        </td>
                                        <td className="py-2 pr-2 text-right">{student.total_score ?? '—'}</td>
                                        <td className="py-2 pr-2 text-center font-semibold">{student.grade_letter ?? '—'}</td>
                                        <td className="py-2">
                                            <Badge variant={student.locked ? 'default' : 'outline'}>{student.status}</Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {students.length === 0 ? (
                            <p className="text-muted-foreground py-6 text-center text-sm">No students registered for this course yet.</p>
                        ) : (
                            <div className="flex justify-end pt-4">
                                <Button onClick={submit} disabled={saving}>
                                    Save scores
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
