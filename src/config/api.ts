// Configuración de endpoints y entorno para el chatbot
export const API_CONFIG = {
  // Para desarrollo local
  development: {
    baseUrl: 'http://localhost/chatbot-api',
    timeout: 30000,
    endpoints: {}
  },
  // Para producción - URL
  production: {
    baseUrl: 'https://chatbot.com/api',
    timeout: 30000,
    endpoints: {}
  }
};

// Obtener configuración actual
export const getCurrentConfig = () => {
  const isDevelopment = process.env.NODE_ENV === 'development' || 
                       window.location.hostname === 'localhost' ||
                       window.location.hostname === '127.0.0.1';
  
  return isDevelopment ? API_CONFIG.development : API_CONFIG.production;
};
