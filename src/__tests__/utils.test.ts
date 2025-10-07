import { describe, it, expect } from 'vitest';
import { cn } from '../lib/utils';

describe('Utilidades', () => {
  it('cn should merge classes correctly', () => {
    expect(cn('a', 'b')).toBe('a b');
    const condition = false;
    expect(cn('a', condition && 'b')).toBe('a');
  });
});
