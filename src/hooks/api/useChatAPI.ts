import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { chatApiService } from '@/services/chatApi';
import type { ChatResponse } from './types';

/**
 * Hook para enviar mensajes al chatbot
 * Usa TanStack Query para manejo de estado, caché y reintentos
 */
export function useChatMessage() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (message: string): Promise<ChatResponse> => {
      // Validar mensaje antes de enviar
      const validation = chatApiService.validateMessage(message);
      if (!validation.isValid) {
        throw new Error(validation.error);
      }

      return chatApiService.sendMessage(message);
    },
    onSuccess: (data) => {
      // Invalidar queries relacionadas si es necesario
      queryClient.setQueryData(['chat', 'last-response'], data);
    },
    onError: (error) => {
      console.error('Error en useChatMessage:', error);
    },
    // Configuración de reintentos
    retry: (failureCount, error) => {
      // No reintentar errores de validación (4xx)
      if (error.message.includes('HTTP 4')) {
        return false;
      }
      // Reintentar hasta 2 veces para errores de red/servidor
      return failureCount < 2;
    },
    retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000), // Exponential backoff
  });
}

/**
 * Hook para verificar el estado de la API
 */
export function useApiHealth() {
  return useQuery({
    queryKey: ['api', 'health'],
    queryFn: () => chatApiService.getHealth(),
    staleTime: 5 * 60 * 1000, // 5 minutos
    gcTime: 10 * 60 * 1000, // 10 minutos (antes cacheTime)
    retry: 2,
    retryDelay: 1000,
    refetchOnWindowFocus: false,
    // Solo refetch si la app ha estado inactiva por más de 5 minutos
    refetchOnMount: (query) => {
      const lastFetched = query.state.dataUpdatedAt;
      return Date.now() - lastFetched > 5 * 60 * 1000;
    },
  });
}

/**
 * Hook para obtener el historial de mensajes (si se implementa en el futuro)
 */
export function useChatHistory() {
  return useQuery({
    queryKey: ['chat', 'history'],
    queryFn: async () => {
      // Por ahora retorna array vacío, preparado para implementación futura
      return [];
    },
    enabled: false, // Deshabilitado hasta implementar endpoint
    staleTime: 2 * 60 * 1000, // 2 minutos
  });
}

/**
 * Hook compuesto que maneja toda la lógica de chat
 */
export function useChatAPI() {
  const sendMessage = useChatMessage();
  const apiHealth = useApiHealth();
  const chatHistory = useChatHistory();

  return {
    // Mutación para enviar mensajes
    sendMessage: {
      mutate: sendMessage.mutate,
      mutateAsync: sendMessage.mutateAsync,
      isLoading: sendMessage.isPending,
      isError: sendMessage.isError,
      error: sendMessage.error,
      data: sendMessage.data,
      reset: sendMessage.reset,
    },
    
    // Query para estado de la API
    apiHealth: {
      data: apiHealth.data,
      isLoading: apiHealth.isLoading,
      isError: apiHealth.isError,
      error: apiHealth.error,
      refetch: apiHealth.refetch,
    },
    
    // Query para historial (preparado para futuro)
    chatHistory: {
      data: chatHistory.data,
      isLoading: chatHistory.isLoading,
      isError: chatHistory.isError,
      error: chatHistory.error,
      refetch: chatHistory.refetch,
    },

    // Helper functions
    validateMessage: chatApiService.validateMessage,
    isApiHealthy: apiHealth.data?.status === 'ok',
  };
}