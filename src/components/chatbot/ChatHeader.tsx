import React from 'react';
import { X, MessageSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation, useLanguage } from '@/hooks/useTranslation';

interface ChatHeaderProps {
  isApiHealthy: boolean;
  showInitialOptions: boolean;
  onClose: () => void;
  onGoHome: () => void;
}

export const ChatHeader: React.FC<ChatHeaderProps> = ({
  isApiHealthy,
  showInitialOptions,
  onClose,
  onGoHome
}) => {
  const { t } = useTranslation();
  const { currentLanguage, toggleLanguage } = useLanguage();

  return (
    <div className="bg-linear-to-r from-slate-900 to-slate-800 text-white p-4 rounded-t-2xl">
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center space-x-3 min-w-0 flex-1">
          <div className="relative">
            <div className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center backdrop-blur-sm">
              <MessageSquare className="w-5 h-5 text-white" />
            </div>
            {/* Status indicator */}
            <div 
              className={`absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-white ${
                isApiHealthy ? 'bg-emerald-500' : 'bg-red-500'
              }`}
            />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="font-semibold text-sm truncate">{t('chatbot.welcome')}</h3>
            <div className="flex items-center gap-1 mt-0.5">
              <span className="text-xs text-white/80">
                {isApiHealthy ? t('chatbot.status.online') : t('chatbot.status.offline')}
              </span>
            </div>
          </div>
        </div>
        
        <div className="flex items-center gap-2 shrink-0">
          {/* Home button - only when in conversation */}
          {!showInitialOptions && (
            <Button
              onClick={onGoHome}
              size="sm"
              variant="ghost"
              className="h-8 px-3 text-white/90 hover:bg-white/10 hover:text-white transition-colors text-xs font-medium"
            >
              {t('chatbot.home')}
            </Button>
          )}
          
          {/* Language toggle */}
          <Button
            onClick={toggleLanguage}
            variant="ghost"
            size="sm"
            className="h-8 px-2 text-white/90 hover:bg-white/10 hover:text-white transition-colors text-xs font-semibold"
            title={currentLanguage === 'es' ? 'Switch to English' : 'Cambiar a Español'}
          >
            {currentLanguage.toUpperCase()}
          </Button>
          
          {/* Close button */}
          <Button
            onClick={onClose}
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-white/90 hover:bg-white/10 hover:text-white transition-colors"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  );
};