import React, { useState, useRef, useEffect } from 'react';
import { X, Send, Calendar, MessageSquare, HelpCircle, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useChatAPI } from '@/hooks/api/useChatAPI';
import { useTranslation, useLanguage } from '@/hooks/useTranslation';

interface Message {
  id: string;
  content: string;
  isUser: boolean;
  timestamp: Date;
  isTyping?: boolean;
}

interface Message {
  id: string;
  content: string;
  isUser: boolean;
  timestamp: Date;
  isTyping?: boolean;
}

// Componente para el efecto de typing
const TypingDots: React.FC = () => (
  <div className="flex items-center space-x-1 py-1">
    <div className="w-2 h-2 bg-slate-400 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
    <div className="w-2 h-2 bg-slate-400 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
    <div className="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
  </div>
);

// Componente para el efecto de typing de texto
const TypewriterText: React.FC<{ text: string; speed?: number; onComplete?: () => void }> = ({ 
  text, 
  speed = 30, 
  onComplete 
}) => {
  const [displayText, setDisplayText] = useState('');
  const [currentIndex, setCurrentIndex] = useState(0);

  useEffect(() => {
    if (currentIndex < text.length) {
      const timeout = setTimeout(() => {
        setDisplayText(prev => prev + text[currentIndex]);
        setCurrentIndex(currentIndex + 1);
      }, speed);
      return () => clearTimeout(timeout);
    } else if (onComplete) {
      onComplete();
    }
  }, [currentIndex, text, speed, onComplete]);

  return <span className="whitespace-pre-wrap break-words">{displayText}</span>;
};

