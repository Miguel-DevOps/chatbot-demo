import React from 'react';
import { ChatHeader } from './ChatHeader';
import { InitialOptions } from './InitialOptions';
import { MessageList } from './MessageList';
import { ChatInput } from './ChatInput';
import type { Message } from './useChat';

interface ChatWindowProps {
  messages: Message[];
  input: string;
  showInitialOptions: boolean;
  buttonsVisible: boolean;
  messagesEndRef: React.RefObject<HTMLDivElement | null>;
  isApiHealthy: boolean;
  isLoading: boolean;
  setInput: (value: string) => void;
  handleSendMessage: () => void;
  handleGoHome: () => void;
  closeChat: () => void;
  handleInitialOption: (option: string) => void;
  updateMessageTyping: (messageId: string) => void;
}

export const ChatWindow: React.FC<ChatWindowProps> = ({
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
  closeChat,
  handleInitialOption,
  updateMessageTyping
}) => {
  return (
    <div 
      className="fixed bottom-6 right-6 w-[380px] h-[600px] bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col z-50 overflow-hidden transition-all duration-300 ease-out transform-gpu"
      style={{
        transformOrigin: 'bottom right',
      }}
    >
      <ChatHeader
        isApiHealthy={isApiHealthy}
        showInitialOptions={showInitialOptions}
        onClose={closeChat}
        onGoHome={handleGoHome}
      />

      {showInitialOptions ? (
        <InitialOptions
          buttonsVisible={buttonsVisible}
          onOptionSelect={handleInitialOption}
        />
      ) : (
        <>
          <MessageList
            messages={messages}
            isLoading={isLoading}
            messagesEndRef={messagesEndRef}
            onTypingComplete={updateMessageTyping}
          />
          <ChatInput
            input={input}
            onInputChange={setInput}
            onSendMessage={handleSendMessage}
            isLoading={isLoading}
          />
        </>
      )}
    </div>
  );
};