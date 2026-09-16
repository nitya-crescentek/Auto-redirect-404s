=== Auto Redirect 404 to Custom URL - 404 Redirect & Error Log ===
Contributors: nityasaha
Donate link: https://buymeacoffee.com/nityasaha
Tags: 404 redirect, redirect, 301 redirect, 404 error, seo
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Redirect 404 errors to any URL with SEO-friendly 301 redirects, and log every broken link so you can find and fix 404 errors fast.

== Description ==

**Auto Redirect 404 to Custom URL** is a fast, lightweight 404 redirect plugin that automatically sends every 404 "Page Not Found" error to a custom URL or your homepage — and now logs every broken link so you can see exactly which pages are missing.

Instead of losing a visitor to an error page, the plugin redirects them to a page that works. Instead of guessing which URLs are broken, the built-in 404 error log shows you every missing URL, how many times it was hit, and where the visitor came from.

If you are trying to clear "Not found (404)" errors out of Google Search Console, fix broken links after a site migration, or simply stop losing traffic to dead URLs, this plugin does it in about thirty seconds of setup.

= Key Features =

* **Redirect 404 to Homepage or Any URL** – send all 404 errors to your homepage, a landing page, a category, or an external site
* **404 Error Log** – every broken URL recorded with a hit counter, referrer, and first/last seen timestamps
* **SEO-Friendly 301 Redirects** – permanent redirects that pass link equity, with a 302 temporary option when you need it
* **Fix Google Search Console 404 Errors** – resolve "Not found (404)" and soft 404 reports with proper redirects
* **One-Click Enable / Disable** – toggle redirects and logging independently, without deactivating the plugin
* **Quick Page Select** – pick any published page from a dropdown instead of typing a URL
* **CSV Export** – download your full 404 log for analysis in Excel or Google Sheets
* **Top 404 Errors Widget** – your five worst broken links, right on the settings screen
* **Privacy-Friendly Logging** – no IP addresses, no user agents, no personal data stored
* **Self-Limiting Log Table** – hard-capped at 1,000 rows, so a bot scan can never bloat your database
* **Smart Exclusions** – feeds, sitemaps, robots.txt, REST API and cron requests keep their real 404 status
* **Skip Files & System Paths** – missing images, scripts, fonts and documents keep a real 404 instead of being redirected
* **Custom Exclusion Patterns** – your own wildcard rules for URLs that should stay a genuine 404
* **Redirect Loop Protection** – checks the destination really exists before sending anyone to it
* **Lightweight & Fast** – no bloat, no third-party services, and zero work on pages that are not 404s
* **Works With Any Theme** – runs independently of your theme and page builder

= The 404 Error Log =

Turn on **404 Logging** from the settings screen and the plugin starts recording every broken URL on your site.

Each unique URL is stored once with a hit counter, so a crawler hitting the same missing page ten thousand times creates a single row — not ten thousand. The log is capped at 1,000 entries and trims the least recently seen URLs automatically, so it can never grow out of control.

From the **404 Logs** tab you can:

* Sort by hit count to find your most damaging broken links first
* Search the log by URL or referrer
* See where each broken link was clicked from
* Delete individual entries, bulk delete, or clear the whole log
* Export everything to CSV

Logging stores the requested path, the referring URL, a hit count and timestamps. **No IP addresses and no user agents are recorded**, so there is nothing personally identifying in the log.

= Exclusions & Safety =

Three controls on the settings screen decide exactly which 404s the plugin touches. On a new install they are switched on for you. If you are updating from an earlier version they start switched off, so your site keeps behaving exactly as it did until you choose to enable them.

**Redirect Loop Protection** verifies that your redirect destination actually exists before sending visitors to it. If the destination is itself missing, the plugin steps aside and shows the normal 404 page rather than bouncing the visitor back and forth until the browser gives up.

The check itself runs on a scheduled background task and whenever you save settings, never during a visitor's request, so it can never add a single millisecond to a page load. A destination that has not been checked, or cannot be checked because your host blocks internal requests, is simply treated as fine and your redirects carry on working exactly as before.

