import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from '@tanstack/react-router';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './app.css';
import { startBackgroundSync } from './lib/sync';
import { router } from './router';

const queryClient = new QueryClient({
    defaultOptions: { queries: { staleTime: 60_000, retry: 2 } },
});

startBackgroundSync();

createRoot(document.getElementById('root')!).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>
    </StrictMode>,
);
