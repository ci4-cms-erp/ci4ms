# Web Server Hardening

This file documents the production web-server config ci4ms expects you
to apply on top of the shipped `.htaccess` rules. Apache deployments are
covered by the per-directory `.htaccess` files; nginx deployments must
add the equivalent rules to their server config manually.

## Apache

Defense-in-depth `.htaccess` files ship at:

| Path | Purpose |
|------|---------|
| `public/.htaccess` | Front controller rewrite, mod_rewrite rules |
| `public/templates/.htaccess` | Block PHP execution inside uploaded themes |
| `public/media/.htaccess` | Block PHP execution inside user-uploaded media (avatars, attachments, elFinder uploads) |
| `public/uploads/.htaccess` | Block PHP execution inside the public uploads dir |

All four rely on `<FilesMatch>` + `mod_authz_core` and `php_flag engine off`. If you customise them, keep both the `<FilesMatch>` denial AND the `php_flag` (different web-server / PHP-SAPI combos honour different layers).

## nginx

nginx does not honour `.htaccess`. Add the following blocks to the
server section of your site config (or include them from a snippet):

```nginx
# ── Block PHP execution inside user-writable directories ──
# Mirror of public/{media,uploads,templates}/.htaccess.

location ~* ^/(media|uploads|templates)/.*\.(php[0-9]?|phtml|phtm|phar|phps|pht|phpt|inc|cgi|pl|py|jsp|asp|aspx|sh|bat|exe|htaccess|htpasswd|user\.ini|ini|shtml|shtm|stm|hta)(?:\.|$) {
    deny all;
    return 403;
}

# Send everything else under those dirs straight to the filesystem (no PHP fall-through).
location ~ ^/(media|uploads|templates)/ {
    try_files $uri =404;
}

# Honour declared MIME type — never sniff.
add_header X-Content-Type-Options nosniff always;
```

Place the deny-block BEFORE the catch-all `location ~ \.php$ { fastcgi_pass ...; }` so the deny rule matches first.

### Check the PHP location itself

Several local-development stacks (FlyEnv/PhpWebStudy among them) generate a PHP
block that looks like this:

