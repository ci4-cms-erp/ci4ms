# DevGate — CI4 Development Access Gate

A CodeIgniter 4 module similar to Symfony's `security.yaml`, designed to restrict access to the development environment.
Only users defined in the config can access the site when `ENVIRONMENT=development`.
It has **zero effect** in production — nothing runs.

---

## Installation

### 1. Copy the files
```
Modules/
└── DevGate/
    ├── Config/
    │   ├── DevGate.php
    │   └── Registrar.php
    └── Filters/
        └── DevGateFilter.php
```

### 2. Register the module in CI4 — `app/Config/Autoload.php`
```php
public $psr4 = [
    APP_NAMESPACE   => APPPATH,
    'Config'        => APPPATH . 'Config',
    'Modules'       => ROOTPATH . 'Modules',   // ← add this
];
```

### 3. Check your `.env` file
```ini
CI_ENVIRONMENT = development
```

DevGate activates only when this is set to `development`. Any other value disables it silently.

---

## Defining Users

Edit `Modules/DevGate/Config/DevGate.php`:

### Plain text (quick start)
```php
public array $users = [
    'admin' => 'secret123',
    'bertu' => 'mypass',
];

public bool $useHashedPasswords = false;
```

### Hashed passwords (recommended)
```php
public array $users = [
    'admin' => '$2y$10$...', // password_hash('secret123', PASSWORD_BCRYPT)
    'bertu' => '$2y$10$...',
];

public bool $useHashedPasswords = true;
```

Generate a hash via terminal:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```

---

## Excluding Paths

To allow access to webhooks, health checks, or similar endpoints without authentication:
```php
public array $except = [
    '#^/health#',
    '#^/webhook/#',
    '#^/api/ping#',
];
```

---

## How It Works

1. Every request passes through `DevGateFilter::before()`.
2. If `ENVIRONMENT !== 'development'`, the filter exits immediately — **never runs in production**.
3. In development, it checks the HTTP Basic Auth header.
4. If the user is recognized, the request passes through; otherwise a `401` + `WWW-Authenticate` header is returned.
5. The browser automatically prompts for a username and password.

Thanks to `Registrar.php`, you do not need to manually add anything to `app/Config/Filters.php`.
CI4's module system finds and merges the Registrar file automatically.
