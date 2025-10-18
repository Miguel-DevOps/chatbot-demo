import React from 'react';
import { Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

interface ChatInputProps {
  input: string;
  onInputChange: (value: string) => void;
  onSendMessage: () => void;
  isLoading: boolean;
}

export const ChatInput: React.FC<ChatInputProps> = ({
  input,
  onInputChange,
  onSendMessage,
  isLoading
}) => {
  const { t } = useTranslation();

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      onSendMessage();
    }
  };

  const quickSuggestions = [
    "¿Qué es React?",
    "Ayuda",
    "Precios"
  ];

  return (
    <div className="p-4 border-t border-slate-200 bg-slate-50/50">
      <div className="flex space-x-3">
        <Input
          value={input}
          onChange={(e) => onInputChange(e.target.value)}
          placeholder={t('messages.placeholder')}
          onKeyPress={handleKeyPress}
          className="flex-1 rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900 text-sm bg-white shadow-sm placeholder:text-slate-400"
        />
        <Button
          onClick={onSendMessage}
          disabled={isLoading || !input.trim()}
          size="icon"
          className="rounded-xl bg-slate-900 hover:bg-slate-900 disabled:bg-slate-700 shrink-0 shadow-sm transition-all duration-200 disabled:cursor-not-allowed"
        >
          <Send color="white" className="h-4 w-4" />
        </Button>
      </div>
      
      {/* Quick suggestions */}
      <div className="flex flex-wrap gap-2 mt-3">
        {quickSuggestions.map((suggestion) => (
          <button
            key={suggestion}
            onClick={() => onInputChange(suggestion)}
            className="px-3 py-1 text-xs bg-white border border-slate-200 rounded-full text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-200"
          >
            {suggestion}
          </button>
        ))}
      </div>
    </div>
  );
};