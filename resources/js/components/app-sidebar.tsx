import { Link, usePage } from '@inertiajs/react';
import { FileText, Mail } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as documentsIndex } from '@/routes/documents';
import { index as invitationsIndex } from '@/routes/invitations';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Documents',
        href: documentsIndex(),
        icon: FileText,
    },
];

export function AppSidebar() {
    // Shared from HandleInertiaRequests, so the badge tracks invitations from every page.
    const { pendingInvitationsCount } = usePage().props;

    const navItems: NavItem[] = [
        ...mainNavItems,
        {
            title: 'Invitations',
            href: invitationsIndex(),
            icon: Mail,
            badge: pendingInvitationsCount,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
