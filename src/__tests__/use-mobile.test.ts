import { describe, it, expect } from 'vitest';
import { useIsMobile } from '../hooks/use-mobile';

describe('useIsMobile', () => {
  it('debería ser una función', () => {
    expect(typeof useIsMobile).toBe('function');
  });
});