const ChatBot: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const { 
    sendMessage, 
    apiHealth,
    validateMessage,
    isApiHealthy
  } = useChatAPI();
  const [showInitialOptions, setShowInitialOptions] = useState(true);
  const [buttonsVisible, setButtonsVisible] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Hooks para internacionalización
  const { t } = useTranslation();
  const { currentLanguage, toggleLanguage } = useLanguage();

  // Función para volver al menú principal
  const handleGoHome = () => {
    setShowInitialOptions(true);
    setMessages([]);
    setInput('');
  };

  // Configuración para verificar estado de la API al abrir
  useEffect(() => {
    if (isOpen) {
      apiHealth.refetch();
    }
  }, [isOpen, apiHealth]);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  useEffect(() => {
    if (isOpen && showInitialOptions) {
      // Animar la aparición de los botones con delay
      setTimeout(() => setButtonsVisible(true), 300);
    } else {
      setButtonsVisible(false);
    }
  }, [isOpen, showInitialOptions]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const addMessage = (content: string, isUser: boolean = false) => {
    const newMessage: Message = {
      id: Date.now().toString(),
      content,
      isUser,
      timestamp: new Date(),
      isTyping: !isUser
    };
    setMessages(prev => [...prev, newMessage]);
  };

  const updateMessageTyping = (messageId: string) => {
    setMessages(prev => 
      prev.map(msg => 
        msg.id === messageId ? { ...msg, isTyping: false } : msg
      )
    );
  };

  const handleSendMessage = async () => {
    if (!input.trim()) return;

    const userMessage = input.trim();
    
    // Validar mensaje usando el hook
    const validation = validateMessage(userMessage);
    if (!validation.isValid) {
      addMessage(validation.error || t('messages.error'));
      return;
    }

    setInput('');
    addMessage(userMessage, true);

    // Usar la mutación del hook para enviar el mensaje
    sendMessage.mutate(userMessage, {
      onSuccess: (response) => {
        addMessage(response.response || t('messages.reformulate'));
      },
      onError: () => {
        addMessage(t('messages.error'));
      }
    });
  };

  const handleInitialOption = (option: string) => {
    setShowInitialOptions(false);
    
    switch (option) {
      case 'startChat': {
        const welcomeMessage = t('messages.welcomeChat');
        addMessage(welcomeMessage);
        break;
      }
      case 'whatsapp': {
        const whatsappMessage = t('messages.whatsappMessage');
        addMessage(whatsappMessage);
        window.open('https://wa.me/#', '_blank');
        break;
      }
      case 'faq': {
        const faqMessage = t('messages.faqMessage');
        addMessage(faqMessage);
        break;
      }
      case 'schedule': {
        const scheduleMessage = t('messages.scheduleMessage');
        addMessage(scheduleMessage);
        window.open('URL_CALENDAR_SYSTEM', '_blank');
        break;
      }
    }
  };

  const openChat = () => {
    setIsOpen(true);
  };

  if (!isOpen) {
    return (
      <div className="fixed bottom-6 right-6 z-50">
        <Button
          onClick={openChat}
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
  }

  return (
    <div 
      className="fixed bottom-6 right-6 w-[380px] h-[600px] bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col z-50 overflow-hidden transition-all duration-300 ease-out transform-gpu"
      style={{
        transformOrigin: 'bottom right',
      }}
    >
      {/* Header */}
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
                onClick={handleGoHome}
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
              onClick={() => setIsOpen(false)}
              variant="ghost"
              size="icon"
              className="h-8 w-8 text-white/90 hover:bg-white/10 hover:text-white transition-colors"
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      {/* Messages Area */}
      <ScrollArea className="flex-1 p-4">
        {showInitialOptions ? (
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
                onClick={() => handleInitialOption('startChat')}
                className="w-full bg-slate-900 hover:bg-slate-800 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
                variant="default"
              >
                <MessageSquare className="mr-3 h-4 w-4 shrink-0" />
                <span className="truncate">{t('buttons.startChat')}</span>
              </Button>
              
              <Button
                onClick={() => handleInitialOption('whatsapp')}
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
                variant="default"
              >
                <Phone className="mr-3 h-4 w-4 shrink-0" />
                <span className="truncate">{t('buttons.whatsapp')}</span>
              </Button>
              
              <Button
                onClick={() => handleInitialOption('faq')}
                className="w-full bg-slate-600 hover:bg-slate-700 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
                variant="default"
              >
                <HelpCircle className="mr-3 h-4 w-4 shrink-0" />
                <span className="truncate">{t('buttons.faq')}</span>
              </Button>
              
              <Button
                onClick={() => handleInitialOption('schedule')}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white transition-all duration-200 justify-start h-12 text-sm rounded-xl shadow-sm"
                variant="default"
              >
                <Calendar className="mr-3 h-4 w-4 shrink-0" />
                <span className="truncate">{t('buttons.schedule')}</span>
              </Button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            {messages.map((message) => (
              <div
                key={message.id}
                className={`flex ${message.isUser ? 'justify-end' : 'justify-start'} transition-all duration-200`}
              >
                <div
                  className={`max-w-[85%] px-4 py-3 rounded-2xl ${
                    message.isUser
                      ? 'bg-slate-900 text-white ml-4 rounded-br-md'
                      : 'bg-slate-100 text-slate-800 mr-4 rounded-bl-md border border-slate-200'
                  } shadow-sm`}
                >
                  <div className="text-sm leading-relaxed">
                    {message.isTyping ? (
                      <TypewriterText 
                        text={message.content} 
                        speed={30}
                        onComplete={() => updateMessageTyping(message.id)}
                      />
                    ) : (
                      <span className="whitespace-pre-wrap break-words">{message.content}</span>
                    )}
                  </div>
                  <p className="text-xs opacity-60 mt-2">
                    {message.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </p>
                </div>
              </div>
            ))}
            
            {/* Typing indicator */}
            {sendMessage.isLoading && (
              <div className="flex justify-start transition-all duration-200">
                <div className="bg-slate-100 text-slate-800 px-4 py-3 rounded-2xl mr-4 rounded-bl-md border border-slate-200 shadow-sm">
                  <div className="flex items-center space-x-2">
                    <TypingDots />
                    <span className="text-sm text-slate-600">{t('messages.thinking')}</span>
                  </div>
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>
        )}
      </ScrollArea>

      {/* Input Area */}
      {!showInitialOptions && (
        <div className="p-4 border-t border-slate-200 bg-slate-50/50">
          <div className="flex space-x-3">
            <Input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder={t('messages.placeholder')}
              onKeyPress={(e) => e.key === 'Enter' && handleSendMessage()}
              className="flex-1 rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900 text-sm bg-white shadow-sm placeholder:text-slate-400"
            />
            <Button
              onClick={handleSendMessage}
              disabled={sendMessage.isLoading || !input.trim()}
              size="icon"
              className="rounded-xl bg-slate-900 hover:bg-slate-900 disabled:bg-slate-700 shrink-0 shadow-sm transition-all duration-200 disabled:cursor-not-allowed"
            >
              <Send color="white" className="h-4 w-4" />
            </Button>
          </div>
          
          {/* Quick suggestions */}
          <div className="flex flex-wrap gap-2 mt-3">
            {[
              "¿Qué es React?",
              "Ayuda",
              "Precios"
            ].map((suggestion) => (
              <button
                key={suggestion}
                onClick={() => setInput(suggestion)}
                className="px-3 py-1 text-xs bg-white border border-slate-200 rounded-full text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-200"
              >
                {suggestion}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default ChatBot;
