import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { type FormEvent, useState } from 'react';

export interface DashboardFilterValues {
    session_id?: number | null;
    semester_id?: number | null;
    from?: string;
    to?: string;
    faculty_id?: number | null;
    department_id?: number | null;
}

export interface SessionOption {
    id: number;
    name: string;
    semesters: { id: number; name: string }[];
}

export interface FacultyOption {
    id: number;
    name: string;
    departments: { id: number; name: string }[];
}

export interface DashboardScopeInfo {
    label: string;
    locked: boolean;
    faculty_locked: boolean;
    institution: boolean;
}

const selectClass = 'bg-background h-9 rounded-md border px-2 text-sm';
const fieldLabel = 'text-muted-foreground block text-xs font-medium';

/** Query string for the given filters, without empty values. */
export function filterQuery(values: DashboardFilterValues, extra: Record<string, string> = {}): string {
    const params = new URLSearchParams();
    Object.entries({ ...values, ...extra }).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') params.set(key, String(value));
    });
    return params.toString();
}

/** Local calendar date as YYYY-MM-DD (toISOString would shift it to UTC). */
const isoDay = (d: Date) => [d.getFullYear(), d.getMonth() + 1, d.getDate()].map((n) => String(n).padStart(2, '0')).join('-');

/**
 * One row of filters above a dashboard's charts, applied together, plus
 * CSV/PDF downloads of exactly what is on screen (the applied filters).
 */
export function DashboardToolbar({
    path,
    filters,
    sessions,
    faculties,
    scope,
    showSemester = false,
    showRange = false,
}: {
    path: string;
    filters: DashboardFilterValues;
    sessions?: SessionOption[];
    faculties?: FacultyOption[];
    scope?: DashboardScopeInfo;
    showSemester?: boolean;
    showRange?: boolean;
}) {
    const [values, setValues] = useState<DashboardFilterValues>(filters);
    const set = (patch: DashboardFilterValues) => setValues((v) => ({ ...v, ...patch }));
    const toNumber = (value: string) => (value === '' ? null : Number(value));

    const session = sessions?.find((s) => s.id === values.session_id);
    const showScope = scope && !scope.locked && faculties && faculties.length > 0;
    const faculty = faculties?.find((f) => f.id === values.faculty_id) ?? (scope?.faculty_locked ? faculties?.[0] : undefined);
    const departments = faculty ? faculty.departments : (faculties ?? []).flatMap((f) => f.departments);

    const apply = (e?: FormEvent, next: DashboardFilterValues = values) => {
        e?.preventDefault();
        router.get(`${path}${filterQuery(next) ? `?${filterQuery(next)}` : ''}`, {}, { preserveScroll: true });
    };

    const preset = (months: number | null, days?: number) => {
        const to = new Date();
        const from = new Date(to);
        if (days) from.setDate(from.getDate() - (days - 1));
        if (months) {
            from.setDate(1);
            from.setMonth(from.getMonth() - (months - 1));
        }
        const next = { ...values, from: isoDay(from), to: isoDay(to) };
        setValues(next);
        apply(undefined, next);
    };

    return (
        <div className="flex flex-col gap-3 rounded-xl border p-3 print:hidden">
            <form onSubmit={apply} className="flex flex-wrap items-end gap-3" aria-label="Dashboard filters">
                {sessions && (
                    <label className="space-y-1">
                        <span className={fieldLabel}>Session</span>
                        <select
                            className={selectClass}
                            value={values.session_id ?? ''}
                            onChange={(e) => set({ session_id: toNumber(e.target.value), semester_id: null })}
                        >
                            {sessions.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {showSemester && session && (
                    <label className="space-y-1">
                        <span className={fieldLabel}>Semester</span>
                        <select
                            className={selectClass}
                            value={values.semester_id ?? ''}
                            onChange={(e) => set({ semester_id: toNumber(e.target.value) })}
                        >
                            {session.semesters.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {showScope && !scope.faculty_locked && (
                    <label className="space-y-1">
                        <span className={fieldLabel}>Faculty / school</span>
                        <select
                            className={selectClass}
                            value={values.faculty_id ?? ''}
                            onChange={(e) => set({ faculty_id: toNumber(e.target.value), department_id: null })}
                        >
                            <option value="">Whole institution</option>
                            {faculties.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {showScope && (
                    <label className="space-y-1">
                        <span className={fieldLabel}>Department</span>
                        <select
                            className={selectClass}
                            value={values.department_id ?? ''}
                            onChange={(e) => set({ department_id: toNumber(e.target.value) })}
                        >
                            <option value="">{faculty ? `All of ${faculty.name}` : 'All departments'}</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {showRange && (
                    <>
                        <label className="space-y-1">
                            <span className={fieldLabel}>From</span>
                            <input
                                type="date"
                                className={selectClass}
                                value={values.from ?? ''}
                                max={values.to}
                                onChange={(e) => set({ from: e.target.value })}
                            />
                        </label>
                        <label className="space-y-1">
                            <span className={fieldLabel}>To</span>
                            <input
                                type="date"
                                className={selectClass}
                                value={values.to ?? ''}
                                min={values.from}
                                onChange={(e) => set({ to: e.target.value })}
                            />
                        </label>
                        <div className="flex gap-1" role="group" aria-label="Date range presets">
                            <Button type="button" size="sm" variant="ghost" onClick={() => preset(null, 30)}>
                                30 days
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => preset(6)}>
                                6 months
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => preset(12)}>
                                12 months
                            </Button>
                        </div>
                    </>
                )}

                <Button type="submit" size="sm">
                    Apply
                </Button>

                <div className="ml-auto flex gap-2">
                    {(['csv', 'pdf'] as const).map((format) => (
                        <Button key={format} asChild size="sm" variant="outline">
                            <a href={`${path}?${filterQuery(filters, { format })}`}>
                                <Download aria-hidden /> {format.toUpperCase()}
                            </a>
                        </Button>
                    ))}
                </div>
            </form>

            {scope && (
                <p className="text-muted-foreground text-xs">
                    Showing <span className="text-foreground font-medium">{scope.label}</span>
                    {scope.locked && !scope.institution ? ' — your department' : ''}
                    {scope.faculty_locked && !scope.locked ? ' — limited to your faculty' : ''}
                </p>
            )}
        </div>
    );
}