**Skip Files & System Paths** keeps a real 404 for missing images, scripts, stylesheets, fonts, documents and archives, and for system paths such as `/wp-json/`, `/wp-content/`, feeds, sitemaps and `robots.txt`. Redirecting a missing image or script to an HTML page confuses browsers and crawlers, so this is switched on for new installs and recommended for everyone else.

**Exclusion Patterns** lets you list your own URL patterns, one per line, that should always keep a genuine 404:

`/private/*
/downloads/*.zip
*/preview
/campaign-?`

Use `*` to match any part of a path and `?` to match a single character. Matching is against the path only, ignoring the domain and query string, and is not case sensitive. Excluded URLs are neither redirected nor logged.

= Why Fix 404 Errors? =

Unhandled 404 errors quietly cost you:

* **Traffic** – visitors who hit an error page usually leave and do not come back
* **SEO rankings** – crawl budget is wasted on dead URLs, and inbound link equity is lost
* **Conversions** – a potential customer who lands on an error page is a lost sale
* **Credibility** – broken pages make a site look abandoned

A 301 redirect solves all four at once, and the 404 log tells you which URLs are worth a dedicated redirect.

= Perfect For =

* Fixing 404 errors after a site migration or redesign
* Recovering traffic from deleted posts, pages, and products
* Cleaning up "Not found (404)" reports in Google Search Console
* Finding broken links pointing at your site from elsewhere
* Catching mistyped URLs in your own navigation and content
* SEO audits and ongoing link maintenance

= For Developers =

Three filters let you customise behaviour without touching plugin files:

