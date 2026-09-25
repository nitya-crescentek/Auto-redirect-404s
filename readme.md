# Auto Redirect 404s – 301 Redirect Manager & 404 Monitor

A fast, lightweight WordPress redirect plugin that does three jobs from one screen:

1. **404 Auto Redirect** – sends every 404 "Page Not Found" error to your homepage or any URL, with an SEO-friendly 301.
2. **Redirection Manager** – 301, 302, 307, 308 and 410 rules with exact, wildcard and regex matching, per-rule query string handling, hit counts, and CSV import/export.
3. **404 Monitor** – logs every broken URL with a hit counter and referrer, with a one-click **Create Redirect** action.

Instead of losing a visitor to an error page, they land somewhere useful. Instead of guessing which URLs are broken, the 404 log tells you, and a Redirection Manager rule sends that traffic and link equity where it belongs.

- **Version:** 1.3.0
- **Requires:** WordPress 5.0+, PHP 7.4+
- **Admin:** *Auto Redirects* menu (also linked from *Settings → Auto 404 Redirects*)
- **WordPress.org readme:** [readme.txt](readme.txt)

## How a request is handled

1. **Redirection Manager rules** run on every front-end request (`template_redirect`, priority 0). Exact rules are tried first with one indexed lookup, then wildcard and regex rules, oldest first.
2. If WordPress resolved the request as a **404** and no rule matched, the 404 Monitor logs it (when logging is on).
3. The **catch-all 404 redirect** then sends it to the configured destination, unless it is excluded or loop protection has flagged the destination as missing.

## Code map

| File | Purpose |
| --- | --- |
| `includes/class-redirects.php` | Rule storage, validation, lookup cache and request matching |
| `includes/class-redirects-table.php` | Redirection Manager list table |
| `includes/class-frontend.php` | Applies rules and the catch-all 404 redirect |
| `includes/class-logger.php` | 404 log storage |
| `includes/class-logs-table.php` | 404 log list table |
| `includes/class-admin.php` | Menus, tabs, form handling, CSV import/export |
| `templates/partials/admin-header.php` | Shared page header and tab navigation |

## Filters

- `r404c_should_apply_rule` – skip a matched Redirection Manager rule
- `r404c_should_redirect` – skip the catch-all 404 redirect
- `r404c_should_log` – skip logging a 404
- `r404c_exclude_request` – exclude a 404 from both logging and the catch-all redirect
