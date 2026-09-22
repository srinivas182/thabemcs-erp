import { createRootRoute, createRoute, createRouter, lazyRouteComponent } from '@tanstack/react-router';
import { HomePage } from './pages/home';
import { RootLayout } from './pages/root-layout';

const rootRoute = createRootRoute({ component: RootLayout });

const homeRoute = createRoute({ getParentRoute: () => rootRoute, path: '/', component: HomePage });
const diaryNewRoute = createRoute({ getParentRoute: () => rootRoute, path: '/diary/new', component: lazyRouteComponent(() => import('./pages/diary-new'), 'DiaryNewPage') });

export const router = createRouter({ routeTree: rootRoute.addChildren([homeRoute, diaryNewRoute]) });

declare module '@tanstack/react-router' {
    interface Register {
        router: typeof router;
    }
}
