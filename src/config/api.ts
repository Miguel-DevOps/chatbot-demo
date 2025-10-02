// Configuración de endpoints y entorno para el chatbot
// SEGURIDAD: Usa EXCLUSIVAMENTE variables de entorno de Vite

interface ApiConfig {
  baseUrl: string;
  timeout: number;
  endpoints: Record<string, string>;
}

export const API_CONFIG: Record<string, ApiConfig> = {
  // Para desarrollo local
  development: {
    baseUrl: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080',
    timeout: parseInt(import.meta.env.VITE_API_TIMEOUT || '30000', 10),
    endpoints: {
      chat: '/chat',
      health: '/health'
    }
  },
  // Para producción
  production: {
    baseUrl: import.meta.env.VITE_API_BASE_URL || 'https://examplesite.com/api',
    timeout: parseInt(import.meta.env.VITE_API_TIMEOUT || '30000', 10),
    endpoints: {
      chat: '/chat',
      health: '/health'
    }
  }
};

// Obtener configuración actual basada en el entorno
export const getCurrentConfig = (): ApiConfig => {
  // Usar EXCLUSIVAMENTE las variables de entorno de Vite
  // No depender de window.location.hostname (frágil y acoplado)
  const isDevelopment = import.meta.env.DEV;
  
  return isDevelopment ? API_CONFIG.development : API_CONFIG.production;
};

// Obtener URL completa de un endpoint
export const getEndpointUrl = (endpoint: keyof typeof API_CONFIG.development.endpoints): string => {
  const config = getCurrentConfig();
  return `${config.baseUrl}${config.endpoints[endpoint]}`;
};

// Configuración para requests HTTP
export const getRequestConfig = () => {
  const config = getCurrentConfig();
  return {
    timeout: config.timeout,
    headers: {
      'Content-Type': 'application/json',
    },
  };
};
