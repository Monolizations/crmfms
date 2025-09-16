
# Project Overview

This project is a PHP-based Faculty Management System (FMS) with attendance tracking capabilities. It uses a MySQL database and a vanilla JavaScript frontend with Bootstrap for styling. The backend is a RESTful API built with pure PHP.

## Key Technologies

*   **Backend:** PHP
*   **Frontend:** JavaScript, HTML, CSS (Bootstrap)
*   **Database:** MySQL/MariaDB
*   **Web Server:** Apache
*   **Dependency Management:** Composer

## Architecture

The project follows a simple client-server architecture:

*   **`api/`:** Contains the PHP backend code, organized into modules for different functionalities like authentication, attendance, and QR code management.
*   **`public/`:** Contains the frontend assets, including HTML, CSS, and JavaScript files.
*   **`config/`:** Holds configuration files for the database and security settings.
*   **`vendor/`:** Manages PHP dependencies via Composer.

# Building and Running

## 1. Setup Apache

1.  Run the setup script to configure the Apache virtual host:
    ```bash
    sudo ./setup.sh
    ```
2.  This will:
    *   Copy the virtual host configuration.
    *   Enable the site and required Apache modules.
    *   Add `crmfms.local` to your `/etc/hosts` file.
    *   Set file permissions.
    *   Restart Apache.

## 2. Setup the Database

1.  Make sure you have a MySQL/MariaDB server running.
2.  Create a database named `faculty_attendance_system`.
3.  Run the database setup script to create the necessary tables and seed initial data:
    ```bash
    ./setup_database.sh
    ```

## 3. Install Dependencies

1.  Install Composer if you haven't already.
2.  Install the required PHP dependencies:
    ```bash
    composer install
    ```

## 4. Access the Application

You can now access the application in your browser at `http://crmfms.local`.

# Development Conventions

## Code Style

*   **PHP:**
    *   Use `require_once` for imports.
    *   Use try-catch blocks for error handling.
    *   Use PDO prepared statements for database queries.
    *   Follow the naming conventions: `camelCase` for variables, `PascalCase` for classes, and `snake_case` for database columns.
*   **JavaScript:**
    *   Use `async/await` for asynchronous operations.
    *   Use `DOMContentLoaded` to ensure the DOM is loaded before executing scripts.
    *   Use `fetch` for API calls.
    *   Follow the naming conventions: `camelCase` for variables and functions, `PascalCase` for classes.

## Testing

There are no automated tests in this project. Testing is done manually by opening the `test_*.html` files in the browser. The `TEST_CREDENTIALS.md` file contains credentials for testing.

## Linting

There are no linting tools configured for this project.
