import { ChartCard, ColumnChart, HBarChart, Meter, StatTile, type Datum } from '@/components/viz/charts';
import { DashboardToolbar, type DashboardFilterValues, type DashboardScopeInfo, type FacultyOption } from '@/components/viz/dashboard-toolbar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'HR Dashboard', href: '/hr/dashboard' }];

interface DueRow {
    id: number;
    name: string;
    staff_no: string;
    designation: string | null;
    department: string | null;
    years: number;
    next: string | null;
}

interface Props {
    totals: {
        active: number;
        academic: number;
        nonAcademic: number;
        payrollReady: number;
        pendingPromotions: number;
        onLeaveToday: number;
        due: number;
    };
    byDepartment: Datum[];
    byRank: Datum[];
    byGradeLevel: Datum[];
    leaveByType: Datum[];
    promotionsDecided: Datum[];
    due: DueRow[];
    minYears: number;
    range: string;
    filters: DashboardFilterValues;
    scope: DashboardScopeInfo;
    options: { faculties: FacultyOption[] };
}

const percent = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 100) : 0);

export default function HrDashboard({
    totals,
    byDepartment,
    byRank,
    byGradeLevel,
    leaveByType,
    promotionsDecided,
    due,
    minYears,
    range,
    filters,
    scope,
    options,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="HR Dashboard" />
            <div className="viz-root flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">HR dashboard</h1>
                    <p className="text-muted-foreground text-sm">Establishment, payroll readiness, promotions and leave</p>
                </div>

                <DashboardToolbar path="/hr/dashboard" filters={filters} faculties={options.faculties} scope={scope} showRange />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Active staff"
                        value={totals.active.toLocaleString()}
                        hint={`${totals.academic.toLocaleString()} academic · ${totals.nonAcademic.toLocaleString()} non-academic`}
                    />
                    <StatTile
                        label="Payroll details complete"
                        value={`${percent(totals.payrollReady, totals.active)}%`}
                        hint={`${totals.payrollReady} of ${totals.active} have IPPIS, account number and grade level`}
                    >
                        <Meter value={totals.payrollReady} max={totals.active} label="Payroll readiness" />
                    </StatTile>
                    <StatTile label="On leave today" value={totals.onLeaveToday.toLocaleString()} hint="Approved leave covering today" />
                    <StatTile
                        label="Promotion cases pending"
                        value={totals.pendingPromotions.toLocaleString()}
                        hint={`${totals.due.toLocaleString()} more staff due (${minYears}+ years in rank)`}
                    />
                </div>

                <div className={`grid gap-4 ${byDepartment.length > 0 ? 'lg:grid-cols-3' : 'lg:grid-cols-2'}`}>
                    {byDepartment.length > 0 && (
                        <ChartCard title="Staff by department" subtitle="Active staff" table={byDepartment}>
                            <HBarChart data={byDepartment} />
                        </ChartCard>
                    )}
                    <ChartCard title="Academic staff by rank" subtitle="Lowest rank first" table={byRank}>
                        <HBarChart data={byRank} />
                    </ChartCard>
                    <ChartCard title="Staff by grade level" subtitle="CONUASS / CONTISS grade level" table={byGradeLevel}>
                        <ColumnChart data={byGradeLevel} format={(n) => n.toLocaleString()} empty="No grade levels recorded." />
                    </ChartCard>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ChartCard title="Approved leave" subtitle={`${range} · working days by type`} table={leaveByType}>
                        <HBarChart data={leaveByType} unit=" days" />
                    </ChartCard>
                    <ChartCard title="Promotion decisions" subtitle={`${range} · cases decided`} table={promotionsDecided}>
                        <HBarChart data={promotionsDecided} />
                    </ChartCard>
                </div>

                <section aria-labelledby="due-heading" className="rounded-xl border">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 id="due-heading" className="text-sm font-semibold">
                            Due for promotion
                        </h2>
                        <Link href="/hr/promotions" className="text-primary text-xs underline">
                            Open promotions
                        </Link>
                    </div>
                    {due.length === 0 ? (
                        <p className="text-muted-foreground p-4 text-sm">Nobody is due: every eligible member of staff already has a case open.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left text-xs">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Staff</th>
                                        <th className="px-4 py-2 font-medium">Department</th>
                                        <th className="px-4 py-2 font-medium">Current</th>
                                        <th className="px-4 py-2 font-medium">Next</th>
                                        <th className="px-4 py-2 text-right font-medium">Years in rank</th>
                                    </tr>
                                </thead>
                                <tbody style={{ fontVariantNumeric: 'tabular-nums' }}>
                                    {due.map((s) => (
                                        <tr key={s.id} className="border-t">
                                            <td className="px-4 py-2">
                                                <div className="font-medium">{s.name}</div>
                                                <div className="text-muted-foreground text-xs">{s.staff_no}</div>
                                            </td>
                                            <td className="px-4 py-2">{s.department ?? '—'}</td>
                                            <td className="px-4 py-2">{s.designation ?? '—'}</td>
                                            <td className="px-4 py-2">{s.next ?? '—'}</td>
                                            <td className="px-4 py-2 text-right">{s.years}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {totals.due > due.length && (
                                <p className="text-muted-foreground border-t px-4 py-2 text-xs">
                                    Showing the {due.length} longest in rank of {totals.due}. The CSV/PDF export lists everyone.
                                </p>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
