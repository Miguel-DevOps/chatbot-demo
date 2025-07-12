# Chatbot Demo

![React](https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=for-the-badge&logo=typescript&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=FFD62E)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-38B2AC?style=for-the-badge&logo=tailwindcss&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![MIT License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## Table of Contents

1. [Project Objective](#-project-objective)
2. [Key Features](#-key-features)
3. [Testing & Final Validation Flow](#-testing--final-validation-flow)
4. [Installation & Usage](#-installation--usage)
5. [Project Structure](#-project-structure)
6. [Customization](#-customization)
7. [WordPress Integration](#-wordpress-integration)
8. [Contributing](#-contributing)
9. [License](#-license)
10. [Useful Resources](#-useful-resources)
11. [Read this in Spanish](./README.md)

---

> **Chatbot Demo** is a professional, modern, and portable solution designed to work in any hosting environment, including shared servers that only support PHP. Its decoupled architecture combines React and PHP to deliver a fast, flexible experience that's easy to integrate into existing websites, including WordPress.

## 🛠️ Technologies & Tools

| Frontend         | Backend         | Integration & DevOps   |
|------------------|----------------|-----------------------|
| React            | PHP            | ESLint                |
| TypeScript       | REST Endpoints | PostCSS               |
| Vite             |                | Tailwind CSS          |
| Tailwind CSS     |                | Bash Scripts (.sh)    |
| Lucide Icons     |                | Vitest (testing)      |
| Radix UI         |                | PHPUnit (testing)     |

## 🎯 Project Objective

Provide a portable and decoupled chatbot that can be installed on any web server, even those that only support PHP. It's ideal for WordPress, corporate sites, blogs, and any environment where Node.js is not available.

### 🚀 Key Features

- **100% compatible with shared PHP hosting:** No Node.js or external databases required.
- **Decoupled frontend:** The React client can be easily integrated into any website.
- **Simple PHP API:** Endpoints can be hosted on any traditional hosting.
- **WordPress integration:** Lightweight plugin with no heavy dependencies.
- **Modern, responsive UI:** Adapts to mobile and desktop devices.
- **Multi-language:** Supports Spanish and English.
- **Easy customization:** Change texts, styles, and logic as needed.
- **Automated testing:** Scripts and tools to ensure quality in both frontend and backend.

## 🧪 Testing & Final Validation Flow

This project includes a robust, automated workflow to ensure quality before any deployment:

### Unified Testing & Validation
- **Main script:** `sh scripts/test-all.sh`
  - Runs all React (Vitest) and PHP (PHPUnit) tests automatically.
  - Checks the syntax of all PHP files.
  - Builds and validates the frontend (`dist/index.html`).
  - Stops the process if any error or test fails.

### Flow Execution

#### Minimum Requirements
- Node.js >= 18
- npm >= 9
- PHP >= 7.4
- Composer (for PHPUnit)
- Bash (to run .sh scripts)

1. Install dependencies:
   ```bash
   npm install
   composer global require phpunit/phpunit
   ```
2. Give execution permissions to the scripts:
   ```bash
   chmod +x scripts/*.sh
   ```
3. Run the complete workflow:
   ```bash
   sh scripts/test-all.sh
   ```
4. (Optional) Package for WordPress:
   ```bash
   sh scripts/build-wordpress-plugin.sh
   ```

### Continuous Integration (CI/CD)
- **GitHub Actions:** The workflow `.github/workflows/security-check.yml` runs:
  - Dependency audit (`npm audit`).
  - Linting and build.
  - Automated tests (React and PHP).
  - Blocks deployment if vulnerabilities or errors are found.

### Technical Highlights
- **Decoupled architecture:** Complete separation between frontend (React) and backend (PHP).
- **Unified alias & config:** `@` alias for imports, centralized endpoint and route configuration.
- **Cross-platform scripts:** All scripts work on Linux, macOS, and WSL.
- **WordPress support:** Portable plugin, integration via shortcode, widget, or custom block.

This workflow ensures the project is robust, portable, and easy to maintain in any professional environment.

> **Note:** Warnings related to Fast Refresh (`react-refresh/only-export-components`) can be ignored if they do not affect functionality or user experience. The workflow allows deployment to continue even if non-critical warnings exist.

#### Workflow Customization Example

You can adapt the workflow to add extra steps, such as:

- Run automated React and PHP tests:
  ```yaml
  - name: Run React tests
    run: npm run test

  - name: Run PHP tests
    run: |
      composer global require phpunit/phpunit
      phpunit --configuration api/phpunit.xml
  ```
- Automatically deploy to a server or cloud service.
- Notify via Slack, Discord, or email in case of errors.

#### How to Use the Workflow

The workflow runs automatically on every push or pull request to the main branches (`main`, `develop`). No manual intervention is required. If any check fails (security, lint, build, tests), deployment is blocked and the error is shown in the GitHub Actions interface.

To customize, edit `.github/workflows/security-check.yml` as needed and add steps for your workflow.

## ⚡ Installation & Usage

### 1. Clone the repository

```bash
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo
```

### 2. Install dependencies (frontend development only)

```bash
npm install
```

### 3. Run in development mode

```bash
npm run dev
```

### 4. Build for production

```bash
npm run build
```

### 5. Package for WordPress (optional)

If you need to integrate the chatbot as a WordPress plugin, run:
```bash
sh scripts/build-wordpress-plugin.sh
```
This generates the necessary structure for WordPress, copying the PHP files and the compiled frontend.

### 6. Upload PHP files

Copy the `api/` folder to the server where the backend will be hosted. No additional configuration required.

### 7. WordPress Integration

- Use the included scripts to generate the WordPress plugin.
- The frontend can be inserted as a shortcode, widget, or custom block.
- The PHP backend works as a REST endpoint, without affecting site performance.

#### Shortcode Example

```php
[chatbot_demo]
```

You can also integrate it as a widget or custom block as needed.

## 📁 Project Structure

```
├── api/                  # PHP Endpoints
│   ├── chat.php
│   ├── knowledge-base.php
│   └── ...
├── src/                  # React source code
│   ├── components/
│   ├── pages/
│   └── ...
├── public/               # Static files
├── scripts/              # Build & verification scripts
├── package.json
├── vite.config.ts
├── tailwind.config.ts
└── README.en.md
```

## 🧩 Customization

- Edit texts and logic in `src/components/ChatBot.tsx`.
  ```tsx
  // Example: change the welcome text
  const welcomeText = "Hi! How can I help you today?";
  ```

- Change PHP endpoints as needed.
  ```php
  // Example: add a new endpoint in api/my-endpoint.php
  <?php
  header('Content-Type: application/json');
  echo json_encode(["message" => "Hello from my custom endpoint"]);
  ```

- Customize styles with Tailwind and config files.
  ```tsx
  // Example: change the primary color in tailwind.config.ts
  theme: {
    extend: {
      colors: {
        primary: '#1D4ED8', // Change the main color
      },
    },
  }
  ```

## 🤝 Contributing

Contributions are welcome! You can open issues, submit pull requests, or suggest improvements.

## 📄 License

This project is distributed under the MIT license.

> **Designed for maximum compatibility and easy integration in any PHP environment.**

## 📚 Useful Resources

- [React Documentation](https://react.dev/)
- [TypeScript Documentation](https://www.typescriptlang.org/docs/)
- [Vite Documentation](https://vitejs.dev/guide/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [PHP Documentation](https://www.php.net/docs.php)
- [WordPress Documentation](https://developer.wordpress.org/rest-api/)
- [Vitest](https://vitest.dev/)
- [PHPUnit](https://phpunit.de/)
