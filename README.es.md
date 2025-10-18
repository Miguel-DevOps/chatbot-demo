<div align="center">

# 🤖 Chatbot Demo

**Un blueprint educativo de chatbot que demuestra arquitectura de software evolutiva**  
_Código limpio • Pruebas exhaustivas • Estrategia progresiva de despliegue_

[![MIT License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](./LICENSE)
[![CI/CD Pipeline](https://img.shields.io/badge/CI%2FCD-GitHub%20Actions-blue?style=for-the-badge&logo=githubactions)](#)
[![Tests](https://img.shields.io/badge/Tests-99%20Passing-brightgreen?style=for-the-badge)](#pruebas-y-calidad)
[![Architecture Status](https://img.shields.io/badge/Status-Blueprint%20Educativo-orange?style=for-the-badge)](#vision)</div>

<div align="center">

![React](https://img.shields.io/badge/React-19.2-20232A?style=for-the-badge&logo=react&logoColor=61DAFB)
![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?style=for-the-badge&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Slim Framework](https://img.shields.io/badge/Slim-Framework%204-719E40?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)

</div>

## 📋 Índice

- [🎯 Visión del proyecto](#-visión-del-proyecto)
- [⚡ Inicio rápido](#-inicio-rápido)
- [🏗️ Niveles de despliegue y arquitectura](#️-niveles-de-despliegue-y-arquitectura)
- [🗺️ Hoja de ruta estratégica](#️-hoja-de-ruta-estratégica)
- [🏛️ Decisiones arquitectónicas clave](#️-decisiones-arquitectónicas-clave)
- [� Observabilidad y monitorización](#-observabilidad-y-monitorización)
- [�🔧 Configuración y seguridad](#-configuración-y-seguridad)
- [🧪 Pruebas y calidad](#-pruebas-y-calidad)
- [🤝 Contribuir](#-contribuir)
- [📄 Licencia](#-licencia)
- [📄 English Documentation](./README.md)

## 🎯 Visión del proyecto

Este proyecto actúa como un **blueprint educativo** para evolucionar una aplicación de chatbot desde una prueba de concepto hasta un despliegue empresarial. Muestra arquitectura limpia, pruebas exhaustivas y patrones de evolución arquitectónica mediante **restricciones intencionales** que reproducen retos reales de desarrollo.

**Enfoque educativo:** En lugar de presentar una solución «perfecta», esta base de código incluye limitaciones arquitectónicas deliberadas que crean oportunidades de aprendizaje para una evolución sistemática. Cada restricción enseña lecciones concretas sobre escalabilidad, gestión de estado y consideraciones de despliegue.

**Estado actual:** Totalmente funcional con alta calidad de código (99 tests pasando, 95%+ cobertura), pruebas exhaustivas y prácticas modernas de desarrollo. Preparado para desarrollo, demostraciones y despliegues educativos, con una ruta clara hacia producción empresarial.

## ⚡ Inicio rápido

### 🐳 Método 1: Docker (recomendado)

La forma más rápida de ejecutar toda la aplicación:

```bash
# Clona y arranca
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo
cp .env.example .env
# Edita .env con tu GEMINI_API_KEY

# Inicia todo con Docker
docker-compose up --build
```

**Accesos:**

- Frontend: http://localhost:3000
- API: http://localhost:8080
- Documentación API: http://localhost:8080/docs

### 🛠️ Método 2: Instalación manual (educativa)

Para desarrolladores que quieren entender la pila:

**Requisitos previos:**

```bash
# Versiones requeridas
PHP >= 8.1.0 (extensiones: json, mbstring, pdo, sqlite3, curl, openssl)
Node.js >= 18.0.0
pnpm >= 8.0.0
```

**Instalación:**

```bash
# 1. Instala dependencias
composer install --working-dir=api
pnpm install

# 2. Configura entorno
cp .env.example .env
# Edita .env con tu configuración

# 3. Arranca servidores de desarrollo
pnpm dev                                    # Frontend (http://localhost:5173)
# Note: php -S está depreciado - usa Docker para desarrollo apropiado
```

> 🚨 **DEPRECIADO**: `php -S` crea divergencia de entornos y ya no se recomienda. Usa `docker-compose up` para desarrollo que coincida con producción.

## �️ Niveles de despliegue y arquitectura

### 📊 Matriz de estrategia de despliegue

| **Nivel**     | **Caso de uso**          | **Infraestructura**    |             **Estado** | **Almacenamiento de estado** | **Contenido**       |
| ------------- | ------------------------ | ---------------------- | ---------------------: | ---------------------------- | ------------------- |
| **Nivel 0**   | Desarrollo/Demo          | Docker Compose         |           ✅ **Listo** | Archivos SQLite              | Archivos Markdown   |
| **Nivel 0.5** | Hosting compartido / VPS | Apache/Nginx + FPM     | 🟡 **Requiere Fase 2** | MySQL/PostgreSQL             | Base de datos       |
| **Nivel 1**   | VPS dedicado             | Docker + Base de datos | 🟡 **Requiere Fase 2** | Base de datos externa        | Base de datos / CMS |
| **Nivel 2**   | Empresa / Cloud-native   | Kubernetes + Redis     | 🔴 **Requiere Fase 3** | Cluster Redis                | Headless CMS        |

### 🏛️ Arquitectura objetivo (Nivel 2)

```mermaid
graph TB
    subgraph "Frontend"
        React[React App] --> CDN[CDN]
    end
    
    subgraph "Load Balancer"
        LB[Nginx/ALB] --> API1[API Pod 1]
        LB --> API2[API Pod 2]
        LB --> API3[API Pod N]
    end
    
    subgraph "Capa de estado"
        API1 --> Redis[(Redis Cluster)]
        API2 --> Redis
        API3 --> Redis
    end
    
    subgraph "Contenido & AI"
        API1 --> CMS[Headless CMS]
        API1 --> Gemini[Google Gemini]
        API2 --> CMS
        API3 --> CMS
    end
    
    subgraph "Observabilidad"
        API1 --> Monitoring[Prometheus/Grafana]
        API2 --> Logs[ELK/Loki]
        API3 --> Tracing[Jaeger]
    end
```

### 🚨 Advertencias de despliegue

**Nivel 0 (actual):** Ideal para desarrollo, demos y aprendizaje. **No es apto para producción** por dependencias en el sistema de archivos.

**Nivel 0.5 - Retos:** El hosting compartido requiere configurar correctamente permisos de archivos, PHP-FPM y conectividad a base de datos. La dependencia actual en SQLite impide el escalado horizontal.

**Preparación para producción:** Los niveles 1+ requieren completar la Fase 2 (abstracción de estado) y la Fase 3 (contenedorización/orquestación).

## 🗺️ Hoja de ruta estratégica

### ✅ Fase 1: Fundación (Completa)

- **Arquitectura limpia:** Principios SOLID, inyección de dependencias, cumplimiento PSR
- **Pruebas exhaustivas:** Unitarias + Integración + E2E con cobertura objetivo alta
- **Pila moderna:** React 19, TypeScript 5.9, PHP 8.1+, Slim Framework 4
- **CI/CD:** Tests automatizados, auditorías de seguridad, validación OpenAPI
- **Experiencia de desarrollador:** Hot reload, tipado, linting, formato de código

### 🚧 Fase 2: Abstracción de estado (En progreso)

**Objetivo:** Eliminar dependencias en el sistema de archivos para habilitar Nivel 0.5-1

**Tareas críticas:**

- **Interfaz StateStore:** Extraer limitador de tasas de SQLite para soportar Redis/MySQL
- **Interfaz ContentStore:** Migrar la base de conocimiento de archivos a base de datos/CMS
- **Migraciones de base de datos:** Implementar Phinx o similar para gestión de esquemas
- **Gestión de configuración:** Externalizar valores hardcodeados a variables de entorno

**Habilita:** Hosting compartido, despliegue en VPS, preparación para escalado horizontal

### 📋 Fase 3: Madurez operacional (Planificada)

**Objetivo:** Despliegue empresarial con observabilidad y seguridad

**Características:**

- **Contenerización:** Builds multi-stage Docker, manifiestos Kubernetes
- **Observabilidad:** Logging estructurado, métricas (Prometheus), trazas distribuidas
- **Seguridad:** OAuth2/OIDC, gestión de JWT, marco de validación de entradas
- **Automatización:** Helm, GitOps, escalado automático

## 🏛️ Decisiones arquitectónicas clave

| **Tecnología**            | **Elección**          | **Alternativa**  | **Compromiso**                                                                                 |
| ------------------------- | --------------------- | ---------------- | ---------------------------------------------------------------------------------------------- |
| **Framework backend**     | Slim 4                | Laravel/Symfony  | Sacrificamos rapidez de scaffolding por transparencia arquitectónica y cumplimiento PSR        |
| **Frontend**              | React 19 + TypeScript | Vue/Angular      | Aceptamos curva de aprendizaje por seguridad en tiempo de compilación y madurez del ecosistema |
| **Gestión de estado**     | TanStack Query        | Redux/Zustand    | Adoptamos complejidad de cache para sincronización automática de server state                  |
| **Contenedor DI**         | PHP-DI                | DI del framework | Elegimos lo explícito sobre lo implícito para mejor testabilidad y trazabilidad                |
| **Estrategia de pruebas** | PHPUnit + Vitest      | Ecosistema único | Mantuvimos separación de responsabilidades a costa de complejidad de herramientas              |

> **ADRs completas:** Decisiones arquitectónicas detalladas con contexto y rationale se documentan en `/docs/ADRs/` (en progreso)

## � Observabilidad y monitorización

### 🚀 **Stack completo de observabilidad**

El proyecto incluye una infraestructura de observabilidad lista para producción con recolección de métricas, visualización y trazabilidad distribuida.

#### **🎯 Inicio rápido con observabilidad**

```bash
# Desarrollo con stack completo de monitorización
pnpm dev:monit:up

# Producción con observabilidad
pnpm prod:monit:up

# Acceder a las interfaces de monitorización
open http://localhost:3000    # Grafana (admin/admin)
open http://localhost:9090    # Prometheus
open http://localhost:16686   # Jaeger UI
```

#### **📈 Arquitectura de monitorización**

```
Capa de aplicación:
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Nginx Proxy   │───▶│  Chatbot API    │───▶│ OpenTelemetry  │
│     :80/443     │    │    :9000        │    │   Collector     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │                       │
                                ▼                       ▼
Recolección métricas:      ┌─────────────────┐    ┌─────────────────┐
                          │   Prometheus    │    │     Jaeger      │
                          │     :9090       │    │    :16686       │
                          └─────────────────┘    └─────────────────┘
                                │
                                ▼
Visualización:             ┌─────────────────┐
                          │    Grafana      │
                          │     :3000       │
                          └─────────────────┘
```

#### **🔍 Métricas disponibles**

**Métricas de rendimiento API:**

- `chatbot_api_http_requests_total` - Contador de requests por endpoint, método, estado
- `chatbot_api_http_request_duration_seconds` - Histogramas de latencia de requests
- Tasa de requests por segundo (promedio 5 minutos)
- Tasas de error por endpoint y código de estado

**Métricas del sistema:**

- Uso de memoria y límites
- Utilización de CPU
- Uso de disco y I/O
- Throughput de red

#### **📊 Dashboards preconfigurados**

**Dashboard resumen API** (`/monitoring/grafana/dashboards/`)

- Tasa de requests HTTP y latencia
- Seguimiento de tasas de error (respuestas 4xx, 5xx)
- Top endpoints por tráfico y latencia
- Utilización de recursos del sistema

**Métricas en tiempo real disponibles:**

```bash
# Probar el endpoint de métricas
curl http://localhost/api/v1/metrics

# Consultar Prometheus directamente
curl "http://localhost:9090/api/v1/query?query=chatbot_api_http_requests_total"
```

#### **📋 Servicios del stack de monitorización**

| **Servicio**                | **Puerto** | **Propósito**                          | **Estado**   |
| --------------------------- | ---------- | -------------------------------------- | ------------ |
| **Prometheus**              | 9090       | Recolección y almacenamiento métricas  | ✅ **Listo** |
| **Grafana**                 | 3000       | Visualización y dashboards métricas    | ✅ **Listo** |
| **Jaeger**                  | 16686      | Visualización trazabilidad distribuida | ✅ **Listo** |
| **OpenTelemetry Collector** | 4317/4318  | Hub recolección trazas y métricas      | ✅ **Listo** |

#### **🎯 Estado actual de observabilidad**

**✅ Completamente implementado:**

- Recolección completa de métricas desde endpoints API
- Scraping Prometheus en tiempo real (intervalos 10s)
- Dashboards Grafana preconfigurados
- Monitorización health checks
- Métricas request/response con etiquetas
- Monitorización recursos contenedores
- Descubrimiento automático de servicios

**🚧 Infraestructura lista (necesita instrumentación app):**

- Trazabilidad distribuida con Jaeger
- Recolección trazas OpenTelemetry
- Métricas de negocio personalizadas
- Seguimiento avanzado de errores

**Variables de entorno observabilidad:**

```bash
# Configuración observabilidad
OTEL_SERVICE_NAME=chatbot-api          # Nombre servicio para trazas
OTEL_TRACES_ENABLED=true               # Habilitar trazabilidad distribuida
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318  # Endpoint collector
```

## �🔧 Configuración y seguridad

### 🌍 Variables de entorno

**Requeridas:**

```bash
GEMINI_API_KEY=tu_clave_google_gemini_aqui
LOG_LEVEL=info
CORS_ORIGINS="http://localhost:3000,http://localhost:5173"
```

**Fase 2 (próximamente):**

```bash
# Configuración de base de datos
DATABASE_URL="mysql://usuario:pass@host:puerto/dbname"
STATE_STORE_TYPE="database"  # sqlite|database|redis
CONTENT_STORE_TYPE="database"  # filesystem|database|cms

# Limitador de tasa
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

### 🔒 Configuración de servidor para producción

#### Nginx + PHP-FPM (recomendado)

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    # SSL (Certbot / Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    # CRÍTICO: root debe apuntar a api/public
    root /var/www/chatbot-demo/api/public;
    index index.php;
    
    # Cabeceras de seguridad
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # Bloquear archivos sensibles
    location ~ /\.(env|git) { deny all; }
    location ~ \.(json|lock|md)$ { deny all; }
    
    # Procesado PHP
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # URLs limpias
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

#### Apache + mod_php

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/chatbot-demo/api/public
    
    # SSL
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/your-domain.com/chain.pem
    
    # Bloquear acceso a directorios padre
    <Directory "/var/www/chatbot-demo">
        Require all denied
    </Directory>
    
    <Directory "/var/www/chatbot-demo/api/public">
        AllowOverride All
        Require all granted
        
        # URLs limpias
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]
    </Directory>
</VirtualHost>
```

### 🛡️ Lista de comprobación de seguridad

**Desarrollo:**

- [ ] Nunca commitear archivos `.env`
- [ ] Usar claves distintas por entorno
- [ ] Activar `error_reporting=0` en PHP de producción

**Producción:**

- [ ] Configurar HTTPS con certificados válidos
- [ ] Permisos de ficheros adecuados (`644` archivos, `755` directorios)
- [ ] Bloquear acceso a ficheros sensibles (`.env`, `.git`, etc.)
- [ ] Usar PHP-FPM en lugar de `mod_php` cuando sea posible
- [ ] Implementar limitación de peticiones a nivel de servidor web

## 🧪 Pruebas y calidad

### 🔬 Estrategia de pruebas

**Pirámide de pruebas:**

- **Unitarias:** `pnpm test` (Frontend) + `composer test` (Backend) — rápidas e aisladas
- **Integración:** Validación de contratos API mediante OpenAPI
- **E2E:** `pnpm test:e2e` — flujos completos de usuario

**Puertas de calidad:**

```bash
# Validaciones pre-commit
pnpm lint && pnpm typecheck    # Calidad de código
pnpm test                      # Tests unitarios
composer test                  # Tests backend

# Auditoría de seguridad
pnpm audit && composer audit   # Vulnerabilidades en dependencias
```

**Objetivos de cobertura:**

- Lógica de negocio: 90%+ (Servicios, Controladores)
- Componentes UI: 80%+ (Interacciones, estados de error)  
- Integración: 100% (Contratos API, servicios externos)

## 🤝 Contribuir

### 🎯 Áreas de alta prioridad

| **Prioridad**  | **Área**                         | **Habilidades**              | **Impacto**                         |
| -------------- | -------------------------------- | ---------------------------- | ----------------------------------- |
| 🔥 **Crítico** | Fase 2: Abstracción de estado    | PHP, SQL, Patrones de diseño | Habilita despliegue Nivel 0.5-1     |
| 🔥 **Crítico** | Producción con Docker            | DevOps, Docker, Nginx        | Contenedores listos para producción |
| ⭐ **Alto**    | Migraciones de BD (Phinx)        | PHP, Diseño de BD            | Gestión de esquemas                 |
| ⭐ **Alto**    | Interfaz de gestión de contenido | PHP, APIs, Integración CMS   | Contenido escalable                 |
| 💡 **Medio**   | Manifiestos Kubernetes           | K8s, Helm, DevOps            | Despliegue empresarial              |
| 💡 **Medio**   | Observabilidad                   | Monitorización, Logging      | Excelencia operacional              |

### 🏗️ Guía para contribuyentes

**Antes de empezar:**

1. **Piensa en interfaces:** Diseña abstracciones que permitan intercambiar tecnologías
2. **Pruebas primero:** Escribe tests fallidos antes de implementar
3. **Configuración externa:** Nada hardcodeado — todo configurable
4. **Documenta:** Actualiza ADRs para decisiones arquitectónicas

**Flujo de desarrollo:**

```bash
# 1. Fork y clona
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo

# 2. Crea rama de características
git checkout -b feature/database-abstraction

# 3. Desarrollo con pruebas
./scripts/test.sh    # Validar cambios

# 4. Envía PR con:
#    - Descripción clara
#    - Tests actualizados
#    - Documentación
#    - Notas sobre posibles breaking changes
```

**Áreas por nivel de experiencia:**

- **Principiante:** Mejoras UI, cobertura de tests, documentación
- **Intermedio:** Implementación de interfaces, gestión de configuración
- **Avanzado:** Diseño arquitectónico, infraestructura, observabilidad

## 📄 Licencia

Licencia MIT - ver [LICENSE](./LICENSE) para detalles.

**Uso educativo recomendado:**

- ✅ Usar como referencia para evolución arquitectónica
- ✅ Fork para talleres y formación
- ✅ Adaptar patrones a proyectos propios
- ✅ Referenciar en blogs técnicos y documentación

---

<div align="center">

_Este proyecto sirve como documento vivo para la evolución arquitectónica. Se aceptan contribuciones._

[![GitHub](https://img.shields.io/badge/GitHub-Miguel--DevOps-black.svg)](https://github.com/Miguel-DevOps)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Connect-blue.svg)](https://linkedin.com/in/miguel-lozano-devops)

_Construido con ❤️ para la comunidad de desarrolladores_

</div>
