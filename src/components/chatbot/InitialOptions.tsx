import React from 'react';
import { Calendar, MessageSquare, HelpCircle, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useTranslation } from '@/hooks/useTranslation';

interface InitialOptionsProps {
  buttonsVisible: boolean;
  onOptionSelect: (option: string) => void;
}

export const InitialOptions: React.FC<InitialOptionsProps> = ({ 
  buttonsVisible, 
  onOptionSelect 
}) => {
  const { t } = useTranslation();

  return (
    <ScrollArea className="flex-1 p-4">
      <div className="space-y-4">
        {/* Welcome Header */}
        <div className="text-center mb-8 animate-fade-in">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
            <MessageSquare className="w-8 h-8 text-slate-700" />
          </div>
          <h3 className="text-lg font-semibold text-slate-900 mb-2">
            {t('chatbot.header')}
          </h3>
          <p className="text-sm text-slate-600 max-w-xs mx-auto">
            {t('chatbot.description')}
          </p>
        </div>
        
        {/* Action Buttons */}
        <div className={`space-y-3 transition-all duration-500 ${buttonsVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}>
          <Button
            onClick={() => onOptionSelect('startChat')}
            className="w-full bg-slate-900 hover:bg-slate-800 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
            variant="default"
          >
            <MessageSquare className="mr-3 h-4 w-4 shrink-0" />
            <span className="truncate">{t('buttons.startChat')}</span>
          </Button>
          
          <Button
            onClick={() => onOptionSelect('whatsapp')}
            className="w-full bg-emerald-600 hover:bg-emerald-700 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
            variant="default"
          >
            <Phone className="mr-3 h-4 w-4 shrink-0" />
            <span className="truncate">{t('buttons.whatsapp')}</span>
          </Button>
          
          <Button
            onClick={() => onOptionSelect('faq')}
            className="w-full bg-slate-600 hover:bg-slate-700 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
            variant="default"
          >
            <HelpCircle className="mr-3 h-4 w-4 shrink-0" />
            <span className="truncate">{t('buttons.faq')}</span>
          </Button>
          
          <Button
            onClick={() => onOptionSelect('schedule')}
            className="w-full bg-blue-600 hover:bg-blue-700 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
            variant="default"
          >
            <Calendar className="mr-3 h-4 w-4 shrink-0" />
            <span className="truncate">{t('buttons.schedule')}</span>
          </Button>
        </div>
      </div>
    </ScrollArea>
  );
};