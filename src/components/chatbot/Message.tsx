import React from 'react';
import { TypewriterText } from './TypewriterText';
import type { Message as MessageType } from './useChat';

interface MessageProps {
  message: MessageType;
  onTypingComplete: (messageId: string) => void;
}

export const Message: React.FC<MessageProps> = ({ message, onTypingComplete }) => {
  return (
    <div
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
              onComplete={() => onTypingComplete(message.id)}
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
  );
};