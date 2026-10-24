import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BedDouble,
    Bell,
    BookOpen,
    Briefcase,
    CalendarDays,
    CheckSquare,
    ClipboardCheck,
    ClipboardList,
    FileText,
    Folder,
    GraduationCap,
    IdCard,
    LayoutGrid,
    ListChecks,
    Receipt,
    ScrollText,
    ShieldCheck,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

function navItemsFor(roles: string[], isStaff: boolean, unread: number): NavItem[] {
    const has = (...names: string[]) => names.some((name) => roles.includes(name));
    const items: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: unread > 0 ? `Notifications (${unread})` : 'Notifications', url: '/notifications', icon: Bell },
    ];

    if (has('applicant')) {
        items.push({ title: 'My Application', url: '/apply', icon: FileText });
    }

    if (has('student')) {
        items.push(
            { title: 'My Dashboard', url: '/student/dashboard', icon: GraduationCap },
            { title: 'Course Registration', url: '/student/registration', icon: ClipboardList },
            { title: 'My Results', url: '/student/results', icon: BookOpen },
            { title: 'Fees & Payments', url: '/student/fees', icon: Wallet },
            { title: 'Hostel', url: '/student/hostel', icon: BedDouble },
            { title: 'Transcripts', url: '/student/transcripts', icon: ScrollText },
            { title: 'SIWES', url: '/student/siwes', icon: Briefcase },
            { title: 'Final Clearance', url: '/student/clearance', icon: ClipboardCheck },
            { title: 'ID Card', url: '/student/id-card', icon: IdCard },
        );
    }

    if (has('lecturer', 'hod', 'dean')) {
        items.push({ title: 'My Courses', url: '/lecturer/courses', icon: BookOpen });
    }

    if (has('hod', 'dean', 'exam-officer', 'registrar', 'super-admin')) {
        items.push({ title: 'Result Approvals', url: '/staff/approvals', icon: CheckSquare });
    }

    if (has('registrar', 'admission-officer', 'super-admin')) {
        items.push(
            { title: 'Students', url: '/admin/students', icon: Users },
            { title: 'Applicants', url: '/admissions/applicants', icon: FileText },
            { title: 'Admission Lists', url: '/admissions/lists', icon: ListChecks },
        );
    }

    if (has('bursar', 'registrar', 'super-admin')) {
        items.push(
            { title: 'Fee Management', url: '/bursary/fees', icon: Wallet },
            { title: 'Payments', url: '/bursary/payments', icon: Receipt },
            { title: 'Revenue Reports', url: '/bursary/reports', icon: BarChart3 },
        );
    }

    if (has('registrar', 'exam-officer', 'super-admin')) {
        items.push({ title: 'Transcript Requests', url: '/records/transcripts', icon: ScrollText });
    }

    if (has('registrar', 'super-admin')) {
        items.push({ title: 'Graduation & NYSC', url: '/records/graduation', icon: GraduationCap });
    }

    if (has('hod', 'dean', 'librarian', 'bursar', 'student-affairs', 'registrar', 'super-admin')) {
        items.push({ title: 'Clearance Queue', url: '/clearance', icon: ClipboardCheck });
    }

    if (has('student-affairs', 'registrar', 'super-admin')) {
        items.push({ title: 'Hostels', url: '/hostels', icon: BedDouble });
    }

    if (has('siwes-coordinator', 'registrar', 'super-admin')) {
        items.push({ title: 'SIWES Placements', url: '/siwes/placements', icon: Briefcase });
    }

    if (has('registrar', 'super-admin')) {
        items.push({ title: 'Audit Log', url: '/admin/audit', icon: ShieldCheck });
    }

    if (has('hr-officer', 'registrar', 'super-admin')) {
        items.push({ title: 'Staff Register', url: '/hr/staff', icon: IdCard });
    }

    if (has('hod', 'hr-officer', 'registrar', 'super-admin')) {
        items.push({ title: 'Promotions', url: '/hr/promotions', icon: TrendingUp });
    }

    if (isStaff) {
        items.push({ title: 'My Leave', url: '/staff/leave', icon: CalendarDays });
    }

    if (has('hod', 'registrar', 'super-admin')) {
        items.push({ title: 'Leave Approvals', url: '/staff/leave/approvals', icon: CalendarDays });
    }

    return items;
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const mainNavItems = navItemsFor(auth.roles ?? [], auth.is_staff ?? false, auth.unread_notifications ?? 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
