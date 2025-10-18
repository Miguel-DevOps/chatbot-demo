import { useEffect } from 'react';
import { useChatAPI } from '@/hooks/api/useChatAPI';
import { useChatMessages } from './useChatMessages';
import { useChatState } from './useChatState';
import { useChatInput } from './useChatInput';

// Re-export Message interface for backwards compatibility
export { type Message } from './useChatMessages';

export const useChat = () => {
  // Use the specialized hooks
  const chatMessages = useChatMessages();
  const chatState = useChatState();
  const chatInput = useChatInput({ addMessage: chatMessages.addMessage });

  const { apiHealth, isApiHealthy } = useChatAPI();

  // Configuración para verificar estado de la API al abrir
  useEffect(() => {
    if (chatState.isOpen) {
      apiHealth.refetch();
    }
  }, [chatState.isOpen, apiHealth]);

  const handleGoHome = () => {
    chatState.resetToInitialState();
    chatMessages.clearMessages();
    chatInput.clearInput();
  };

  const handleInitialOption = (option: string) => {
    chatState.hideInitialView();
    chatInput.handleInitialOption(option);
  };

  return {
    // State from messages hook
    messages: chatMessages.messages,
    messagesEndRef: chatMessages.messagesEndRef,
    
    // State from chat state hook
    isOpen: chatState.isOpen,
    showInitialOptions: chatState.showInitialOptions,
    buttonsVisible: chatState.buttonsVisible,
    
    // State from input hook
    input: chatInput.input,
    isLoading: chatInput.isLoading,
    
    // API state
    isApiHealthy,
    
    // Actions from input hook
    setInput: chatInput.setInput,
    handleSendMessage: chatInput.handleSendMessage,
    
    // Actions from chat state hook
    toggleChat: chatState.toggleChat,
    openChat: chatState.openChat,
    closeChat: chatState.closeChat,
    
    // Actions from messages hook
    updateMessageTyping: chatMessages.updateMessageTyping,
    
    // Composed actions
    handleGoHome,
    handleInitialOption
  };
};