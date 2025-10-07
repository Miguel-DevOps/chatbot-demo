import { describe, it, expect } from 'vitest';
import { reducer } from '../hooks/use-toast';

describe('Toast reducer', () => {
  it('should be a function', () => {
    expect(typeof reducer).toBe('function');
  });
});
