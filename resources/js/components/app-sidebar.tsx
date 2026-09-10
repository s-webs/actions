import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Archive, BookOpen, Calendar, CalendarClock, ClipboardCheck, ClipboardList, FileText, Folder, History, KeyRound, LayoutGrid, ListTree, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Дашборд',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'План',
        url: '/plan',
        icon: ClipboardList,
    },
    {
        title: 'Направления',
        url: '/directions',
        icon: ListTree,
    },
    {
        title: 'Проверка этапов',
        url: '/approval',
        icon: ClipboardCheck,
    },
    {
        title: 'Помесячный мониторинг',
        url: '/monitoring',
        icon: CalendarClock,
    },
    {
        title: 'Календарь контроля',
        url: '/calendar',
        icon: Calendar,
    },
    {
        title: 'Свод по ответственным',
        url: '/responsibles',
        icon: Users,
    },
    {
        title: 'Доказательная база',
        url: '/evidence',
        icon: FileText,
    },
    {
        title: 'Учётные данные мероприятий',
        url: '/credentials',
        icon: KeyRound,
    },
    {
        title: 'Закрытие периода',
        url: '/periods',
        icon: Archive,
    },
    {
        title: 'История изменений',
        url: '/audit',
        icon: History,
    },
];

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
