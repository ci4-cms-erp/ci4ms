# CI4MS — Docker Test Environment Setup Guide

## 🔧 Requirements

| Tool                | Minimum Version  |
|---------------------|-----------------|
| Docker              | 20.x+           |
| Docker Compose      | v2+              |
| Git                 | 2.x+             |
| Composer            | 2.x+             |

---

## 🚀 Installation (4 Steps)

### 1. Clone the Project and Install Dependencies

```bash
git clone <repo-url> ci4ms
cd ci4ms
composer install
```

### 2. Start the Docker Containers

```bash
docker compose up -d --build
```

This command creates **3 services**:

| Service        | Container          | Access URL              | Description            |
|----------------|--------------------|-------------------------|------------------------|
| **app**        | `ci4ms_app`        | `http://ci4ms.loc`      | PHP 8.2 + Apache       |
| **db**         | `ci4ms_db`         | `localhost:3307`        | MariaDB 12.0.2         |
| **phpmyadmin** | `ci4ms_phpmyadmin` | `http://localhost:8081` | phpMyAdmin Interface   |

### 3. Verify the Database is Ready

```bash
docker compose ps
```

The `ci4ms_db` service should be in **`healthy`** state. For detailed logs:

```bash
docker compose logs -f db
```

You can proceed once you see the `ready for connections` message.

### 4. Launch the Installation Wizard

First, add `127.0.0.1 ci4ms.loc` to your `/etc/hosts` file.

Open **`http://ci4ms.loc`** in your browser. The CI4MS installation wizard will appear automatically.

Fill in the following information:

#### Site Information

| Field      | Value                             |
|------------|-----------------------------------|
| Base URL   | `http://ci4ms.loc/`               |
| Site Name  | *(your preferred name)*           |

#### Database Information

| Field      | Value               |
|------------|---------------------|
| Hostname   | `db`                |
| Database   | `ci4ms_test`        |
| Username   | `ci4ms_user`        |
| Password   | `ci4ms_pass`        |
| Port       | `3306`              |
| Prefix     | `ci4ms_`            |
| Driver     | `MySQLi`            |

> **⚠️ Hostname must be `db`**, not `localhost`. Containers communicate with each other using service names within the Docker internal network.

#### Administrator Account

| Field       | Value                         |
|-------------|-------------------------------|
| First Name  | *(your first name)*           |
| Last Name   | *(your last name)*            |
| Username    | *(min 3 characters)*          |
| Email       | *(valid email address)*       |
| Password    | *(min 8 characters)*          |

**After submitting the form**, the wizard will automatically:
1. Create and configure the `.env` file
2. Generate an encryption key
3. Run all migrations (table creation)
4. Insert default data (settings, languages, permission groups)
5. Create the superadmin account
6. Regenerate the routes file
7. Create required directories (`writable/backups`, `public/media/.tmb`, `public/media/.trash`)

Once installation is complete, you will be redirected to the **homepage**.

---

## 🛠️ Useful Commands

### Container Management

```bash
# Start
docker compose up -d

# Stop
docker compose down

# Rebuild (after Dockerfile changes)
docker compose up -d --build

# Watch logs
docker compose logs -f app
docker compose logs -f db
```

### Spark Commands Inside the Container

```bash
# Connect to the container
docker exec -it ci4ms_app bash

# Clear cache
php spark cache:clear

# Run migrations
php spark migrate

# Clear logs
php spark log:clear
```

### Full Database Reset

To start the installation from scratch:

```bash
# Stop containers and delete the database volume
docker compose down -v

# Remove .env and reset Routes file (the install module will recreate them)
rm -f .env
rm -f app/Config/Routes.php
cp app/Config/DefaultRoutes.php app/Config/Routes.php

# Restart
docker compose up -d --build
```

Then go to `http://ci4ms.loc` and run the installation again.

### phpMyAdmin

```
http://localhost:8081
```

| Field    | Value          |
|----------|----------------|
| Username | `root`         |
| Password | `ci4ms_secret` |

---

## ⚙️ Configuration Details

### Docker Services

```
Host Machine                       Docker Network (ci4ms_network)
────────────                       ──────────────────────────────
localhost:80    ──────────────►     ci4ms_app:80     (Apache + PHP 8.2)
localhost:3307  ──────────────►     ci4ms_db:3306    (MariaDB 12.0.2)
localhost:8081  ──────────────►     ci4ms_phpmyadmin:80 (phpMyAdmin)
```

### PHP Settings (`.docker/php/php.ini`)

| Setting               | Value             |
|-----------------------|-------------------|
| `upload_max_filesize` | 64M               |
| `post_max_size`       | 64M               |
| `memory_limit`        | 256M              |
| `max_execution_time`  | 120               |
| `display_errors`      | On                |
| `date.timezone`       | Europe/Istanbul   |

### MariaDB Settings

| Setting          | Value                    |
|------------------|--------------------------|
| Version          | 12.0.2                   |
| Storage Engine   | InnoDB                   |
| Charset          | `utf8mb4`                |
| Collation        | `utf8mb4_unicode_ci`     |
| Buffer Pool      | 256MB                    |

---

## 🐛 Known Issues and Solutions

### `Connection refused` — Database connection error

**Cause:** The DB container is not ready yet or the hostname is incorrect.

**Solution:**
- Verify the `ci4ms_db` service is `healthy` by running `docker compose ps`
- Make sure you entered **`db`** in the hostname field (not `localhost`)
- Enter **`3306`** for the port (`3307` is the port exposed to the host machine)

### SMTP `Invalid HELO name` error

**Cause:** The Docker container name (`ci4ms_app`) is not accepted as a valid FQDN by the remote SMTP server.

**Solution:** Disable Email 2FA and EmailActivator in the development environment:

```php
// modules/Auth/Config/Auth.php
public array $actions = [
    'login'    => null,
    'register' => null,
];
```

### Port 80 is already in use by another application

Change the app port in `docker-compose.yml`:

```yaml
ports:
  - "80:80"  # Use 8080 instead of 80
```

Then enter `http://ci4ms.loc/` as the Base URL in the installation form.

### AirPlay port conflict on macOS

Go to **System Settings → General → AirDrop & Handoff** and disable the **AirPlay Receiver** option.

---

## 📁 Project Structure

```
ci4ms/
├── .docker/
│   ├── Dockerfile                 # PHP 8.2 + Apache image
│   ├── apache/000-default.conf    # VirtualHost configuration
│   └── php/php.ini                # PHP settings
├── docker-compose.yml             # Service definitions
├── modules/
│   └── Install/                   # Installation wizard module
│       ├── Controllers/Install.php
│       ├── Services/InstallService.php
│       └── Views/install.php
└── ...
```

---

## 🔄 Development Workflow

1. **Make code changes** — Reflected instantly in the container via volume mount
2. **After module menu changes** — Admin Panel → Modules → "Module Scan"
3. **For cache issues** — `docker exec -it ci4ms_app php spark cache:clear`
4. **For a clean start** — `docker compose down -v` → `rm .env` → `cp app/Config/DefaultRoutes.php app/Config/Routes.php` → `docker compose up -d --build`
