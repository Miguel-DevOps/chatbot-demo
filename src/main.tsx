import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './App.tsx';
import './index.css';
import './i18n'; // Inicializar i18next

// Configurar el cliente de TanStack Query
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutos
      gcTime: 10 * 60 * 1000, // 10 minutos (antes cacheTime)
      retry: (failureCount, error: Error & { status?: number }) => {
        // No reintentar errores de cliente (4xx)
        if (error?.status && error.status >= 400 && error.status < 500) {
          return false;
        }
        // Reintentar hasta 2 veces para errores de red/servidor
        return failureCount < 2;
      },
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000),
    },
    mutations: {
      retry: (failureCount, error: Error & { message?: string }) => {
        // No reintentar errores de validación (4xx)
        if (error?.message?.includes('HTTP 4')) {
          return false;
        }
        return failureCount < 1; // Solo un reintento para mutaciones
      },
      retryDelay: 1000,
    },
  },
});

createRoot(document.getElementById("root")!).render(
  <QueryClientProvider client={queryClient}>
    <App />
  </QueryClientProvider>
);
