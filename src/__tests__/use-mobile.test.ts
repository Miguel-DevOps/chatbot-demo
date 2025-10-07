import { describe, it, expect } from 'vitest';
import { useIsMobile } from '../hooks/use-mobile';

describe('useIsMobile', () => {
  it('should be a function', () => {
    expect(typeof useIsMobile).toBe('function');
  });
});
