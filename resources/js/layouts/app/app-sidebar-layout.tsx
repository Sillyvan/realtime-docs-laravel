import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useInvitationNotifications } from '@/hooks/use-invitation-notifications';
import { useSocketIdHeader } from '@/hooks/use-socket-id-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    // Every signed-in page: the socket the server has to leave out of its
    // broadcasts, and the invitations that may arrive on any of them.
    useSocketIdHeader();
    useInvitationNotifications();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
