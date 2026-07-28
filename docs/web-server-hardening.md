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

location ~* ^/(media|uploads|templates)/.*\.(php[0-9]?|phtml|phar|phps|pht|inc|cgi|pl|py|jsp|asp|aspx|sh|bat|exe|htaccess|htpasswd|user\.ini|ini)$ {
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

See [SECURITY.md](../SECURITY.md) for the responsible-disclosure policy.
