import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Archive, Calendar, CalendarClock, ClipboardCheck, ClipboardList, FileText, History, KeyRound, LayoutGrid, ListTree, UserCog, Users } from 'lucide-react';
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
        title: 'Должности и ответственные',
        url: '/responsibles/accounts',
        icon: UserCog,
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

const responsibleUrls = new Set([
    '/dashboard',
    '/plan',
    '/approval',
    '/monitoring',
    '/calendar',
    '/evidence',
    '/credentials',
]);

const accountsUrl = '/responsibles/accounts';

export function AppSidebar() {
    const roles = usePage<SharedData>().props.auth.user?.roles ?? [];
    const isAdminLike = roles.includes('developer') || roles.includes('administrator');
    const isResponsibleOnly =
        roles.includes('responsible') && !isAdminLike && !roles.includes('observer');

    const items = mainNavItems.filter((item) => {
        if (item.url === accountsUrl) {
            return isAdminLike;
        }

        if (isResponsibleOnly) {
            return responsibleUrls.has(item.url);
        }

        return true;
    });

    return (
        <Sidebar collapsible="icon">
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
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
