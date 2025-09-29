import { describe, it, expect } from 'vitest';
import { reducer } from '../hooks/use-toast';

describe('Toast reducer', () => {
  it('debería ser una función', () => {
    expect(typeof reducer).toBe('function');
  });
});
