=== Auto Redirect 404s – 301 Redirect Manager & 404 Monitor ===
Contributors: nityasaha
Donate link: https://buymeacoffee.com/nityasaha
Tags: 404 redirect, 301 redirect, redirect manager, redirection, 404 error
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Redirect every 404 to any URL, manage 301 redirects with exact, wildcard and regex rules, and monitor broken links with a built-in 404 log.

== Description ==

**Auto Redirect 404s** is a fast, lightweight redirect plugin that does three jobs most sites need, from one screen:

1. **404 Auto Redirect** – sends every 404 "Page Not Found" error to your homepage or any URL you choose, with an SEO-friendly 301.
2. **Redirection Manager** – create your own 301, 302, 307 and 308 redirects, or mark URLs as 410 Gone, using exact, wildcard or regular expression rules.
3. **404 Monitor** – logs every broken URL with a hit counter and referrer, so you can see what is missing and turn it into a redirect in one click.

Instead of losing a visitor to an error page, they land somewhere useful. Instead of guessing which URLs are broken, the 404 log tells you. And when you move or rename a page, a Redirection Manager rule sends its traffic and link equity to the new address.

If you are clearing "Not found (404)" errors out of Google Search Console, fixing broken links after a site migration, or simply managing redirects without a heavyweight plugin, this does it in minutes.

= Key Features =

**Redirection Manager**

* **301, 302, 307, 308 and 410 Gone** – every redirect type search engines understand
* **Exact, Wildcard and Regex matching** – redirect one URL, a whole folder (`/old-blog/*`), or any pattern you can write as a regular expression
* **Reuse matched text** – insert wildcard and regex captures into the target with `$1`, `$2` …
* **Query string control** – per rule, ignore the query string, pass it through to the target, or match it exactly
* **Hit counter and last-hit date** – see which redirects are still being used
* **Enable, disable, search, sort and bulk edit** your rules
* **CSV import and export** – move redirects between sites, or bulk-load them from a spreadsheet
* **Works for existing pages too** – rules run on every request, not only on 404s
* **Fast** – no database query at all when you have no rules, and a single indexed lookup when you do

**404 Auto Redirect**

* **Redirect 404 to Homepage or Any URL** – send all remaining 404 errors to your homepage, a landing page, or an external site
* **SEO-Friendly 301 Redirects** – permanent redirects that pass link equity, with a 302 option when you need it
* **Quick Page Select** – pick any published page from a dropdown instead of typing a URL
* **Redirect Loop Protection** – checks the destination really exists before sending anyone to it
* **Skip Files & System Paths** – missing images, scripts, fonts and documents keep a real 404
* **Custom Exclusion Patterns** – your own wildcard rules for URLs that should stay a genuine 404
* **Smart Exclusions** – feeds, sitemaps, robots.txt, REST API and cron requests keep their real 404 status

**404 Monitor**

* **404 Error Log** – every broken URL recorded with a hit counter, referrer, and first/last seen timestamps
* **One-click Create Redirect** – turn any logged 404 into a Redirection Manager rule, pre-filled for you
* **CSV Export** – download your full 404 log for analysis in Excel or Google Sheets
* **Top 404 Errors Widget** – your five worst broken links, right on the settings screen
* **Privacy-Friendly** – no IP addresses, no user agents, no personal data stored
* **Self-Limiting Log Table** – hard-capped at 1,000 rows, so a bot scan can never bloat your database

= The Redirection Manager =

Open **Auto Redirects → Redirection Manager** to add a rule. Each rule has:

* **Source URL** – the address visitors arrive at, such as `/old-page`. You can paste a full URL; the domain is removed for you.
* **Match Type**
  * *Exact URL* – matches that one address. Not case sensitive, and a trailing slash makes no difference.
  * *Wildcard* – `*` matches any part of the path. `/old-blog/*` → `/blog/$1` moves a whole section in one rule.
  * *Regular Expression* – full PCRE patterns for anything more complex, such as `^/(\d{4})/(\d{2})/(.+)$` → `/$3`.
