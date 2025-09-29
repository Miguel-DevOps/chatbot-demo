import React from 'react';
import { useLanguage, useTranslation } from '@/hooks/useTranslation';
import { Button } from '@/components/ui/button';

/**
 * Componente de prueba para mostrar todas las traducciones
 * Útil durante desarrollo para verificar que las traducciones funcionan
 */
export const TranslationTester: React.FC = () => {
  const { t } = useTranslation();
  const { currentLanguage, toggleLanguage } = useLanguage();

  const translationKeys = [
    'chatbot.home',
    'chatbot.header',
    'chatbot.description',
    'chatbot.welcome',
    'chatbot.title',
    'chatbot.status.online',
    'chatbot.status.offline',
    'buttons.startChat',
    'buttons.whatsapp',
    'buttons.faq',
    'buttons.schedule',
    'buttons.send',
    'buttons.home',
    'messages.placeholder',
    'messages.thinking',
    'messages.error',
    'messages.reformulate',
    'messages.welcomeChat',
    'messages.whatsappMessage',
    'messages.faqMessage',
    'messages.scheduleMessage',
    'validation.emptyMessage',
    'validation.tooShort',
    'validation.tooLong'
  ] as const;

  return (
    <div className="p-4 max-w-2xl mx-auto">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-xl font-bold">Translation Tester</h2>
        <div className="flex items-center gap-2">
          <span className="text-sm">Current: {currentLanguage.toUpperCase()}</span>
          <Button onClick={toggleLanguage} size="sm">
            Switch to {currentLanguage === 'es' ? 'EN' : 'ES'}
          </Button>
        </div>
      </div>
      
      <div className="grid gap-2">
        {translationKeys.map((key) => (
          <div key={key} className="flex items-start gap-2 p-2 border rounded">
            <code className="text-xs text-gray-500 min-w-[200px]">{key}</code>
            <span className="flex-1">{t(key)}</span>
          </div>
        ))}
      </div>
    </div>
  );
};