    # Orthoplex Multi-Tenant SaaS API

This is a comprehensive, multi-tenant SaaS backend API built with Laravel. It features a modular architecture, robust authentication, complete user management, and extensive API documentation. The entire environment is containerized with Docker for easy setup and consistent development.

## ✨ Core Features

*   **Multi-Tenancy**: Database-per-tenant architecture using `stancl/tenancy` for complete data isolation between tenants.
*   **Modular Architecture**: Code is organized into domain-specific modules (e.g., `Auth`, `User`, `Webhooks`) using `nwidart/laravel-modules` for clean separation of concerns and scalability.
*   **Hybrid JWT Authentication**: Secure, stateless authentication using `tymon/jwt-auth` with support for:
    *   Email/Password login.
    *   Two-Factor Authentication (2FA) via Google Authenticator.
    *   Passwordless Magic Link login.
    *   Email verification for new accounts.
*   **Role-Based Access Control (RBAC)**: Granular permission management for both central and tenant users, powered by `spatie/laravel-permission`.
*   **Complete User CRUD**: Full-featured user management API following Service and Repository patterns, including:
    *   Create, Read, Update, Delete (CRUD) operations.
    *   Soft Deletes and User Restoration.
    *   Bulk operations (e.g., bulk delete, bulk status change).
    *   Advanced search and filtering capabilities.
*   **Webhook System**: A simplified and extensible system for notifying external services of events within the application.
*   **API Documentation**: Comprehensive and interactive API documentation powered by OpenAPI (Swagger) and `l5-swagger`.
*   **Dockerized Environment**: A consistent and reproducible development environment managed with Docker and Docker Compose.

---

## 🚀 Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

*   [Docker](https://www.docker.com/get-started)
*   [Docker Compose](https://docs.docker.com/compose/install/)

### 📦 Installation with Docker

1.  **Clone the Repository**

    ```bash
    git clone <your-repository-url>
    cd orthoplex
    ```

2.  **Create Environment File**

    Copy the example environment file. The default settings are configured to work with the Docker setup.

    ```bash
    copy .env.example .env
    ```

3.  **Build and Run Docker Containers**

    Navigate to the `devops` directory and run the containers in detached mode.

    ```bash
    cd devops
    docker-compose up -d --build
    ```

4.  **Install Dependencies**

    Install the PHP dependencies using Composer inside the `app` container.

    ```bash
    docker-compose exec app composer install
    ```

5.  **Generate Application Key**

    ```bash
    docker-compose exec app php artisan key:generate
    ```

6.  **Run Database Migrations**

    Run the migrations for the central (landlord) database.

    ```bash
    docker-compose exec app php artisan migrate --seed
    ```

    This will set up the central tables for users, tenants, and system data. The seeder will create a default admin user and necessary permissions.

7.  **Generate API Documentation**

    Generate the OpenAPI specification file.

    ```bash
    docker-compose exec app php artisan l5-swagger:generate
    ```

### Your environment is now ready! 🎉

---

## 🐳 Available Services

Once the Docker containers are running, the following services will be available:

| Service         | URL                            | Description                                  |
| --------------- | ------------------------------ | -------------------------------------------- |
| **Application**   | `http://localhost:9000`        | The main entry point for the Nginx web server. |
| **API Docs (UI)** | `http://localhost:9000/api/documentation` | **Interactive Swagger UI for API testing.**      |
| **Database**      | `localhost:3307`               | MySQL database connection port.              |
| **phpMyAdmin**    | `http://localhost:8081`        | Web UI for managing the MySQL database.      |
| **Adminer**       | `http://localhost:8080`        | Alternative lightweight database manager.    |
| **MailHog**       | `http://localhost:8025`        | Catches all outgoing emails for testing.     |
| **Redis**         | `localhost:6379`               | In-memory cache and queue broker.            |

---

## 📚 API Documentation

This project uses `l5-swagger` to generate interactive API documentation from OpenAPI annotations in the code.

*   **Interactive UI**: To explore and test the API endpoints, visit:
    [**http://localhost:9000/api/documentation**](http://localhost:9000/api/documentation)

*   **Raw JSON Spec**: The raw OpenAPI JSON file is available at:
    [http://localhost:9000/docs](http://localhost:9000/docs)

If you make changes to the API annotations, you must regenerate the documentation:

```bash
docker-compose exec app php artisan l5-swagger:generate
```

---

## ⚙️ Key API Endpoints

Here are some of the primary endpoints to get you started.

### Authentication

*   `POST /api/auth/register`: Register a new central user.
*   `POST /api/auth/login`: Authenticate and receive a JWT.
*   `POST /api/auth/login/2fa`: Verify a 2FA code after login.

### User Management (Requires Auth)

*   `GET /api/users`: List all users with pagination and filtering.
*   `POST /api/users`: Create a new user.
*   `GET /api/users/{id}`: Retrieve a specific user's details.
*   `PUT /api/users/{id}`: Update a user.
*   `DELETE /api/users/{id}`: Soft delete a user.

> **Note**: For a full list of endpoints, please refer to the [Swagger Documentation](http://localhost:9000/api/documentation).
