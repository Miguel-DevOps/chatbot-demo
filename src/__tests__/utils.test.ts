import { describe, it, expect } from 'vitest';
import { cn } from '../lib/utils';

describe('Utilidades', () => {
  it('cn debería unir clases correctamente', () => {
    expect(cn('a', 'b')).toBe('a b');
    const condition = false;
    expect(cn('a', condition && 'b')).toBe('a');
  });
});
