import { getEndpointUrl, getRequestConfig } from '@/config/api';
import i18n from '@/i18n';
import type { 
  ChatRequest, 
  ChatResponse, 
  HealthResponse, 
  ApiErrorResponse 
} from '@/types/api';

// Re-exportar tipos para compatibilidad con hooks existentes
export type { ChatRequest, ChatResponse, HealthResponse, ApiErrorResponse as ApiError };

/**
 * Servicio de API abstrayendo toda la lógica de comunicación con el backend
 * Tipos generados automáticamente desde OpenAPI
 */
export class ChatApiService {
  private static instance: ChatApiService;
  private requestConfig = getRequestConfig();

  static getInstance(): ChatApiService {
    if (!ChatApiService.instance) {
      ChatApiService.instance = new ChatApiService();
    }
    return ChatApiService.instance;
  }

  /**
   * Enviar mensaje al chatbot
   */
  async sendMessage(message: string): Promise<ChatResponse> {
    try {
      const requestBody: ChatRequest = { message: message.trim() };
      
      const response = await fetch(getEndpointUrl('chat'), {
        method: 'POST',
        headers: this.requestConfig.headers,
        body: JSON.stringify(requestBody),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Error desconocido del servidor');
      }

      return data as ChatResponse;
    } catch (error) {
      console.error('Error en ChatApiService.sendMessage:', error);
      throw new Error(
        error instanceof Error 
          ? error.message 
          : 'Error de comunicación con el servidor'
      );
    }
  }

  /**
   * Verificar el estado de la API
   */
  async getHealth(): Promise<HealthResponse> {
    try {
      const response = await fetch(getEndpointUrl('health'), {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      return data as HealthResponse;
    } catch (error) {
      console.error('Error en ChatApiService.getHealth:', error);
      throw new Error(
        error instanceof Error 
          ? error.message 
          : 'Error verificando el estado del servidor'
      );
    }
  }

  /**
   * Validar mensaje antes de enviar
   */
  validateMessage(message: string): { isValid: boolean; error?: string } {
    const trimmed = message.trim();
    
    if (!trimmed) {
      return { 
        isValid: false, 
        error: i18n.t('validation.emptyMessage') 
      };
    }
    
    if (trimmed.length > 1000) {
      return { 
        isValid: false, 
        error: i18n.t('validation.tooLong')
      };
    }
    
    return { isValid: true };
  }
}

// Exportar instancia singleton
export const chatApiService = ChatApiService.getInstance();