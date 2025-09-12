# CRM FMS Agent Guidelines

## Build/Lint/Test Commands
- **Setup**: Run `./setup.sh` with sudo to configure Apache virtual host
- **Database**: Run `./setup_database.sh` to set up database with all tables and sample data
- **No build tools**: Pure PHP/JavaScript, no compilation required
- **No linting**: No configured linters (add ESLint/Prettier if needed)
- **No testing framework**: Manual testing via `test_login.html` with provided credentials

## Code Style Guidelines

### PHP
- **Imports**: `require_once __DIR__ . '/../../relative/path.php'` at top
- **Error Handling**: Try/catch with `Throwable`, return JSON error responses
- **Database**: PDO with prepared statements, snake_case column names
- **Security**: `password_verify()` for auth, `sanitize()` function for input
- **Naming**: camelCase for variables, PascalCase for classes
- **Response Format**: `['success' => bool, 'message' => string, 'data' => mixed]`

### JavaScript
- **Classes**: ES6 classes with constructor, camelCase methods
- **Async**: Use async/await for API calls, proper error handling
- **DOM**: Event listeners with `DOMContentLoaded`, localStorage/sessionStorage
- **Naming**: camelCase for variables/functions, PascalCase for classes
- **Imports**: No module system, global objects (e.g., `window.auth`)

### General
- **No TypeScript**: Plain JavaScript, no type annotations
- **No frameworks**: Vanilla PHP/JavaScript, Bootstrap for UI
- **Security**: Role-based access control, session management
- **File Structure**: `/api/` for backend, `/public/` for frontend