```nginx
location ~ [^/]\.php(/|$) {
    ## try_files $uri =404;          # <-- commented out by the generator
    fastcgi_pass unix:/tmp/php-cgi-84.sock;
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

Two problems: the location matches `.php` anywhere in the path (not just at the
end), and with `try_files` disabled nginx forwards requests for files that do not
exist. Combined with PHP's default `cgi.fix_pathinfo=1`, a request such as
`/media/photo.jpg/x.php` makes PHP walk back and try to execute `photo.jpg`.

What stops it is PHP-FPM's `security.limit_extensions`, which defaults to
`.php .phar` and refuses anything else — a single pool setting outside this
repository. Do not rely on it alone:

* **Uncomment `try_files $uri =404;`** inside the PHP location. This closes the
  whole path-info class on its own.
* Keep `security.limit_extensions = .php` in the FPM pool (drop `.phar`).
* Leave `cgi.fix_pathinfo=0` in `php.ini` unless an application needs it.

### Application-side gate

Server configuration is not the only layer. `Modules\Media\Controllers\Media`
refuses to *write* script extensions into the media tree at all — every
dot-separated segment is checked, so `shell.php.jpg` is rejected as well. That
gate travels with the code and holds regardless of web server, which matters
because the MIME allowlist alone only catches `.php` (see
[developer-handbook.md](developer-handbook.md#media-modulesmedia)).

## Verification

After deploy, test that a renamed payload cannot execute:

```bash
# Apache or nginx — should both return 403 / 404, never 200.
curl -i https://your-site.example/media/avatars/exploit.php
curl -i https://your-site.example/media/avatars/exploit.phar
curl -i https://your-site.example/uploads/.htaccess
```

If any of those return 200 with PHP-evaluated content, the rules are
not active for that directory and the site is vulnerable to upload→RCE
chains even with application-level validation in place.

## Additional production hardening

Beyond the directory-level rules:

* Set `Strict-Transport-Security: max-age=31536000; includeSubDomains` once HTTPS is wired up.
* Set `X-Frame-Options: SAMEORIGIN` (or use the `Content-Security-Policy: frame-ancestors 'self'` directive).
* Set `Referrer-Policy: strict-origin-when-cross-origin`.
* Disable `ServerSignature` / `server_tokens off;`.
* Restrict `writable/`, `app/`, `system/`, `tests/`, `vendor/`, `.env` from web access entirely — they should not be inside the document root, but a `deny from all` / `return 404;` is still good defense.
* Set strict filesystem permissions on `.env` (`chmod 600`, owned by the web user).

## Closing the open `/backend/register` endpoint

**Status:** left open by product decision. `ci4ms-security-reports-2026-08-13.md`
(Finding 3, CWE-306, CVSS 3.7) documents that `/backend/register` accepts
requests from unauthenticated visitors and lets them create an active
account in the backend user table with no email verification. The account
lands in Shield's default group, which ships with zero permissions, so it
cannot reach any `backend/*` screen behind the permission filter — but it is
still an unauthenticated write to the `users`/`auth_identities` tables and a
spam/foothold surface. Nothing in this repository implements either option
below; this section exists so an operator who wants to close it anyway knows
exactly what to change and what each change actually does.

Two independent ways to close it, verified against source. **They solve
different problems — read the comparison before picking one.**

### Option 1 — `Auth.allowRegistration = false` (functional kill-switch)

File: `modules/Auth/Config/Auth.php:172`

```php
public bool $allowRegistration = false;
```

Verified against source: the route resolves to this project's own
`modules/Auth/Controllers/RegisterController.php` (not the vendor one),
because `Routes.php:4` passes `'namespace' => 'Modules\Auth\Controllers'`.
Both `registerView()` (GET, line 62) and `registerAction()` (POST, line 88)
open with `if (! setting('Auth.allowRegistration')) { return
redirect()->back()->withInput()->with('error', lang('Auth.registerDisabled'));
}`. So the real effect is a **302 redirect with a flash error, not a 403 or a
404** — the route still exists and still responds, on both verbs, but no row
is ever written to `users`. The check lives inside the controller, so it
holds regardless of how the controller is reached: this route, a future
route pointing at the same controller, a CLI trigger, anything.

### Option 2 — remove the route (`routes($routes, ['except' => ['register']])`)

File: `modules/Auth/Config/Routes.php:4`

```php
service('auth')->routes($routes, ['namespace' => 'Modules\Auth\Controllers', 'except' => ['register']]);
```

Verified against `vendor/codeigniter4/shield/src/Auth.php:134-166` and
`vendor/codeigniter4/shield/src/Config/AuthRoutes.php:20-33`: Shield's route
table is keyed by group name, and `register` is **one key covering both
rows** — the GET `register` route and the POST `registerAction` route.
`except => ['register']` drops both in the same pass; `login`, `logout`,
`magic-link`, and `auth-actions` sit under different keys and are untouched.
A request to `/backend/register` then hits no route at all — a plain 404,
before the controller (and therefore before the `allowRegistration` check)
ever runs.

This is **route-table-only**: it does not touch `Auth.php:172`, so
`allowRegistration` stays `true`. If any later change adds another route
pointing at `RegisterController::registerView`/`registerAction`, or calls
those methods directly (a CLI command, another controller), registration
works again on that new path — the removed route is the only thing blocked.
As of this writing no other route in the repo targets `RegisterController`
(only the class declaration and the two Shield-managed rows reference it).

### Comparison

| | Option 1 — `allowRegistration = false` | Option 2 — `except => ['register']` |
|---|---|---|
| Enforced where | Inside the controller | Route table only |
| `/backend/register` response | 302 + flash error, both verbs | 404, both verbs |
| Survives a new route added later that targets `RegisterController` | Yes | No — bypasses this control entirely |
| Depends on | Shield's internal check staying in `RegisterController` (vendor behavior) | This project's own `Routes.php` (fully under project control) |
| Use when | Registration must stay off everywhere, permanently | `/backend/register` must 404 specifically, and `allowRegistration` will be kept in sync manually |

Applying both is belt-and-suspenders: the route 404s directly, and even if a
route to the controller reappears later, `allowRegistration = false` still
blocks it.

See [SECURITY.md](../SECURITY.md) for the responsible-disclosure policy.
