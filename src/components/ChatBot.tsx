import React from 'react';
import { useChat } from './chatbot/useChat';
import { ChatWindow } from './chatbot/ChatWindow';
import { ChatTrigger } from './chatbot/ChatTrigger';

const ChatBot: React.FC = () => {
  const {
    isOpen,
    messages,
    input,
    showInitialOptions,
    buttonsVisible,
    messagesEndRef,
    isApiHealthy,
    isLoading,
    setInput,
    handleSendMessage,
    handleGoHome,
    openChat,
    closeChat,
    handleInitialOption,
    updateMessageTyping
  } = useChat();

  return (
    <>
      {isOpen ? (
        <ChatWindow
          messages={messages}
          input={input}
          showInitialOptions={showInitialOptions}
          buttonsVisible={buttonsVisible}
          messagesEndRef={messagesEndRef}
          isApiHealthy={isApiHealthy}
          isLoading={isLoading}
          setInput={setInput}
          handleSendMessage={handleSendMessage}
          handleGoHome={handleGoHome}
          closeChat={closeChat}
          handleInitialOption={handleInitialOption}
          updateMessageTyping={updateMessageTyping}
        />
      ) : (
        <ChatTrigger onClick={openChat} />
      )}
    </>
  );
};

export default ChatBot;
