# Wisp Example Application

Example Symfony 8 API application using the Wisp bundle.

## Demo Credentials

```
User token:  user-token-123  (ROLE_USER)
Admin token: admin-token-456 (ROLE_USER, ROLE_ADMIN)
```

## Available Endpoints

| Method | Path             | Auth     | Description          |
|--------|------------------|----------|----------------------|
| GET    | /api/health      | None     | Health check         |
| GET    | /api/users       | ROLE_USER| List users           |
| POST   | /api/users       | None     | Create user (throttled) |
| GET    | /api/users/{id}  | ROLE_USER| Show user            |
| GET    | /api/admin/users | ROLE_ADMIN| Admin user list     |

## Running Tests

```bash
php bin/phpunit
```

## Start Dev Server

```bash
symfony server:start
# or
php -S localhost:8000 -t public
```
