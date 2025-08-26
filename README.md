# AMP Access Management Portal (No Frameworks)

Requirements

- PostgreSQL 13+
- PHP 8.2+ with pdo_pgsql enabled

Configure env (PowerShell)

## PowerShell

$env:DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=amp"
$env:DB_USER="postgres"
$env:DB_PASS="postgres"
$env:JWT_SECRET="change_this_secret"

Database

- CREATE DATABASE `amp` in PostgreSQL.
- Apply migrations and seed:

```powershell
php php/scripts/migrate.php
psql -h 127.0.0.1 -U postgres -d amp -f db/seed.sql
```

Run server

```powershell
php -S localhost:8080 -t php/public
```

API endpoints (subset)

- POST /api/auth/login
- POST /api/auth/verify-otp
- POST /api/auth/reset-password
- PUT  /api/auth/update-password
- GET  /api/users
- GET  /api/users/:id
- POST /api/users
- PUT  /api/users/:id
- DELETE /api/users/:id
- GET  /api/requests
- POST /api/requests
- GET  /api/requests/:id
- POST /api/requests/:id/comment

Docker (no local PHP required)

```powershell
docker compose up -d --build
# wait for db to be ready, then run migration inside the container
docker compose exec app php php/scripts/migrate.php
```

Open the app UI:

- <http://localhost:8080/login.html>
- <http://localhost:8080/otp.html>
- <http://localhost:8080/reset.html>
