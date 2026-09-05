# Admin Panel Settings

A WordPress plugin that controls what each user role can see and do in
wp-admin, plus a set of optional site-level modules (SEO, GDPR, security
hardening, Swish payments, analytics, support tickets) that a site can turn
on only if it actually needs them.

<img src="https://gist.githubusercontent.com/dreadted/216967826e7a59ab91e8a3a16f6fed3f/raw/plugin_version.svg" alt="Plugin Version"> <img src="https://gist.githubusercontent.com/dreadted/216967826e7a59ab91e8a3a16f6fed3f/raw/requires_wp.svg" alt="Requires WP"> <img src="https://gist.githubusercontent.com/dreadted/216967826e7a59ab91e8a3a16f6fed3f/raw/tested_wp.svg" alt="Tested WP"> [![License: GPLv2+](https://img.shields.io/badge/License-GPLv2+-blue.svg?logo=gnu)](https://www.gnu.org/licenses/gpl-2.0.html)

## Author

Christofer Laurin ([@dreadted](https://github.com/dreadted/))

## Why this plugin exists

Most of this started as inline snippets copy-pasted into every new WordPress
theme the author builds — the same "hide this for non-admins", "block
XML-RPC", "add a GDPR export handler" code, rewritten from scratch each
time. This plugin collects that logic in one place, generalized so it works
across sites rather than one specific theme, with each behavior-changing
piece gated behind its own setting so a site only gets what it opts into.

## Features

### Per-role admin panel control

The plugin's original and core feature, under **Settings → Admin Panel
Settings**:

- **General tab** (applies site-wide): minimum password length for all
  users, optionally prevent non-admins from changing their own password,
  hide the Application Passwords section from non-admins, strip clutter
  (comments/new-content links) from the admin bar for non-admins, remove
  the default dashboard widgets (Activity, Quick Draft, etc.), and
  optionally add a "Page Editor" admin menu item pointing at a configurable
  front-end URL.
- **One tab per WordPress user role** (Editor, Author, …): where that role
  lands after login, which admin page it sees by default when opening
  `/wp-admin/`, and — scanned live from WordPress's actual registered admin
  menu, not a hardcoded list — a checklist of exactly which menu items that
  role is allowed to see. A separate toggle can grant a role access to the
  Site Settings pages below without otherwise changing what it can do.
- A **Reset to Defaults** button restores the plugin's original settings.

### Site Settings

A second menu, **Site Settings**, holding several tabs/pages that each save
independently and are only relevant if the site actually uses that feature:

- **SEO** — an all-or-nothing output toggle (off by default, so it never
  fights with Yoast/RankMath/etc. on a site that already has one), then
  document title, meta description, share image, Open Graph locale, and
  theme/background color, all rendered as meta tags plus an
  Organization+Person JSON-LD block. Also lets you restrict WordPress's own
  XML sitemap to a hand-picked list of published pages instead of listing
  everything.
- **Business & Contact / Address / Social Media** — business name and type
  (schema.org), contact person, email, phone, a Swedish
  [Swish](https://www.swish.nu/) payment number, postal address fields, and
  social profile links/handles. These feed the SEO structured data above
  and are reused wherever the site needs the business's own details.
- **Contact Forms** — for sites using FluentForm: a "Default Contact Form"
  picker that opens as a sitewide lightbox whenever a link/button points at
  the `#kontakt` anchor, a toggle that maps FluentForm's own hardcoded
  colors/border-radius/font onto the site's own `theme.json` tokens, and
  invisible, always-on [ALTCHA](https://altcha.org/) proof-of-work spam
  protection on every FluentForm on the site — a self-hosted alternative to
  FluentForm's built-in Cloudflare Turnstile field, with no settings, no
  external account, and no site key tied to a specific domain: the signing
  secret is generated and stored automatically the first time it's needed,
  so it works unchanged across dev/staging/production clones of a site.
  Under its own **GDPR** heading: registers form submissions with
  WordPress's own **Tools → Export/Erase Personal Data** tools (which
  FluentForm does not do on its own), a daily cron that deletes
  submissions past a configurable retention period (a real replacement for
  a FluentForm free-tier setting that the plugin saves but never actually
  enforces) for a chosen subset of forms, and personalized wording on
  WordPress's own privacy-request emails.
- **Hardening** — a handful of always-on, no-downside protections
  (blocking XML-RPC, a generic login error message instead of "unknown
  username", hiding the WordPress version tag, blocking `?username=`
  probing, and removing the `/wp/v2/users` REST endpoint), plus four
  behavior changes that default to *on* but can be switched off per site:
  disabling author archives, redirecting logged-out 404s to the homepage,
  removing jQuery Migrate, and disabling WordPress's generated/responsive
  image sizes.
- **Umami Settings** — configure a site ID for
  [Umami](https://umami.is/) analytics tracking on the front end, plus a
  separate "Analytics" menu that shows the Umami report in an admin iframe
  for whichever roles are allowed to see it.

### Optional integrations

These only do anything if the corresponding plugin is also active —
otherwise they're inert:

- **FluentForm** — Swedish personal identity number (personnummer)
  validation and display formatting for any text field marked with a
  specific CSS class, on top of the Contact Forms handling above.
- **Support Genix Lite** — a "Support Tickets" admin menu (an iframe onto
  the front-end ticket portal), an "Apply Defaults" button on that plugin's
  own settings page to seed its default ticket categories, visibility/edit
  lockdown for non-administrators, and matching the portal's colors to the
  site's own brand colors.

### Small extras

- A styled `console.log` banner on every front-end page load showing the
  site host, active theme, and author — a lightweight footprint/debug aid,
  harmless in production.

## Requirements

- WordPress 5.0 or later (tested up to 6.9)
- PHP 7.0 or later
- FluentForm and/or Support Genix Lite, only if you want the optional
  integrations above — the plugin works fully without them.

## Installation

1. Upload the `amrf-admin-panel-settings` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → Admin Panel Settings** to configure per-role access, and **Settings → Site Settings** for the optional modules above.

## Frequently Asked Questions

### Can I hide menu items per role?

Yes. Each role's tab under **Admin Panel Settings** shows every admin menu
item currently registered on the site (scanned live, so it stays accurate
as other plugins add their own menus) as a checklist — check the ones that
role should see.

### Do I have to configure every Site Settings tab?

No. Each tab/page saves independently and most start in a safe, inert
state (SEO output is off by default, for example) — turn on only what a
given site needs.

### Will this break a site that doesn't use FluentForm or Support Genix Lite?

No. The integrations for those plugins hook onto filters/actions those
plugins define — if the plugin isn't installed, the hook is simply never
triggered and nothing happens.

## Localization

This plugin is fully **translation-ready**. The `.pot` file is in the
`languages` folder, alongside a complete Swedish (`sv_SE`) translation.

## License

This plugin is licensed under the GNU General Public License v2.0 or later.
<https://www.gnu.org/licenses/gpl-2.0.html>

## Changelog

See [changelog.txt](changelog.txt) for details on each release.
