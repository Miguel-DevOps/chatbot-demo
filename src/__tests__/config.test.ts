import { describe, it, expect } from 'vitest';
import { API_CONFIG, getCurrentConfig } from '../config/api';

describe('Config API', () => {
  it('API_CONFIG.development debe tener propiedades baseUrl y endpoints', () => {
    expect(API_CONFIG).toHaveProperty('development');
    expect(API_CONFIG.development).toHaveProperty('baseUrl');
    expect(API_CONFIG.development).toHaveProperty('endpoints');
  });

  it('getCurrentConfig debe retornar configuración válida', () => {
    // Mock window.location para entorno de test
    global.window = Object.create(window);
    Object.defineProperty(window, 'location', {
      value: { hostname: 'localhost' }
    });
    const config = getCurrentConfig();
    // Validar que config es un objeto con baseUrl y endpoints
    expect(typeof config).toBe('object');
    expect(config).not.toBeNull();
    expect(config).toHaveProperty('baseUrl');
    expect(config).toHaveProperty('endpoints');
  });
});
