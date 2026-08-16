# 🐞 Bug Reporters

Thanks to the community members who report functional (non-security) bugs and help us catch regressions before they hit more users.

| Contributor | Contribution | Date |
| :--- | :--- | :--- |
| **[spreaderman](https://github.com/spreaderman)** | Reported two installation-blocking regressions in v0.31.10.0: the web installer returning `404 GET install/dbsetup` after the configuration step, and `php spark ci4ms:setup` aborting on the `users.profileIMG` migration due to a `TEXT` column with a default value (rejected by MySQL/MariaDB strict mode). | May 2026 |
| **[SIENSIS](https://github.com/SIENSIS)** | Reported the fresh-install web installer failing silently because `.env` is written mid-request: migrations ran against an empty database name and the encrypter raised a "needs a starter key" error. | Jul 2026 |
| **[SIENSIS](https://github.com/SIENSIS)** | Reported that every login crashed with a 500 when a DNS-level blocker (e.g. Pi-hole) or an outage made the `ip-api.com` geo lookup unreachable — a failed fetch returned `false` and `json_decode(false)` raised an uncaught `TypeError` under `strict_types`. This led to replacing the third-party call with a local, opt-in geo lookup. | Jul 2026 |

> Found a non-security bug? Please [open an issue](https://github.com/ci4-cms-erp/ci4ms/issues) with reproduction steps.
>
> Found a **security** vulnerability instead? See the [Security Policy](SECURITY.md); researchers are credited in the [Security Hall of Fame](SECURITY_HALL_OF_FAME.md).
