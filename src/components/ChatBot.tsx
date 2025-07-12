import React, { useState, useRef, useEffect } from 'react';
import { MessageCircle, X, Send, Calendar, MessageSquare, HelpCircle, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useToast } from '@/hooks/use-toast';
import { getCurrentConfig } from '@/config/api';

interface Message {
  id: string;
  content: string;
  isUser: boolean;
  timestamp: Date;
  isTyping?: boolean;
}

interface Language {
  code: 'es' | 'en';
  welcome: string;
  startChat: string;
  whatsapp: string;
  faq: string;
  schedule: string;
  placeholder: string;
  thinking: string;
  error: string;
  reformulate: string;
}

const languages: Record<string, Language> = {
  es: {
    code: 'es',
    welcome: 'Chatbot Demo',
    startChat: 'Iniciar Chat',
    whatsapp: 'Contacto por WhatsApp',
    faq: 'Preguntas Frecuentes',
    schedule: 'Agendar cita',
    placeholder: 'Escribe tu consulta aquí...',
    thinking: 'Pensando...',
    error: 'Hubo un error. Intenta nuevamente.',
    reformulate: '¿Puedes reformular tu pregunta o elegir una opción de ejemplo?'
  },
  en: {
    code: 'en',
    welcome: 'Demo Chatbot',
    startChat: 'Start Chat',
    whatsapp: 'WhatsApp contact',
    faq: 'FAQs',
    schedule: 'Schedule an appointment',
    placeholder: 'Type your query here...',
    thinking: 'Thinking...',
    error: 'There was an error. Please try again.',
    reformulate: 'Can you reformulate your question or choose a sample option?'
  },
};

