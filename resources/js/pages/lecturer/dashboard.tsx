import { ChartCard, HBarChart, Meter, StatTile, type Datum } from '@/components/viz/charts';
import { DashboardToolbar, type DashboardFilterValues, type SessionOption } from '@/components/viz/dashboard-toolbar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Teaching Dashboard', href: '/lecturer/dashboard' }];

interface CourseRow {
    id: number;
    code: string;
    title: string;
    units: number;
    is_coordinator: boolean;
    registered: number;
    scored: number;
    average: number | null;
    passRate: number | null;
    stage: string;
}

interface Props {
    session: string | null;
    semester: { id: number; name: string; is_current: boolean } | null;
    totals: { courses: number; registered: number; scored: number; released: number };
    courses: CourseRow[];
    pipeline: Datum[];
    grades: Datum[];
    filters: DashboardFilterValues;
    options: { sessions: SessionOption[] };
}

const percent = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 100) : 0);

export default function TeachingDashboard({ session, semester, totals, courses, pipeline, grades, filters, options }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teaching Dashboard" />
            <div className="viz-root flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Teaching dashboard</h1>
                    <p className="text-muted-foreground text-sm">
                        {session ? `${session} session` : 'No session'}
                        {semester ? ` · ${semester.name}` : ''}
                    </p>
                </div>

                <DashboardToolbar path="/lecturer/dashboard" filters={filters} sessions={options.sessions} showSemester />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatTile label="Courses this semester" value={totals.courses.toLocaleString()} hint="Allocated to you" />
                    <StatTile
                        label="Students registered"
                        value={totals.registered.toLocaleString()}
                        hint={`Course registrations across ${totals.courses} course${totals.courses === 1 ? '' : 's'}`}
                    />
                    <StatTile
                        label="Scores entered"
                        value={`${percent(totals.scored, totals.registered)}%`}
                        hint={`${totals.scored.toLocaleString()} of ${totals.registered.toLocaleString()} students scored`}
                    >
                        <Meter value={totals.scored} max={totals.registered} label="Scoring progress" />
                    </StatTile>
                    <StatTile
                        label="Results released"
                        value={totals.released.toLocaleString()}
                        hint={`${percent(totals.released, totals.scored)}% of entered results have passed senate`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ChartCard title="Results by approval stage" subtitle="Every result you have entered this semester" table={pipeline}>
                        <HBarChart data={pipeline} />
                    </ChartCard>
                    <ChartCard title="Grade distribution" subtitle="All your courses, scored students only" table={grades}>
                        <HBarChart data={grades} />
                    </ChartCard>
                </div>

                <section aria-labelledby="courses-heading" className="rounded-xl border">
                    <h2 id="courses-heading" className="border-b px-4 py-3 text-sm font-semibold">
                        Courses
                    </h2>
                    {courses.length === 0 ? (
                        <p className="text-muted-foreground p-4 text-sm">No courses allocated to you for this semester.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left text-xs">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Course</th>
                                        <th className="px-4 py-2 text-right font-medium">Registered</th>
                                        <th className="px-4 py-2 font-medium">Scored</th>
                                        <th className="px-4 py-2 text-right font-medium">Average</th>
                                        <th className="px-4 py-2 text-right font-medium">Pass rate</th>
                                        <th className="px-4 py-2 font-medium">Stage</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody style={{ fontVariantNumeric: 'tabular-nums' }}>
                                    {courses.map((c) => (
                                        <tr key={c.id} className="border-t">
                                            <td className="px-4 py-2">
                                                <div className="font-medium">
                                                    {c.code}
                                                    {c.is_coordinator && <span className="text-muted-foreground ml-1 text-xs">(coordinator)</span>}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    {c.title} · {c.units} units
                                                </div>
                                            </td>
                                            <td className="px-4 py-2 text-right">{c.registered}</td>
                                            <td className="w-40 px-4 py-2">
                                                <div className="text-xs">
                                                    {c.scored} / {c.registered}
                                                </div>
                                                <Meter value={c.scored} max={c.registered} label={`${c.code} scoring progress`} />
                                            </td>
                                            <td className="px-4 py-2 text-right">{c.average ?? '—'}</td>
                                            <td className="px-4 py-2 text-right">{c.passRate === null ? '—' : `${c.passRate}%`}</td>
                                            <td className="px-4 py-2">{c.stage}</td>
                                            <td className="px-4 py-2 text-right">
                                                {semester?.is_current && (
                                                    <Link href={`/lecturer/courses/${c.id}`} className="text-primary text-xs underline">
                                                        Score sheet
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
