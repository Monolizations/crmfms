# CRM FMS Agent Guidelines

## Build/Lint/Test Commands
- **Setup**: `sudo ./setup.sh` (Apache), `./setup_database.sh` (DB)
- **No build tools**: Pure PHP/JS, no compilation
- **No linting**: Consider adding ESLint/Prettier
- **Testing**: Manual via browser, use `TEST_CREDENTIALS.md` for credentials
- **Single test**: Open test files in browser, check `TEST_CREDENTIALS.md`

## Code Style Guidelines

### PHP
- **Imports**: `require_once __DIR__ . '/../../config/file.php'` at top
- **Error Handling**: Try/catch with `Throwable`, JSON: `['success'=>false, 'message'=>'error']`
- **Database**: PDO prepared statements, snake_case columns, `Database` singleton
- **Security**: `password_verify()`, `sanitize()`, `requireAuth()` for roles
- **Naming**: camelCase vars, PascalCase classes, snake_case DB columns
- **Response**: `['success' => bool, 'message' => string, 'data' => mixed]`

### JavaScript
- **Async**: async/await for fetch, try/catch error handling
- **DOM**: `DOMContentLoaded` listeners, localStorage/sessionStorage
- **API**: fetch with `credentials: 'include'`, JSON headers
- **Naming**: camelCase vars/functions, PascalCase classes
- **Imports**: Global objects (e.g., `window.auth`), API URL constants

### General
- **No TypeScript**: Plain JS, no frameworks
- **UI**: Bootstrap classes, vanilla JS DOM manipulation
- **Security**: Session auth, role-based access, CSRF protection
- **Structure**: `/api/` (backend), `/public/` (frontend), `/config/` (shared)