// Componente para el efecto de typing
const TypingDots: React.FC = () => (
  <div className="flex items-center space-x-1 py-2">
    <div className="w-2 h-2 bg-gray-600 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
    <div className="w-2 h-2 bg-gray-600 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
    <div className="w-2 h-2 bg-gray-600 rounded-full animate-bounce"></div>
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
  const [isLoading, setIsLoading] = useState(false);
  const [showInitialOptions, setShowInitialOptions] = useState(true);
  const [language, setLanguage] = useState<Language>(languages.es);
  const [buttonsVisible, setButtonsVisible] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const { toast } = useToast();

  // Función para volver al menú principal
  const handleGoHome = () => {
    setShowInitialOptions(true);
    setMessages([]);
    setInput('');
  };

  // Configuración de la API
  const apiConfig = getCurrentConfig();

  useEffect(() => {
    const browserLang = navigator.language.startsWith('en') ? 'en' : 'es';
    setLanguage(languages[browserLang]);
  }, []);

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

  const callChatAPI = async (userMessage: string): Promise<string> => {
    try {
      const response = await fetch(`${apiConfig.baseUrl}/chat.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: userMessage
        }),
      });

      if (!response.ok) {
        throw new Error(`Error en la API: ${response.status}`);
      }

      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Error desconocido');
      }

      return data.response || language.reformulate;
    } catch (error) {
      console.error('Error calling Chat API:', error);
      return language.error;
    }
  };

  const handleSendMessage = async () => {
    if (!input.trim()) return;

    const userMessage = input.trim();
    setInput('');
    addMessage(userMessage, true);
    setIsLoading(true);

    try {
      const response = await callChatAPI(userMessage);
      addMessage(response);
    } catch (error) {
      addMessage(language.error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleInitialOption = (option: string) => {
    setShowInitialOptions(false);
    
    switch (option) {
      case 'startChat': {
        const welcomeMessage = language.code === 'es' 
          ? 'Excelente. ¿En qué podemos asesorarte hoy? Estoy aquí para asistirte con información detallada sobre nuestros servicios y filosofía.'
          : 'Excellent. How can we assist you today? I am here to assist you with detailed information about our services and philosophy.';
        addMessage(welcomeMessage);
        break;
      }
      case 'whatsapp': {
        const whatsappMessage = language.code === 'es'
          ? 'Conéctate directamente con nuestro equipo de expertos para una atención personalizada y ágil.'
          : 'Connect directly with our team of experts for personalized and agile assistance.';
        addMessage(whatsappMessage);
        window.open('https://wa.me/573102577839', '_blank');
        break;
      }
      case 'faq': {
        const faqMessage = language.code === 'es'
          ? 'Accede a respuestas rápidas sobre nuestros servicios, procesos y alianzas estratégicas.'
          : 'Access quick answers about our services, processes, and strategic partnerships.';
        addMessage(faqMessage);
        break;
      }
      case 'schedule': {
        const scheduleMessage = language.code === 'es'
          ? 'Reserva un espacio para una consulta estratégica y personalizada con nuestros especialistas.'
          : 'Book a session for a strategic and personalized consultation with our specialists.';
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
      <Button
        onClick={openChat}
        className="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 h-20 w-20 sm:h-18 sm:w-18 rounded-full bg-transparent hover:bg-gray-100/20 shadow-2xl transition-all duration-300 hover:scale-105 z-50 p-2 border-0 overflow-hidden"
        size="icon"
      >
        <img 
          src="/chatbot.svg"
          alt="Demo Chatbot"
          className="w-full h-full object-contain rounded-full drop-shadow-lg"
        />
      </Button>
    );
  }

  return (
    <div 
      className={`fixed bottom-4 right-4 sm:bottom-6 sm:right-6 w-[calc(100vw-2rem)] max-w-sm sm:max-w-96 h-[calc(100vh-8rem)] max-h-[600px] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col z-50 overflow-hidden transition-all duration-300 ease-out transform-gpu opacity-100 scale-100`}
      style={{
        transformOrigin: 'bottom right',
      }}
    >
      {/* Header */}
      <div className="bg-gradient-to-r from-black to-gray-800 text-white p-3 sm:p-4 rounded-t-2xl transition-opacity duration-300">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center space-x-3">
            <img 
              src="/chatbot.svg"
              alt="Demo Chatbot"
              className="w-10 h-10 rounded-full object-cover flex-shrink-0"
            />
            <div className="min-w-0 flex-1">
              <h3 className="font-semibold text-sm truncate">{language.welcome}</h3>
            </div>
          </div>
          {/* Botón de Inicio solo cuando está en la conversación */}
          {!showInitialOptions && (
            <Button
              onClick={handleGoHome}
              size="sm"
              variant="outline"
              className="flex items-center gap-2 text-xs px-3 py-1 border-gray-300 hover:bg-gray-100 bg-white text-black"
            >
              <img 
                src="/home.svg"
                alt="Inicio"
                className="w-4 h-4"
              />
              <span>Inicio</span>
            </Button>
          )}
          <Button
            onClick={() => setIsOpen(false)}
            variant="ghost"
            size="icon"
            className="text-white hover:bg-white/20 h-8 w-8 flex-shrink-0"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Messages Area */}
      <ScrollArea className="flex-1 p-3 sm:p-4">
        {showInitialOptions ? (
          // ...existing code...
          <div className="space-y-3">
            <div className="text-center mb-6 animate-fade-in">
              <img 
                src="/chatbot.svg"
                alt="Icono Chatbot"
                className="w-24 h-24 mx-auto rounded-full object-cover mb-4 shadow-lg"
              />
            </div>
            <div className={`space-y-3 transition-all duration-500 ${buttonsVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}`}> 
              <Button
                onClick={() => handleInitialOption('startChat')}
                className="w-full bg-black hover:bg-gray-800 text-white transition-all duration-200 justify-start h-12 text-sm"
                variant="default"
              >
                <MessageSquare className="mr-3 h-4 w-4 flex-shrink-0" />
                <span className="truncate">{language.startChat}</span>
              </Button>
              <Button
                onClick={() => handleInitialOption('whatsapp')}
                className="w-full bg-green-600 hover:bg-green-700 text-white transition-all duration-200 justify-start h-12 text-sm"
                variant="default"
              >
                <Phone className="mr-3 h-4 w-4 flex-shrink-0" />
                <span className="truncate">{language.whatsapp}</span>
              </Button>
              <Button
                onClick={() => handleInitialOption('faq')}
                className="w-full bg-gray-600 hover:bg-gray-700 text-white transition-all duration-200 justify-start h-12 text-sm"
                variant="default"
              >
                <HelpCircle className="mr-3 h-4 w-4 flex-shrink-0" />
                <span className="truncate">{language.faq}</span>
              </Button>
              <Button
                onClick={() => handleInitialOption('schedule')}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white transition-all duration-200 justify-start h-12 text-sm"
                variant="default"
              >
                <Calendar className="mr-3 h-4 w-4 flex-shrink-0" />
                <span className="truncate">{language.schedule}</span>
              </Button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            {messages.map((message) => (
              <div
                key={message.id}
                className={`flex ${message.isUser ? 'justify-end' : 'justify-start'} transition-opacity duration-200`}
              >
                <div
                  className={`max-w-[85%] px-3 sm:px-4 py-2 rounded-2xl ${
                    message.isUser
                      ? 'bg-black text-white ml-2 sm:ml-4'
                      : 'bg-gray-100 text-gray-800 mr-2 sm:mr-4'
                  }`}
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
                  <p className="text-xs opacity-70 mt-1">
                    {message.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </p>
                </div>
              </div>
            ))}
            {isLoading && (
              <div className="flex justify-start transition-opacity duration-200">
                <div className="bg-gray-100 text-gray-800 px-3 sm:px-4 py-2 rounded-2xl mr-2 sm:mr-4">
                  <div className="flex items-center space-x-2">
                    <TypingDots />
                    <span className="text-sm">{language.thinking}</span>
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
        <div className="p-3 sm:p-4 border-t border-gray-200 transition-opacity duration-300">
          <div className="flex space-x-2">
            <Input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder={language.placeholder}
              onKeyPress={(e) => e.key === 'Enter' && handleSendMessage()}
              className="flex-1 rounded-full border-gray-300 focus:border-black text-sm"
            />
            <Button
              onClick={handleSendMessage}
              disabled={isLoading || !input.trim()}
              size="icon"
              className="rounded-full bg-black hover:bg-gray-800 disabled:bg-gray-300 flex-shrink-0"
            >
              <Send className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ChatBot;
