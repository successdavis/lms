import { FlashMessages } from '@/components/portal/flash-messages';
import { StatCard } from '@/components/portal/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Results', href: '/student/results' }];

interface CourseResult {
    code: string;
    title: string;
    credit_units: number;
    is_carryover: boolean;
    total_score: string;
    grade_letter: string;
    grade_point: string;
    quality_points: string;
    is_passed: boolean;
}

interface SemesterBlock {
    semester: string;
    session: string;
    courses: CourseResult[];
    gpa: string | null;
    cgpa: string | null;
    standing: string | null;
}

interface Props {
    semesters: SemesterBlock[];
    cgpa: string | null;
    classification: string | null;
}

export default function Results({ semesters, cgpa, classification }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Results" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="grid gap-4 md:grid-cols-2">
                    <StatCard label="Cumulative GPA (CGPA)" value={cgpa ?? '—'} />
                    <StatCard label="Current classification" value={classification ?? '—'} hint="Based on your CGPA to date" />
                </div>

                {semesters.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-center">
                            No approved results yet. Results appear here after Senate/Academic Board approval.
                        </CardContent>
                    </Card>
                )}

                {semesters.map((block, i) => (
                    <Card key={i}>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-3 text-base">
                                {block.session} — {block.semester}
                                {block.gpa && <Badge variant="outline">GPA {block.gpa}</Badge>}
                                {block.cgpa && <Badge variant="outline">CGPA {block.cgpa}</Badge>}
                                {block.standing && block.standing !== 'good' && <Badge variant="destructive">{block.standing}</Badge>}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left">
                                        <th className="py-2 pr-2">Course</th>
                                        <th className="py-2 pr-2">Title</th>
                                        <th className="py-2 pr-2 text-right">Units</th>
                                        <th className="py-2 pr-2 text-right">Score</th>
                                        <th className="py-2 pr-2 text-center">Grade</th>
                                        <th className="py-2 text-right">QP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {block.courses.map((course, j) => (
                                        <tr key={j} className="border-b last:border-0">
                                            <td className="py-2 pr-2 font-medium">
                                                {course.code}
                                                {course.is_carryover && (
                                                    <Badge variant="destructive" className="ml-2">
                                                        CO
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground py-2 pr-2">{course.title}</td>
                                            <td className="py-2 pr-2 text-right">{course.credit_units}</td>
                                            <td className="py-2 pr-2 text-right">{course.total_score}</td>
                                            <td className={`py-2 pr-2 text-center font-semibold ${course.is_passed ? '' : 'text-destructive'}`}>
                                                {course.grade_letter}
                                            </td>
                                            <td className="py-2 text-right">{course.quality_points}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
