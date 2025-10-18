import { useState, useEffect } from 'react';

export const useChatState = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [showInitialOptions, setShowInitialOptions] = useState(true);
  const [buttonsVisible, setButtonsVisible] = useState(false);

  // Configurar la visibilidad de los botones cuando cambie el estado del chat
  useEffect(() => {
    if (isOpen && showInitialOptions) {
      // Animar la aparición de los botones con delay
      setTimeout(() => setButtonsVisible(true), 300);
    } else {
      // También usar setTimeout para evitar setState sincrónico en el effect
      setTimeout(() => setButtonsVisible(false), 0);
    }
  }, [isOpen, showInitialOptions]);

  const toggleChat = () => {
    setIsOpen(!isOpen);
  };

  const openChat = () => {
    setIsOpen(true);
  };

  const closeChat = () => {
    setIsOpen(false);
  };

  const showInitialView = () => {
    setShowInitialOptions(true);
  };

  const hideInitialView = () => {
    setShowInitialOptions(false);
  };

  const resetToInitialState = () => {
    setShowInitialOptions(true);
    setButtonsVisible(false);
  };

  return {
    // State
    isOpen,
    showInitialOptions,
    buttonsVisible,
    
    // Actions
    toggleChat,
    openChat,
    closeChat,
    showInitialView,
    hideInitialView,
    resetToInitialState
  };
};