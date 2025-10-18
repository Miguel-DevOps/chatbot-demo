import { useState } from 'react';
import { useChatAPI } from '@/hooks/api/useChatAPI';

interface UseChatInputProps {
  addMessage: (content: string, isUser?: boolean) => void;
}

export const useChatInput = ({ addMessage }: UseChatInputProps) => {
  const [input, setInput] = useState('');

  const { 
    sendMessage, 
    validateMessage
  } = useChatAPI();

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

  const clearInput = () => {
    setInput('');
  };

  const handleInitialOption = (option: string) => {
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
    input,
    isLoading: sendMessage.isLoading,
    
    // Actions
    setInput,
    handleSendMessage,
    clearInput,
    handleInitialOption
  };
};