`// Skip the redirect for specific URLs.
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

`// Exclude a request from both redirecting and logging.
add_filter( 'r404c_exclude_request', function ( $excluded ) {
    return $excluded;
} );`

= Support the Developer =

If this plugin saved you time, please consider [buying me a coffee](https://buymeacoffee.com/nityasaha) or leaving a review. Both help enormously.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "Auto Redirect 404 to Custom URL"
4. Click **Install Now** and then **Activate**
5. Go to **Settings > Auto 404 Redirects** to configure

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `auto-redirect-404s` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Navigate to **Settings > Auto 404 Redirects** to configure your redirect URL

= Configuration =

1. After activation, go to **Settings > Auto 404 Redirects**
2. Enter your custom redirect URL, or pick a page from the **Quick Select** dropdown
3. Choose **301 (permanent)** for SEO, or **302 (temporary)** if the change is short-term
4. Switch on **404 Logging** if you want to record broken links
5. Optionally add **Exclusion Patterns** for URLs that should stay a real 404
6. Click **Save Settings**
7. Test by visiting a non-existent page on your site

== Frequently Asked Questions ==

= Can I redirect 404 errors to any URL? =

Yes. You can redirect to any valid URL — an internal page, a category archive, a custom landing page, or an external website. Use the Quick Select dropdown to pick a published page, or type any URL you like.

= How do I redirect all 404 errors to my homepage? =

Leave the redirect URL set to your site address, or choose **Home Page** from the Quick Select dropdown. Every 404 will then land on your homepage.

= Does this plugin use 301 redirects? =

Yes. 301 (permanent) is the default and is recommended for SEO, because it passes link equity to the destination. A 302 (temporary) option is available if the redirect is not meant to be permanent.

= Will this fix 404 errors in Google Search Console? =

Yes. Adding a redirect is one of Google's recommended ways to resolve "Not found (404)" errors. Once Google recrawls the affected URLs, the errors drop out of the report. Recrawling can take days or weeks depending on your site.

= Should I redirect every 404 to the homepage? =

It is a good default, and far better than leaving visitors on an error page. Where you can, a more relevant destination is better still — Google treats a mass redirect of unrelated URLs to the homepage as a soft 404. That is exactly what the 404 log is for: find your highest-traffic broken URLs and give the important ones a proper destination.

= What does the 404 log record? =

The requested URL path, the referring URL, a hit counter, and the first and last time it was seen. **No IP addresses and no user agent strings are stored**, so the log contains no personally identifying information.

= Will 404 logging slow down my site? =

No. Logging only runs when a page actually returns a 404 — normal page views do no extra work at all. Because each unique URL is stored once with a counter rather than one row per hit, even a heavy bot scan adds almost nothing.

= How large can the log table get? =

It is hard-capped at 1,000 rows. Once the cap is reached, the least recently seen entries are removed automatically. There is nothing to maintain and no way for it to grow without limit.

= Can I export the 404 log? =

Yes. The **Export CSV** button on the 404 Logs tab downloads the full log, sorted by hit count, ready for Excel or Google Sheets.

= Will updating from an older version change anything? =

No. Your redirect URL, redirect type, and enabled/disabled state are preserved exactly as they are. 404 logging is switched **off** after an update, and no database table is created until you turn it on yourself.

= What happens if I don't enter a custom URL? =

If the redirect URL field is empty, no redirect is performed and visitors see your normal 404 page. To redirect to your homepage, enter your site address or pick **Home Page** from the dropdown.

= Does this affect site performance? =

No. The plugin does nothing at all on pages that load normally. It only acts when WordPress has already determined the request is a 404.

= Will this break my sitemap, feeds, or REST API? =

No. Requests for feeds, XML sitemaps, robots.txt, the REST API, XML-RPC and cron are excluded automatically and keep their real 404 status, which is what search engines and integrations expect.

= What happens if my redirect destination is deleted? =

Redirect Loop Protection catches it. A background task checks that the destination exists, and while it is missing visitors see the normal 404 page instead of being bounced around. The settings screen warns you when the destination is returning a 404, and re-checks it as soon as you save a corrected URL. You can switch the check off under Exclusions & Safety.

= How do I stop certain URLs from being redirected? =

Add them under **Exclusion Patterns** on the settings screen, one per line. Wildcards are supported: `*` matches any part of a path and `?` matches a single character. For example `/private/*` leaves everything under `/private/` as a real 404. Excluded URLs are not logged either.

= Why are missing images and scripts not redirected? =

Because redirecting them causes more problems than it solves. A browser asking for a missing stylesheet expects a 404, not an HTML page. The **Skip Files & System Paths** setting handles this and is on by default; you can switch it off if you really need file requests redirected too.

= Does the plugin make external requests? =

No. The only request it ever makes is Redirect Loop Protection checking your own site's redirect destination, and that runs on a background schedule rather than during anyone's page load. Nothing is sent to any third party.

= Can I redirect different 404 pages to different URLs? =

Not in this version — all 404 errors go to a single destination. The 404 log helps you identify which specific URLs deserve their own rule, which you can add with a dedicated redirect plugin or in your server config.

= Will this work with my theme? =

Yes. The plugin works independently of your theme, page builder, and block editor, and is compatible with any WordPress theme.

= Can I use this with other redirect plugins? =

Yes, but be aware that multiple redirect plugins can conflict. This plugin acts only on requests that WordPress has already resolved as a 404, so it generally runs after other redirect rules have had their chance.

= Does it work on WordPress Multisite? =

Yes. Each site in the network keeps its own settings and its own 404 log.

= What happens to my data if I delete the plugin? =

Deleting the plugin removes its settings and drops the log table, leaving nothing behind. Deactivating it changes nothing, so you can safely deactivate and reactivate without losing your configuration or your logs.

== Screenshots ==

1. The settings screen
2. The 404 Logs screen

== Changelog ==

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

= 1.2.0 =
Adds optional 404 error logging, redirect loop protection, file and system path skipping, and custom exclusion patterns. Your existing settings are preserved and the new options start switched off, so nothing changes until you enable them. Now requires WordPress 5.0+ and PHP 7.4+.

== Privacy ==

This plugin does not send any data to any external service.

The only network request it ever makes is Redirect Loop Protection issuing a HEAD request to your own site, to confirm the redirect destination exists before visitors are sent there. It runs on a scheduled background task, no data leaves your server, and the check can be switched off on the settings screen.

When 404 logging is enabled, the plugin stores the following in a table in your own database:

* The requested URL path that returned a 404
* The referring URL, if the browser supplied one
* A hit counter and the first/last seen timestamps

**No IP addresses, no user agent strings, and no user account information are recorded.** The log is capped at 1,000 rows and older entries are removed automatically. Logging is off by default and can be switched off at any time, and deleting the plugin removes the table entirely.

== Support ==

Need help? Have suggestions?

* [Support Forum](https://wordpress.org/support/plugin/auto-redirect-404s/)
* [Buy Me a Coffee](https://buymeacoffee.com/nityasaha) - Support development
