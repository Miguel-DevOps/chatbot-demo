# Chatbot Demo

![React](https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=for-the-badge&logo=typescript&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=FFD62E)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-38B2AC?style=for-the-badge&logo=tailwindcss&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![MIT License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## Tabla de Contenidos

1. [Objetivo del Proyecto](#-objetivo-del-proyecto)
2. [Características Clave](#-características-clave)
3. [Flujo de Test y Validación Final](#-flujo-de-test-y-validación-final)
4. [Instalación y Uso](#-instalación-y-uso)
5. [Estructura del Proyecto](#-estructura-del-proyecto)
6. [Personalización](#-personalización)
7. [Integración WordPress](#-integrar-en-wordpress)
8. [Contribuir](#-contribuir)
9. [Licencia](#-licencia)
10. [Recursos útiles](#-recursos-útiles)
11. [Read this in English](./README.en.md)

---

> **Chatbot Demo** es una solución profesional, moderna y portable, pensada para funcionar en cualquier entorno de hosting, incluyendo servidores compartidos que solo admiten PHP. Su arquitectura desacoplada combina React y PHP para ofrecer una experiencia rápida, flexible y fácil de integrar en sitios web existentes, incluyendo WordPress.

## 🛠️ Tecnologías y Herramientas

| Frontend         | Backend         | Integración & DevOps   |
|------------------|----------------|-----------------------|
| React            | PHP            | ESLint                |
| TypeScript       | Endpoints REST | PostCSS               |
| Vite             |                | Tailwind CSS          |
| Tailwind CSS     |                | Scripts Bash (.sh)    |
| Lucide Icons     |                | Vitest (testing)      |
| Radix UI         |                | PHPUnit (testing)     |

- **API de IA utilizada:** Este proyecto utiliza la API de Gemini para procesamiento de lenguaje natural y generación de respuestas inteligentes.

## 🎯 Objetivo del Proyecto

Ofrecer un chatbot portable y desacoplado, instalable en cualquier servidor web, incluso aquellos que solo permiten PHP. Ideal para WordPress, sitios corporativos, blogs y cualquier entorno donde Node.js no esté disponible.

### 🚀 Características Clave

- **100% compatible con PHP compartido:** No requiere Node.js ni bases de datos externas.
- **Frontend desacoplado:** El cliente React puede integrarse fácilmente en cualquier sitio web.
- **API PHP simple:** Los endpoints pueden alojarse en cualquier hosting tradicional.
- **Integración WordPress:** Plugin con bajo impacto en el performance y sin dependencias pesadas.
- **UI moderna y responsiva:** Adaptable a dispositivos móviles y escritorio.
- **Multi-idioma:** Soporte para español e inglés.
- **Fácil personalización:** Cambia textos, estilos y lógica según tus necesidades.
- **Testing automatizado:** Scripts y herramientas para asegurar calidad en frontend y backend.

## 🧪 Flujo de Test y Validación Final

Este proyecto incluye un flujo automatizado y robusto para asegurar calidad antes de cualquier despliegue:

### Testing y validación unificada
- **Script principal:** `sh scripts/test-all.sh`
  - Ejecuta todos los tests de React (Vitest) y PHP (PHPUnit) en modo automático.
  - Verifica la sintaxis de todos los archivos PHP.
  - Genera y valida el build de frontend (`dist/index.html`).
  - Detiene el proceso si hay cualquier error o test fallido.

### Ejecución del flujo

#### Requisitos mínimos
- Node.js >= 18
- npm >= 9
- PHP >= 7.4
- Composer (para PHPUnit)
- Bash (para ejecutar scripts .sh)

1. Instala dependencias:
   ```bash
   npm install
   composer global require phpunit/phpunit
   ```
2. Da permisos de ejecución a los scripts:
   ```bash
   chmod +x scripts/*.sh
   ```
3. Ejecuta el flujo completo:
   ```bash
   sh scripts/test-all.sh
   ```
4. (Opcional) Empaqueta para WordPress:
   ```bash
   sh scripts/build-wordpress-plugin.sh
   ```

### Integración continua (CI/CD)
- **GitHub Actions:** Workflow `.github/workflows/security-check.yml` que ejecuta:
  - Auditoría de dependencias (`npm audit`).
  - Linting y build del código.
  - Ejecución de tests automatizados (React y PHP).
  - Bloqueo de despliegue si hay vulnerabilidades o errores.

### Mejoras técnicas relevantes
- **Arquitectura desacoplada:** Separación total entre frontend (React) y backend (PHP).
- **Alias y configuración unificada:** Alias `@` para imports, configuración centralizada de endpoints y rutas.
- **Scripts multiplataforma:** Todos los scripts funcionan en Linux, macOS y WSL.
- **Soporte para WordPress:** Plugin portable, integración por shortcode, widget o bloque personalizado.

Este flujo asegura que el proyecto sea robusto, portable y fácil de mantener en cualquier entorno profesional.

> **Nota:** Los warnings relacionados con Fast Refresh (`react-refresh/only-export-components`) pueden ser ignorados si no afectan la funcionalidad ni la experiencia del usuario. El workflow permite continuar el despliegue aunque existan warnings no críticos.

#### Ejemplo de personalización del workflow

Puedes adaptar el workflow para agregar pasos adicionales, como:

- Ejecutar tests automatizados de React y PHP:
  ```yaml
  - name: Run React tests
    run: npm run test

  - name: Run PHP tests
    run: |
      composer global require phpunit/phpunit
      phpunit --configuration api/phpunit.xml
  ```
- Desplegar automáticamente a un servidor o servicio cloud.
- Notificar por Slack, Discord o email en caso de error.

#### Cómo usar el workflow

El workflow se ejecuta automáticamente en cada push o pull request a las ramas principales (`main`, `develop`). No requiere intervención manual. Si alguna verificación falla (seguridad, lint, build, tests), el despliegue se bloquea y se muestra el error en la interfaz de GitHub Actions.

Para personalizarlo, edita el archivo `.github/workflows/security-check.yml` según tus necesidades y agrega los pasos requeridos para tu flujo de trabajo.

## ⚡ Instalación y Uso

### 1. Clonar el repositorio

```bash
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo
```

### 2. Instalar dependencias (solo para desarrollo frontend)

```bash
npm install
```

### 3. Ejecutar en modo desarrollo

```bash
npm run dev
```

### 4. Build para producción

```bash
npm run build
```

### 5. Empaquetar para WordPress (opcional)

Si necesitas integrar el chatbot como plugin de WordPress, ejecuta:
```bash
sh scripts/build-wordpress-plugin.sh
```
Esto genera la estructura necesaria para WordPress, copiando los archivos PHP y el frontend compilado.

### 6. Subir archivos PHP

Copia la carpeta `api/` al servidor donde se alojará el backend. No requiere configuración adicional.

### 7. Integrar en WordPress

- Usa los scripts incluidos para generar el plugin WordPress.
- El frontend puede insertarse como shortcode, widget o bloque personalizado.
- El backend PHP funciona como endpoint REST, sin afectar el rendimiento del sitio.

#### Ejemplo de shortcode

```php
[chatbot_demo]
```

También puedes integrarlo como widget o bloque personalizado según tus necesidades.

## 📁 Estructura del Proyecto

```
├── api/                  # Endpoints PHP
│   ├── chat.php
│   ├── knowledge-base.php
│   └── ...
├── src/                  # Código fuente React
│   ├── components/
│   ├── pages/
│   └── ...
├── public/               # Archivos estáticos
├── scripts/              # Scripts para build y verificación
├── package.json
├── vite.config.ts
├── tailwind.config.ts
└── README.md
```

## 🧩 Personalización

- Modifica los textos y lógica en `src/components/ChatBot.tsx`.
  ```tsx
  // Ejemplo: cambiar el texto de bienvenida
  const welcomeText = "¡Hola! ¿En qué puedo ayudarte hoy?";
  ```

- Cambia los endpoints PHP según tus necesidades.
  ```php
  // Ejemplo: agregar un nuevo endpoint en api/mi-endpoint.php
  <?php
  header('Content-Type: application/json');
  echo json_encode(["mensaje" => "Hola desde mi endpoint personalizado"]);
  ```

- Personaliza estilos con Tailwind y los archivos de configuración.
  ```tsx
  // Ejemplo: cambiar el color primario en tailwind.config.ts
  theme: {
    extend: {
      colors: {
        primary: '#1D4ED8', // Cambia el color principal
      },
    },
  }
  ```

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Puedes abrir issues, enviar pull requests o sugerir mejoras.

## 📄 Licencia

Este proyecto se distribuye bajo la licencia MIT.

> **Desarrollado para máxima compatibilidad y facilidad de integración en cualquier entorno PHP.**

## 📚 Recursos útiles

- [Documentación React](https://react.dev/)
- [Documentación TypeScript](https://www.typescriptlang.org/docs/)
- [Documentación Vite](https://vitejs.dev/guide/)
- [Documentación Tailwind CSS](https://tailwindcss.com/docs)
- [Documentación PHP](https://www.php.net/docs.php)
- [Documentación WordPress](https://developer.wordpress.org/rest-api/)
- [Vitest](https://vitest.dev/)
- [PHPUnit](https://phpunit.de/)
