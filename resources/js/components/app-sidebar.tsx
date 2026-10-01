import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, CheckSquare, ClipboardList, Folder, GraduationCap, LayoutGrid, Receipt, Users, Wallet } from 'lucide-react';
import AppLogo from './app-logo';

function navItemsFor(roles: string[]): NavItem[] {
    const has = (...names: string[]) => names.some((name) => roles.includes(name));
    const items: NavItem[] = [{ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid }];

    if (has('student')) {
        items.push(
            { title: 'My Dashboard', url: '/student/dashboard', icon: GraduationCap },
            { title: 'Course Registration', url: '/student/registration', icon: ClipboardList },
            { title: 'My Results', url: '/student/results', icon: BookOpen },
            { title: 'Fees & Payments', url: '/student/fees', icon: Wallet },
        );
    }

    if (has('lecturer', 'hod', 'dean')) {
        items.push({ title: 'My Courses', url: '/lecturer/courses', icon: BookOpen });
    }

    if (has('hod', 'dean', 'exam-officer', 'registrar', 'super-admin')) {
        items.push({ title: 'Result Approvals', url: '/staff/approvals', icon: CheckSquare });
    }

    if (has('registrar', 'admission-officer', 'super-admin')) {
        items.push({ title: 'Students', url: '/admin/students', icon: Users });
    }

    if (has('bursar', 'registrar', 'super-admin')) {
        items.push(
            { title: 'Fee Management', url: '/bursary/fees', icon: Wallet },
            { title: 'Payments', url: '/bursary/payments', icon: Receipt },
            { title: 'Revenue Reports', url: '/bursary/reports', icon: BarChart3 },
        );
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
    const mainNavItems = navItemsFor(auth.roles ?? []);

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
