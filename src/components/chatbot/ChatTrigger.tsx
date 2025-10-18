import React from 'react';
import { MessageSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ChatTriggerProps {
  onClick: () => void;
}

export const ChatTrigger: React.FC<ChatTriggerProps> = ({ onClick }) => {
  return (
    <div className="fixed bottom-6 right-6 z-50">
      <Button
        onClick={onClick}
        className="h-16 w-16 rounded-full bg-slate-900 hover:bg-slate-800 shadow-2xl transition-all duration-300 hover:scale-110 border-0 relative overflow-hidden group"
        size="icon"
      >
        {/* Animated background */}
        <div className="absolute inset-0 bg-linear-to-br from-slate-800 to-slate-900 transition-all duration-300 group-hover:from-slate-700 group-hover:to-slate-800" />
        
        {/* Icon */}
        <div className="relative z-10">
          <MessageSquare className="w-7 h-7 text-white transition-transform duration-300 group-hover:scale-110" />
        </div>
        
        {/* Pulse animation */}
        <div className="absolute inset-0 rounded-full bg-slate-900 animate-ping opacity-20" />
      </Button>
      
      {/* Tooltip */}
      <div className="absolute bottom-full right-0 mb-2 px-3 py-2 bg-slate-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
        Abrir ChatBot
        <div className="absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-slate-900" />
      </div>
    </div>
  );
};