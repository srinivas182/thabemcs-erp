import { createRootRoute, createRoute, createRouter, lazyRouteComponent } from '@tanstack/react-router';
import { HomePage } from './pages/home';
import { RootLayout } from './pages/root-layout';

const rootRoute = createRootRoute({ component: RootLayout });

const page = (path: string, loader: () => Promise<Record<string, unknown>>, name: string) =>
    createRoute({ getParentRoute: () => rootRoute, path, component: lazyRouteComponent(loader as never, name as never) });

const routeTree = rootRoute.addChildren([
    createRoute({ getParentRoute: () => rootRoute, path: '/', component: HomePage }),
    page('/diary/new', () => import('./pages/diary-new'), 'DiaryNewPage'),
    page('/attendance', () => import('./pages/attendance'), 'AttendancePage'),
    page('/photo', () => import('./pages/photo'), 'PhotoPage'),
    page('/delivery', () => import('./pages/delivery'), 'DeliveryPage'),
    page('/incident', () => import('./pages/incident'), 'IncidentPage'),
    page('/crew', () => import('./pages/crew'), 'CrewPage'),
    page('/receive', () => import('./pages/receive'), 'ReceivePage'),
    page('/snag', () => import('./pages/snag'), 'SnagPage'),
    page('/inspection', () => import('./pages/inspection'), 'InspectionPage'),
    page('/instruction', () => import('./pages/instruction'), 'InstructionPage'),
    page('/forms', () => import('./pages/forms'), 'FormsPage'),
]);

export const router = createRouter({ routeTree, basepath: '/site' });

declare module '@tanstack/react-router' {
    interface Register {
        router: typeof router;
    }
}
