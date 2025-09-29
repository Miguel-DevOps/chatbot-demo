/**
 * Tipos de API generados automáticamente desde OpenAPI
 * 
 * Este archivo re-exporta tipos importantes de la especificación OpenAPI
 * para uso más conveniente en el código de la aplicación.
 * 
 * Los tipos se generan automáticamente ejecutando: pnpm run generate-types
 */

import type { components, operations } from './api.generated';

// Re-exportar tipos de componentes principales
export type ChatRequest = components['schemas']['ChatRequest'];
export type ChatResponse = components['schemas']['ChatResponse'];
export type HealthResponse = components['schemas']['HealthResponse'];
export type ApiError = components['schemas']['ApiError'];
export type ValidationError = components['schemas']['ValidationError'];
export type RateLimitError = components['schemas']['RateLimitError'];
export type ExternalServiceError = components['schemas']['ExternalServiceError'];
export type ApiInfo = components['schemas']['ApiInfo'];

// Re-exportar tipos de operaciones (para futuros hooks tipados)
export type SendChatMessageOperation = operations['sendChatMessage'];
export type GetHealthOperation = operations['getHealth'];
export type GetApiInfoOperation = operations['getApiInfo'];

// Tipos de respuesta para cada operación
export type SendChatMessageResponse = SendChatMessageOperation['responses'][200]['content']['application/json'];
export type GetHealthResponse = GetHealthOperation['responses'][200]['content']['application/json'];
export type GetApiInfoResponse = GetApiInfoOperation['responses'][200]['content']['application/json'];

// Union type para todos los posibles errores de API
export type ApiErrorResponse = ApiError | ValidationError | RateLimitError | ExternalServiceError;

// Tipo helper para requests del chat
export type ChatRequestBody = SendChatMessageOperation['requestBody']['content']['application/json'];

// Re-exportar todas las rutas y operaciones para casos avanzados
export type { paths, components, operations } from './api.generated';