* **Target URL** – a path on your site starting with `/`, or a full URL to any site. Pick a page from the dropdown if you prefer.
* **Redirect Type** – 301 or 308 for permanent moves, 302 or 307 for temporary ones, or 410 to tell search engines a page has been removed for good.
* **Query String** – *Ignore* redirects `/page?utm_source=x` the same as `/page`. *Pass to target* does the same but keeps the query string on the destination, so campaign tracking survives. *Exact match* only redirects when the query string in the source matches, in any order.

Rules are checked on every front-end request, before WordPress decides a page is missing. That means a rule wins over the catch-all 404 redirect, and can redirect a page that still exists. Exact rules are tried first, then wildcard and regex rules from oldest to newest.

**Import and export.** Export writes all rules to CSV. Import reads the same columns – `source, target, code, match, query, enabled` – where only the source and target are required. Sources that already have a rule are skipped, never overwritten.

= The 404 Monitor =

Turn on **404 Logging** from the settings screen and the plugin starts recording every broken URL on your site.

Each unique URL is stored once with a hit counter, so a crawler hitting the same missing page ten thousand times creates a single row – not ten thousand. The log is capped at 1,000 entries and trims the least recently seen URLs automatically.

From the **404 Logs** tab you can:

* Sort by hit count to find your most damaging broken links first
* Hover any URL and choose **Create Redirect** to send it somewhere useful – the log entry is cleared once the rule is saved
* Search the log by URL or referrer
* See where each broken link was clicked from
* Delete individual entries, bulk delete, or clear the whole log
* Export everything to CSV

Logging stores the requested path, the referring URL, a hit count and timestamps. **No IP addresses and no user agents are recorded.**

= Exclusions & Safety =

Three controls decide exactly which 404s the catch-all redirect touches. On a new install they are switched on for you. If you are updating from 1.0.x they start switched off, so your site keeps behaving exactly as it did until you choose to enable them.

**Redirect Loop Protection** verifies that your catch-all destination actually exists before sending visitors to it. If it is itself missing, the plugin steps aside and shows the normal 404 page. The check runs on a scheduled background task and whenever you save settings, never during a visitor's request.

**Skip Files & System Paths** keeps a real 404 for missing images, scripts, stylesheets, fonts, documents and archives, and for system paths such as `/wp-json/`, `/wp-content/`, feeds, sitemaps and `robots.txt`.

**Exclusion Patterns** lets you list your own URL patterns, one per line, that should always keep a genuine 404:

`/private/*
/downloads/*.zip
*/preview
/campaign-?`

Use `*` to match any part of a path and `?` to match a single character. Excluded URLs are neither redirected nor logged. Redirection Manager rules are not affected by exclusions – a rule you create always applies.

= Why Fix 404 Errors? =

Unhandled 404 errors quietly cost you:

* **Traffic** – visitors who hit an error page usually leave and do not come back
* **SEO rankings** – crawl budget is wasted on dead URLs, and inbound link equity is lost
* **Conversions** – a potential customer who lands on an error page is a lost sale
* **Credibility** – broken pages make a site look abandoned

A 301 redirect solves all four at once. The 404 Monitor tells you which URLs deserve their own rule, and the Redirection Manager gives it to them.

= Perfect For =

* Fixing 404 errors after a site migration or redesign
* Redirecting old permalinks, renamed pages and moved categories
* Recovering traffic from deleted posts, pages and products
* Cleaning up "Not found (404)" reports in Google Search Console
* Telling search engines a page is gone for good with a 410
* Keeping campaign URLs working after a landing page changes
* SEO audits and ongoing link maintenance

= For Developers =

Four filters let you customise behaviour without touching plugin files:

`// Skip a Redirection Manager rule for specific requests.
add_filter( 'r404c_should_apply_rule', function ( $apply, $match, $current_url ) {
    // $match = array( 'id' => 12, 'target' => 'https://…', 'code' => 301 )
    return $apply;
}, 10, 3 );`

`// Skip the catch-all 404 redirect for specific URLs.
add_filter( 'r404c_should_redirect', function ( $should, $current_url, $target ) {
    if ( false !== strpos( $current_url, '/keep-404/' ) ) {
        return false;
    }
    return $should;
}, 10, 3 );`

