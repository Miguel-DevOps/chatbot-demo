import React from 'react';
import { useTranslation } from '@/hooks/useTranslation';

// Componente para el efecto de typing dots
export const TypingDots: React.FC = () => (
  <div className="flex items-center space-x-1 py-1">
    <div className="w-2 h-2 bg-slate-400 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
    <div className="w-2 h-2 bg-slate-400 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
    <div className="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
  </div>
);

// Componente para indicar que el chatbot está escribiendo
export const TypingIndicator: React.FC = () => {
  const { t } = useTranslation();
  
  return (
    <div className="flex justify-start transition-all duration-200">
      <div className="bg-slate-100 text-slate-800 px-4 py-3 rounded-2xl mr-4 rounded-bl-md border border-slate-200 shadow-sm">
        <div className="flex items-center space-x-2">
          <TypingDots />
          <span className="text-sm text-slate-600">{t('messages.thinking')}</span>
        </div>
      </div>
    </div>
  );
};