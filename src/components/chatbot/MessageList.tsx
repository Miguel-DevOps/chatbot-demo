import React from 'react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Message } from './Message';
import { TypingIndicator } from './TypingIndicator';
import type { Message as MessageType } from './useChat';

interface MessageListProps {
  messages: MessageType[];
  isLoading: boolean;
  messagesEndRef: React.RefObject<HTMLDivElement | null>;
  onTypingComplete: (messageId: string) => void;
}

export const MessageList: React.FC<MessageListProps> = ({ 
  messages, 
  isLoading, 
  messagesEndRef, 
  onTypingComplete 
}) => {
  return (
    <ScrollArea className="flex-1 p-4">
      <div className="space-y-4">
        {messages.map((message) => (
          <Message 
            key={message.id} 
            message={message} 
            onTypingComplete={onTypingComplete}
          />
        ))}
        
        {/* Typing indicator */}
        {isLoading && <TypingIndicator />}
        
        <div ref={messagesEndRef} />
      </div>
    </ScrollArea>
  );
};