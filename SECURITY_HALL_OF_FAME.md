# 🏆 Security Hall of Fame

A huge thank you to the security researchers who have helped make **ci4ms** more secure by finding and reporting vulnerabilities.

| Contributor | Contribution | Date |
| :--- | :--- | :--- |
| **[Lars van Mil](https://github.com/Far-Horizons)** | Identified Critical RCE and Information Disclosure vulnerabilities. | Jan 2026 |
| **[0xAlchemist](https://github.com/bugmithlegend)** | Identified Critical Stored DOM XSS vulnerabilities across Company Info, Social Media, and Mail Settings modules, and a Session Invalidation flaw, leading to Account Takeover, Privilege Escalation, and potential Platform Compromise. | Feb 2026 |
| **[peeefour](https://github.com/peeefour)** | Identified Stored DOM XSS vulnerabilities leading to Account Takeover. | Feb 2026 |
| **[Hunter.](https://github.com/LAW6ZX7)** | Identified Critical Stored XSS in Backend & Blog modules allowing Session Hijacking. | Feb 2026 |
| **[m1scher](https://github.com/m1scher)** | Assisted with vulnerability triaging and security testing. | Feb 2026 |
| **[alpernae](https://github.com/alpernae)** | Assisted with vulnerability triaging and security testing. | Feb 2026 |
| **[offset](https://github.com/offset)** | Identified Critical vulnerabilities including multiple Stored XSS (Blog & Pages content via broken `html_purify` validation), Authorization Bypass in Fileeditor destructive operations (delete/rename extension allowlist missing), Install Guard Bypass, and CRLF Injection. | Apr – May 2026 |
| **[fg0x0](https://github.com/fg0x0)** | Identified Critical Arbitrary File Write (Zip Slip RCE) vulnerabilities in Theme::upload and Backup::restore modules. | Apr 2026 |
| **[0xAlchemist](https://github.com/bugmithlegend)** , **[peeefour](https://github.com/peeefour)** and **[DexterHK](https://github.com/DexterHK)** | Identified Critical Full Account Takeover and Privilege Escalation via Stored DOM Blind XSS in Backup Management (v2). | Apr 2026 |
| **[dapickle](https://github.com/dapickle)** | Identified Critical Authenticated RCE in Theme installation, Arbitrary Database Table Drop in Theme module, and a Session Management Bypass. | Apr 2026 |
| **[iltosec](https://github.com/iltosec)** | Identified Broken Access Control in Media module, Unsafe Reflection in Dashboard Widgets, RCE via template-function parsing in Pages, and Stored XSS in Pages Cover Image URL leading to Account Takeover (the residual instance of the same Cover Image URL Stored XSS class in Blog Categories was subsequently hardened as well). | Jun 2026 |
| **[skeletonsec](https://github.com/skeletonsec)** | Coordinated disclosure of a delegated-privilege → superadmin → RCE chain (permission-management endpoints missing actor-scope checks, CWE-863), production installs shipping in development mode (debug toolbar, stack traces, login/comment captcha bypass, CWE-489), and unauthenticated self-registration on the backend (CWE-306). The report prompted an independent audit that hardened further access-control gaps in the same class (Backup restore, Methods permission mapping, user-group assignment). | Aug 2026 |

> If you find a security vulnerability, please report it via the [Security Policy](SECURITY.md). Non-security bug reporters are credited separately in [BUG_REPORTERS.md](BUG_REPORTERS.md).
