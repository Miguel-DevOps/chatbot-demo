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
    
    // Language detector configuration
    detection: {
      // Detection methods in priority order
      order: ['localStorage', 'navigator', 'htmlTag'],
      // Caches to persist selected language
      caches: ['localStorage'],
      // LocalStorage key
      lookupLocalStorage: 'i18nextLng',
    },

    // Debug configuration (development only)
    debug: import.meta.env.DEV,

    // Interpolation configuration
    interpolation: {
      // React already escapes values, no need to escape here
      escapeValue: false,
    },

    // Namespace configuration (for future expansions)
    defaultNS: 'translation',
    ns: ['translation'],

    // Resource loading configuration
    load: 'languageOnly', // 'es' instead of 'es-ES'
    
    // Fallback configuration
    nonExplicitSupportedLngs: true,
  });

export default i18n;

// TypeScript types for autocompletion
export type TranslationKeys = 
  | 'chatbot.home'
  | 'chatbot.header'
  | 'chatbot.description'
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