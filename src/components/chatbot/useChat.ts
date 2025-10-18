import { useState, useRef, useEffect } from 'react';
import { useChatAPI } from '@/hooks/api/useChatAPI';

export interface Message {
  id: string;
  content: string;
  isUser: boolean;
  timestamp: Date;
  isTyping?: boolean;
}

export const useChat = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [showInitialOptions, setShowInitialOptions] = useState(true);
  const [buttonsVisible, setButtonsVisible] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const { 
    sendMessage, 
    apiHealth,
    validateMessage,
    isApiHealthy
  } = useChatAPI();

  // Función para hacer scroll al final de los mensajes
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
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
      // También usar setTimeout para evitar setState sincrónico en el effect
      setTimeout(() => setButtonsVisible(false), 0);
    }
  }, [isOpen, showInitialOptions]);

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
      addMessage(validation.error || 'Error al validar mensaje');
      return;
    }

    setInput('');
    addMessage(userMessage, true);

    // Usar la mutación del hook para enviar el mensaje
    sendMessage.mutate(userMessage, {
      onSuccess: (response) => {
        addMessage(response.response || 'Por favor, reformula tu pregunta');
      },
      onError: () => {
        addMessage('Error al procesar tu mensaje');
      }
    });
  };

  const handleGoHome = () => {
    setShowInitialOptions(true);
    setMessages([]);
    setInput('');
  };

  const toggleChat = () => {
    setIsOpen(!isOpen);
  };

  const openChat = () => {
    setIsOpen(true);
  };

  const closeChat = () => {
    setIsOpen(false);
  };

  const handleInitialOption = (option: string) => {
    setShowInitialOptions(false);
    
    switch (option) {
      case 'startChat': {
        const welcomeMessage = 'Hola! Soy tu asistente virtual. ¿En qué puedo ayudarte hoy?';
        addMessage(welcomeMessage);
        break;
      }
      case 'whatsapp': {
        const whatsappMessage = 'Te redirigiré a WhatsApp para una atención más personalizada.';
        addMessage(whatsappMessage);
        window.open('https://wa.me/573134692221', '_blank');
        break;
      }
      case 'faq': {
        const faqMessage = 'Aquí tienes algunas preguntas frecuentes...';
        addMessage(faqMessage);
        break;
      }
      case 'schedule': {
        const scheduleMessage = 'Te redirigiré a nuestro calendario para agendar una cita.';
        addMessage(scheduleMessage);
        window.open('https://calendar.app.google/DdMWDtRisQ9RCPBu6', '_blank');
        break;
      }
    }
  };

  return {
    // State
    isOpen,
    messages,
    input,
    showInitialOptions,
    buttonsVisible,
    messagesEndRef,
    isApiHealthy,
    isLoading: sendMessage.isLoading,
    
    // Actions
    setInput,
    handleSendMessage,
    handleGoHome,
    toggleChat,
    openChat,
    closeChat,
    handleInitialOption,
    addMessage,
    updateMessageTyping
  };
};