`// Skip logging for specific URLs.
add_filter( 'r404c_should_log', function ( $should, $url ) {
    return false === strpos( $url, '/wp-content/' ) ? $should : false;
}, 10, 2 );`

`// Exclude a 404 from both the catch-all redirect and logging.
add_filter( 'r404c_exclude_request', function ( $excluded ) {
    return $excluded;
} );`

Redirects sent by a Redirection Manager rule carry the header `X-Redirect-By: Auto Redirect 404s`, which makes them easy to spot in browser dev tools and crawl reports.

= Support the Developer =

If this plugin saved you time, please consider [buying me a coffee](https://buymeacoffee.com/nityasaha) or leaving a review. Both help enormously.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "Auto Redirect 404s"
4. Click **Install Now** and then **Activate**
5. Open the new **Auto Redirects** menu in your admin sidebar

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `auto-redirect-404s` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Open **Auto Redirects** in your admin sidebar

= Configuration =

1. Go to **Auto Redirects → Settings**
2. Enter your catch-all redirect URL, or pick a page from the **Quick Select** dropdown
3. Choose **301 (permanent)** for SEO, or **302 (temporary)** if the change is short-term
4. Switch on **404 Logging** if you want to record broken links
5. Click **Save Settings**
6. Go to **Auto Redirects → Redirection Manager** to add redirects for specific URLs
7. Test by visiting an old or non-existent URL on your site

The screen is also still reachable from **Settings → Auto 404 Redirects**, where it lived in earlier versions.

== Frequently Asked Questions ==

= What is the difference between the 404 Auto Redirect and the Redirection Manager? =

The Redirection Manager sends *specific* URLs to *specific* destinations – `/old-pricing` to `/pricing`, for example. The 404 Auto Redirect is a safety net: any 404 that no rule catches goes to one destination, such as your homepage. Rules always run first.

= Can I redirect different 404 pages to different URLs? =

Yes. That is exactly what the Redirection Manager is for. Add a rule per URL, or use a wildcard or regex rule to handle a whole group at once. Anything without a rule still falls back to the catch-all destination on the Settings tab.

= Which redirect type should I use? =

Use **301** when a page has moved for good – it is the one Google recommends for passing ranking signals. **308** is the same but keeps the request method, which only matters for forms and APIs. Use **302** or **307** for temporary moves. Use **410 Gone** for content you have removed on purpose and do not want to redirect; search engines drop a 410 faster than a 404.

= How do wildcard redirects work? =

A `*` in the source matches any run of characters, and whatever it matched can be inserted into the target as `$1` (then `$2` for a second `*`, and so on). For example, source `/old-blog/*` and target `/blog/$1` sends `/old-blog/my-post` to `/blog/my-post`.

= Can I use regular expressions? =

Yes. Choose **Regular Expression** as the match type and enter a PCRE pattern, such as `^/product/(\d+)$`. Capture groups become `$1`, `$2` … in the target. Patterns are checked when you save, so a typo is reported instead of breaking your site. Matching is not case sensitive.

= Can I redirect a page that still exists? =

Yes. Rules run on every request, not only on 404s, so a rule replaces a live page. This is handy for pointing an old page at its replacement before you delete it.

= Does the Redirection Manager slow my site down? =

No. With no rules there is no extra database work at all. Exact rules are found with a single indexed lookup, and wildcard and regex rules are cached together in one option. Rules are matched before WordPress loads your theme, and a matched URL is redirected immediately.

= Can I import redirects from another plugin or a spreadsheet? =

Yes. Save them as a CSV with the columns `source, target, code, match, query, enabled` and use **Import redirects from CSV** on the Redirection Manager tab. Only source and target are required. Exporting from this plugin produces the same format, which makes moving redirects between sites easy.

= Can I redirect all 404 errors to my homepage? =

Yes. Leave the catch-all redirect URL set to your site address, or choose **Home Page** from the Quick Select dropdown.

= Will this fix 404 errors in Google Search Console? =

Yes. Adding a redirect is one of Google's recommended ways to resolve "Not found (404)" errors. Once Google recrawls the affected URLs, the errors drop out of the report. Recrawling can take days or weeks.

= Should I redirect every 404 to the homepage? =

It is a good default, and far better than leaving visitors on an error page. Where you can, a more relevant destination is better still – Google treats a mass redirect of unrelated URLs to the homepage as a soft 404. Use the 404 Monitor to find your highest-traffic broken URLs, then give the important ones a proper destination with **Create Redirect**.

= What does the 404 log record? =

The requested URL path, the referring URL, a hit counter, and the first and last time it was seen. **No IP addresses and no user agent strings are stored.**

= How large can the log table get? =

It is hard-capped at 1,000 rows. Once the cap is reached, the least recently seen entries are removed automatically.

= Will updating from an older version change anything? =

No. Your catch-all redirect URL, redirect type, logging state and exclusions are preserved exactly as they are. Version 1.3.0 adds the Redirection Manager with no rules in it, so nothing changes until you add one. The screen moves to its own **Auto Redirects** menu, and the old **Settings → Auto 404 Redirects** link still works.

= What happens if I don't enter a catch-all URL? =

If the redirect URL field is empty, unmatched 404s are not redirected and visitors see your normal 404 page. Redirection Manager rules still work.

= Will this break my sitemap, feeds, or REST API? =

No. Feeds, XML sitemaps, robots.txt, the REST API, XML-RPC and cron keep their real 404 status and are never caught by the catch-all redirect. Redirection Manager rules only apply to the URLs you create them for.

= What happens if my redirect destination is deleted? =

Redirect Loop Protection catches it for the catch-all redirect: while the destination is missing, visitors see the normal 404 page instead of being bounced around, and the settings screen warns you. Redirection Manager rules also refuse to redirect a URL to itself, both when you save the rule and at request time.

= Does the plugin make external requests? =

No. The only request it ever makes is Redirect Loop Protection checking your own site's catch-all destination, on a background schedule. Nothing is sent to any third party.

= Can I use this with other redirect plugins? =

Yes, but running several redirect plugins at once can make it hard to tell which one sent a visitor where. Rules from this plugin are marked with an `X-Redirect-By: Auto Redirect 404s` header to help.

= Does it work on WordPress Multisite? =

Yes. Each site in the network keeps its own settings, redirect rules and 404 log.

= What happens to my data if I delete the plugin? =

Deleting the plugin removes its settings, drops the redirect and log tables, and leaves nothing behind. Deactivating changes nothing, so you can deactivate and reactivate without losing your rules or your logs.

== Screenshots ==

1. Settings – the catch-all 404 redirect and safety options
2. 404 Logs – every broken URL with hits, referrer and one-click Create Redirect
3. Redirection Manager – add exact, wildcard and regex redirects and manage them in one list

== Changelog ==

= 1.3.0 =

**Redirection Manager**

* New: **Redirection Manager** tab for creating your own redirects
* New: 301, 302, 307 and 308 redirects, plus 410 Gone for removed content
* New: exact, wildcard (`*`) and regular expression matching, with `$1`, `$2` … captures in the target
* New: per-rule query string handling – ignore, pass through to the target, or match exactly
* New: hit counter and last-hit date for every rule
* New: enable, disable, edit, delete, search, sort, filter and bulk actions
* New: CSV import and export of redirect rules
* New: **Create Redirect** action on every 404 log entry, which pre-fills the rule and clears the log entry once saved
* New: `r404c_should_apply_rule` filter, and an `X-Redirect-By: Auto Redirect 404s` header on rule redirects
* Performance: rules add no database work when there are none, and a single indexed lookup when there are

**Interface**

* New: top-level **Auto Redirects** admin menu with Settings, Redirection Manager and 404 Logs submenus
* New: page header summarising the status of the 404 Auto Redirect, Redirection Manager and 404 Monitor at a glance
* Changed: the plugin is renamed "Auto Redirect 404s – 301 Redirect Manager & 404 Monitor" to reflect its three features
* Compatibility: **Settings → Auto 404 Redirects** is still in the menu, and old bookmarked URLs forward to the new screen
* Uninstall now also removes the redirects table and its cache

= 1.2.0 =

**404 Error Logging**

* New: optional 404 logging with a dedicated **404 Logs** tab on the settings screen
* New: each unique URL is stored once with a hit counter, referrer, and first/last seen timestamps
* New: sort, search, paginate, delete individual entries, bulk delete, and clear the whole log
* New: CSV export of the full log
* New: "Top 404 Errors" widget on the settings sidebar showing your five worst broken links
* Privacy: no IP addresses and no user agents are recorded
* Safety: the log table is hard-capped at 1,000 rows and trims itself, so a bot scan cannot bloat your database

**Exclusions & Safety**

* New: **Redirect Loop Protection** verifies the destination exists before redirecting, and shows the normal 404 if it does not. The check runs on an hourly background task and on settings save, never during a visitor's request, so it cannot slow the site down
* New: **Skip Files & System Paths** keeps a real 404 for missing images, scripts, stylesheets, fonts, documents, archives and system paths
* New: **Exclusion Patterns** for your own wildcard rules for URLs that should stay a genuine 404, never redirected or logged
* New: toggle to show or hide the "Top 404 Errors" sidebar widget
* Note: on an existing install the two behaviour-changing options above start switched off, so nothing changes until you enable them. New installs get them on

**Fixes and hardening**

* Fixed: the activation routine never actually ran, so default options were never written. Upgrades are now handled on admin load, which is what reaches sites updated through the WordPress updater
* Fixed: removed a "same as your current site URL" confirmation dialog that appeared on every save. It compared hostnames only, so pointing 404s at your own homepage, the recommended setup, always triggered it
* Fixed: redirect loops caused by http/https, trailing-slash and query-string differences between the request and the destination
* Fixed: feeds, sitemaps, robots.txt, REST API and cron requests are no longer redirected and keep their real 404 status
* Fixed: activation and deactivation no longer call wp_cache_flush(), which wiped the entire site object cache for every other plugin
* Security: the redirect URL is now validated against an http/https allowlist, rejecting javascript:, data: and other unsafe schemes
* Security: CSV export escapes formula characters to prevent spreadsheet formula injection
* Security: all log queries use prepared statements, with sorting constrained to a fixed allowlist
* New: uninstall.php removes all options and the log table, with multisite support
* New: three filters for developers — r404c_should_redirect, r404c_should_log and r404c_exclude_request
* Requirement: WordPress 5.0 or newer (was 4.7)
* Requirement: PHP 7.4 or newer (was 7.0)

= 1.0.1 =
* Compatibility updates

= 1.0.0 =
* First stable release. Install to start redirecting 404 errors automatically!

== Upgrade Notice ==

= 1.3.0 =
Adds the Redirection Manager: 301/302/307/308/410 redirects with exact, wildcard and regex matching, CSV import/export, and one-click redirects from the 404 log. The plugin now has its own Auto Redirects menu. Your existing settings and logs are untouched.

= 1.2.0 =
Adds optional 404 error logging, redirect loop protection, file and system path skipping, and custom exclusion patterns. Your existing settings are preserved and the new options start switched off, so nothing changes until you enable them. Now requires WordPress 5.0+ and PHP 7.4+.

== Privacy ==

This plugin does not send any data to any external service.

The only network request it ever makes is Redirect Loop Protection issuing a HEAD request to your own site, to confirm the catch-all redirect destination exists before visitors are sent there. It runs on a scheduled background task, no data leaves your server, and the check can be switched off on the settings screen.

Redirection Manager rules store only what you enter – source, target, redirect type and options – plus a hit counter and the time of the last hit. Nothing about the visitor is recorded.

When 404 logging is enabled, the plugin stores the following in a table in your own database:

* The requested URL path that returned a 404
* The referring URL, if the browser supplied one
* A hit counter and the first/last seen timestamps

**No IP addresses, no user agent strings, and no user account information are recorded.** The log is capped at 1,000 rows and older entries are removed automatically. Logging can be switched off at any time, and deleting the plugin removes all of its tables.

== Support ==

Need help? Have suggestions?

* [Support Forum](https://wordpress.org/support/plugin/auto-redirect-404s/)
* [Buy Me a Coffee](https://buymeacoffee.com/nityasaha) - Support development
