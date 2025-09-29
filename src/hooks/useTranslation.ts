import { useTranslation as useI18nTranslation } from 'react-i18next';
import type { TranslationKeys } from '@/i18n';
import type { TFunction } from 'i18next';

/**
 * Hook personalizado para usar traducciones con tipos seguros
 * Wrapper alrededor de useTranslation de react-i18next
 */
export function useTranslation() {
  const { t, i18n } = useI18nTranslation();

  // Función tipada para traducciones
  const translate = (key: TranslationKeys, options?: Record<string, unknown>): string => {
    return t(key, options) as string;
  };

  // Función para cambiar idioma
  const changeLanguage = (lng: 'es' | 'en'): Promise<TFunction> => {
    return i18n.changeLanguage(lng);
  };

  // Función para obtener idioma actual
  const getCurrentLanguage = (): 'es' | 'en' => {
    return i18n.language as 'es' | 'en';
  };

  // Función para verificar si está cargando
  const isLoading = (): boolean => {
    return !i18n.isInitialized;
  };

  return {
    t: translate,
    changeLanguage,
    currentLanguage: getCurrentLanguage(),
    isLoading: isLoading(),
    i18n,
    // Exponer función original para casos especiales
    tOriginal: t,
  };
}

/**
 * Hook específico para obtener el idioma actual y cambiarlo
 */
export function useLanguage() {
  const { changeLanguage, currentLanguage } = useTranslation();

  const toggleLanguage = (): Promise<TFunction> => {
    const newLang = currentLanguage === 'es' ? 'en' : 'es';
    return changeLanguage(newLang);
  };

  return {
    currentLanguage,
    changeLanguage,
    toggleLanguage,
    isSpanish: currentLanguage === 'es',
    isEnglish: currentLanguage === 'en',
  };
}