import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

// Importar traducciones
import es from './locales/es.json';
import en from './locales/en.json';

const resources = {
  es: {
    translation: es
  },
  en: {
    translation: en
  }
};

i18n
  // Detectar idioma del navegador
  .use(LanguageDetector)
  // Pasar la instancia i18n a react-i18next
  .use(initReactI18next)
  // Inicializar i18next
  .init({
    resources,
    // Idioma por defecto
    fallbackLng: 'es',
    // Idiomas soportados
    supportedLngs: ['es', 'en'],
    
    // Configuración del detector de idioma
    detection: {
      // Métodos de detección en orden de prioridad
      order: ['localStorage', 'navigator', 'htmlTag'],
      // Caches para persistir el idioma seleccionado
      caches: ['localStorage'],
      // Clave en localStorage
      lookupLocalStorage: 'i18nextLng',
    },

    // Configuración de debugging (solo en desarrollo)
    debug: import.meta.env.DEV,

    // Configuración de interpolación
    interpolation: {
      // React ya escapa los valores, no necesitamos escapar aquí
      escapeValue: false,
    },

    // Configuración de namespaces (para futuras expansiones)
    defaultNS: 'translation',
    ns: ['translation'],

    // Configuración de carga de recursos
    load: 'languageOnly', // 'es' en lugar de 'es-ES'
    
    // Configuración de fallback
    nonExplicitSupportedLngs: true,
  });

export default i18n;

// Tipos TypeScript para autocompletado
export type TranslationKeys = 
  | 'chatbot.welcome'
  | 'chatbot.title'
  | 'chatbot.status.online'
  | 'chatbot.status.offline'
  | 'buttons.startChat'
  | 'buttons.whatsapp'
  | 'buttons.faq'
  | 'buttons.schedule'
  | 'buttons.send'
  | 'buttons.home'
  | 'messages.placeholder'
  | 'messages.thinking'
  | 'messages.error'
  | 'messages.reformulate'
  | 'messages.welcomeChat'
  | 'messages.whatsappMessage'
  | 'messages.faqMessage'
  | 'messages.scheduleMessage'
  | 'validation.emptyMessage'
  | 'validation.tooShort'
  | 'validation.tooLong';