import { ChartCard, ColumnChart, compact, HBarChart, Meter, naira, nairaCompact, StatTile, type Datum } from '@/components/viz/charts';
import {
    DashboardToolbar,
    type DashboardFilterValues,
    type DashboardScopeInfo,
    type FacultyOption,
    type SessionOption,
} from '@/components/viz/dashboard-toolbar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface Props {
    session: string | null;
    semester: string | null;
    range: string;
    filters: DashboardFilterValues;
    scope: DashboardScopeInfo;
    options: { sessions: SessionOption[]; faculties: FacultyOption[] };
    academics: {
        activeStudents: number;
        registered: number;
        onProbation: number;
        byLevel: Datum[];
        byDepartment: Datum[];
        pipeline: Datum[];
        classes: Datum[];
    } | null;
    finance: {
        invoiced: number;
        collected: number;
        invoices: number;
        fullyPaid: number;
        outstanding: number;
        received: number;
        daily: boolean;
        byPeriod: Datum[];
    } | null;
    admissions: { session: string; drafts: number; funnel: Datum[] } | null;
    hostels: { name: string; capacity: number; occupied: number; confirmed: number }[] | null;
}

const percent = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 100) : 0);

export default function Dashboard({ session, semester, range, filters, scope, options, academics, finance, admissions, hostels }: Props) {
    const nothing = !academics && !finance && !admissions && !hostels;
    const scoped = !scope.institution;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="viz-root flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">{scoped ? `${scope.label} overview` : 'Institution overview'}</h1>
                    <p className="text-muted-foreground text-sm">
                        {session ? `${session} session` : 'No current session'}
                        {semester ? ` · ${semester}` : ''}
                    </p>
                </div>

                {!nothing && (
                    <DashboardToolbar
                        path="/dashboard"
                        filters={filters}
                        sessions={options.sessions}
                        faculties={academics || finance || admissions ? options.faculties : []}
                        scope={scope}
                        showSemester={!!academics}
                        showRange={!!finance}
                    />
                )}

                {nothing && (
                    <p className="text-muted-foreground text-sm">Your role has no dashboard sections. Use the sidebar to reach your work queues.</p>
                )}

                {academics && (
                    <section className="space-y-4" aria-labelledby="academics-heading">
                        <h2 id="academics-heading" className="text-sm font-semibold tracking-wide uppercase">
                            Academics
                        </h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            <StatTile label="Active students" value={compact(academics.activeStudents)} hint="Active, on probation or spill-over" />
                            <StatTile
                                label="Registered this semester"
                                value={`${percent(academics.registered, academics.activeStudents)}%`}
                                hint={`${academics.registered.toLocaleString()} of ${academics.activeStudents.toLocaleString()} students`}
                            >
                                <Meter value={academics.registered} max={academics.activeStudents} label="Registration progress" />
                            </StatTile>
                            <StatTile
                                label="On academic probation"
                                value={academics.onProbation.toLocaleString()}
                                hint={
                                    academics.onProbation > 0 ? (
                                        <span className="flex items-center gap-1">
                                            <span aria-hidden>⚠</span> CGPA below the probation threshold
                                        </span>
                                    ) : (
                                        'No students below the threshold'
                                    )
                                }
                            />
                        </div>
                        <div className={`grid gap-4 ${academics.byDepartment.length > 0 ? 'lg:grid-cols-2' : 'lg:grid-cols-3'}`}>
                            {academics.byDepartment.length > 0 && (
                                <ChartCard title="Students by department" subtitle="Active students" table={academics.byDepartment}>
                                    <HBarChart data={academics.byDepartment} />
                                </ChartCard>
                            )}
                            <ChartCard title="Students by level" subtitle="Active students" table={academics.byLevel}>
                                <HBarChart data={academics.byLevel} />
                            </ChartCard>
                            <ChartCard
                                title="Results approval pipeline"
                                subtitle={`${scoped ? 'Results of courses offered here' : 'Course results'}${semester ? `, ${semester}` : ''}`}
                                table={academics.pipeline}
                            >
                                <HBarChart data={academics.pipeline} />
                            </ChartCard>
                            <ChartCard title="Students by current class" subtitle="From each student's latest CGPA" table={academics.classes}>
                                <HBarChart data={academics.classes} />
                            </ChartCard>
                        </div>
                    </section>
                )}

                {finance && (
                    <section className="space-y-4" aria-labelledby="finance-heading">
                        <h2 id="finance-heading" className="text-sm font-semibold tracking-wide uppercase">
                            Finance
                        </h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            <StatTile
                                label="School fees collected"
                                value={nairaCompact(finance.collected)}
                                hint={`${percent(finance.collected, finance.invoiced)}% of ${naira(finance.invoiced)} invoiced`}
                            >
                                <Meter value={finance.collected} max={finance.invoiced} label="Fee collection rate" />
                            </StatTile>
                            <StatTile label="Outstanding school fees" value={nairaCompact(finance.outstanding)} hint={naira(finance.outstanding)} />
                            <StatTile
                                label="Students fully paid"
                                value={`${percent(finance.fullyPaid, finance.invoices)}%`}
                                hint={`${finance.fullyPaid.toLocaleString()} of ${finance.invoices.toLocaleString()} school-fees invoices settled`}
                            >
                                <Meter value={finance.fullyPaid} max={finance.invoices} label="Invoices fully paid" />
                            </StatTile>
                        </div>
                        <ChartCard
                            title={`Payments received by ${finance.daily ? 'day' : 'month'}`}
                            subtitle={`${range} · ${naira(finance.received)} in successful payments${scoped ? ' from these students' : ', all fee types'}`}
                            table={finance.byPeriod}
                        >
                            <ColumnChart
                                data={finance.byPeriod}
                                format={nairaCompact}
                                tooltipFormat={naira}
                                empty="No payments recorded in this period."
                            />
                        </ChartCard>
                    </section>
                )}

                {(admissions || hostels) && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {admissions && (
                            <section className="space-y-4" aria-labelledby="admissions-heading">
                                <h2 id="admissions-heading" className="text-sm font-semibold tracking-wide uppercase">
                                    Admissions
                                </h2>
                                <ChartCard
                                    title="Admissions funnel"
                                    subtitle={`${admissions.session} cycle · each stage counts everyone who reached it${admissions.drafts ? ` · ${admissions.drafts} unsubmitted draft(s)` : ''}`}
                                    table={admissions.funnel}
                                >
                                    <HBarChart data={admissions.funnel} />
                                </ChartCard>
                            </section>
                        )}
                        {hostels && (
                            <section className="space-y-4" aria-labelledby="hostels-heading">
                                <h2 id="hostels-heading" className="text-sm font-semibold tracking-wide uppercase">
                                    Hostels
                                </h2>
                                {scoped && <p className="text-muted-foreground text-xs">Halls are shared, so occupancy covers all students.</p>}
                                <div className="grid gap-4">
                                    {hostels.length === 0 && <p className="text-muted-foreground text-sm">No open hostels.</p>}
                                    {hostels.map((h) => (
                                        <StatTile
                                            key={h.name}
                                            label={h.name}
                                            value={`${h.occupied.toLocaleString()} / ${h.capacity.toLocaleString()}`}
                                            hint={`${percent(h.occupied, h.capacity)}% of bed spaces taken · ${h.confirmed} confirmed, ${h.occupied - h.confirmed} reserved`}
                                        >
                                            <Meter value={h.occupied} max={h.capacity} label={`${h.name} occupancy`} warnAt={0.9} />
                                        </StatTile>
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
