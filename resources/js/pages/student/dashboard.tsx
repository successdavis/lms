import { FlashMessages } from '@/components/portal/flash-messages';
import { StatCard } from '@/components/portal/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Dashboard', href: '/student/dashboard' }];

interface Props {
    student: {
        matric_no: string | null;
        status: string;
        programme: { name: string; department: { name: string } };
        level: { name: string };
    };
    session: { name: string } | null;
    semester: { name: string } | null;
    cgpa: string | null;
    standing: string | null;
    registration: { status: string; total_units: number } | null;
    carryoverCount: number;
    unpaidInvoices: number;
}

export default function StudentDashboard({ student, session, semester, cgpa, standing, registration, carryoverCount, unpaidInvoices }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            {student.matric_no ?? 'No matric number yet'}
                            <Badge variant={student.status === 'active' ? 'default' : 'destructive'}>{student.status}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-muted-foreground text-sm">
                        {student.programme.name} · {student.programme.department.name} · {student.level.name}
                        {session && semester && (
                            <span>
                                {' '}
                                — {session.name}, {semester.name}
                            </span>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard label="CGPA" value={cgpa ?? '—'} hint={standing ? `Standing: ${standing}` : 'No results yet'} />
                    <StatCard
                        label="Registration"
                        value={registration ? registration.status : 'Not registered'}
                        hint={registration ? `${registration.total_units} units` : 'Register for this semester'}
                    />
                    <StatCard label="Carryovers" value={carryoverCount} hint={carryoverCount > 0 ? 'Register these first' : 'None outstanding'} />
                    <StatCard
                        label="Unpaid invoices"
                        value={unpaidInvoices}
                        hint={unpaidInvoices > 0 ? 'Payment unlocks registration' : 'All settled'}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Link href="/student/registration" className="hover:bg-accent rounded-xl border p-4">
                        <div className="font-medium">Course Registration</div>
                        <p className="text-muted-foreground text-sm">Register courses for the current semester.</p>
                    </Link>
                    <Link href="/student/results" className="hover:bg-accent rounded-xl border p-4">
                        <div className="font-medium">My Results</div>
                        <p className="text-muted-foreground text-sm">Approved results, GPA and CGPA per semester.</p>
                    </Link>
                    <Link href="/student/fees" className="hover:bg-accent rounded-xl border p-4">
                        <div className="font-medium">Fees & Payments</div>
                        <p className="text-muted-foreground text-sm">Invoices, payments and receipts.</p>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
