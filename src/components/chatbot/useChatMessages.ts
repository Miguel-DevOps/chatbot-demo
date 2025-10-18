import { useState, useRef, useEffect } from 'react';

export interface Message {
  id: string;
  content: string;
  isUser: boolean;
  timestamp: Date;
  isTyping?: boolean;
}

export const useChatMessages = () => {
  const [messages, setMessages] = useState<Message[]>([]);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Función para hacer scroll al final de los mensajes
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  // Auto-scroll cuando se agregan mensajes
  useEffect(() => {
    scrollToBottom();
  }, [messages]);

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

  const clearMessages = () => {
    setMessages([]);
  };

  return {
    messages,
    messagesEndRef,
    addMessage,
    updateMessageTyping,
    clearMessages,
    scrollToBottom
  };
};