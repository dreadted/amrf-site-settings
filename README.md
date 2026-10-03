# AMRF Site Settings

<!-- badges:start -->
[![Plugin Version](https://img.shields.io/badge/Plugin_Version-0.3.3-3d444d?style=for-the-badge&labelColor=1f2328)](https://github.com/dreadted/amrf-site-settings/releases/latest)
[![Requires WP](https://img.shields.io/badge/Requires_WP-6.6-3d444d?style=for-the-badge&labelColor=1f2328&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Tested WP](https://img.shields.io/badge/Tested_WP-7.1-3d444d?style=for-the-badge&labelColor=1f2328&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-3d444d?style=for-the-badge&labelColor=1f2328&logo=gnu&logoColor=white)](https://www.gnu.org/licenses/gpl-2.0.html)
<!-- badges:end -->

A WordPress plugin with general site settings for

- [Basic on-site SEO](#site-settings)
- [Contact Forms settings](#forms)
- [Swish payments functionality](#swish)
- [GDPR tools](#gdpr)
- [Analytics tracking with Umami](#umami-settings)
- [Security hardening](#hardening)
- [Customizing admin panels for user roles](#per-role-admin-panel-control)
- [Optional integrations for Fluent Forms, FluentCRM & Support Genix Lite](#optional-integrations)

## Author

Christofer Laurin ([@dreadted](https://github.com/dreadted/)), with prompt assistance from [@claude](https://github.com/claude/).

## Why this plugin exists

Most of this started as inline snippets copy-pasted into every new WordPress theme — the same "hide this for non-admins", "block
XML-RPC", etc. This plugin collects that logic in one place, generalized so it works across sites rather than one specific theme.

## Features

This plugin adds an admin panel menu item named **"Site Settings"** with the following features:

### Site Settings

#### SEO

- An all-or-nothing output toggle (off by default, so it never fights with dedicated SEO plugins)

- Document title
- Meta description
- Share image
- Open Graph locale
- Theme/background color

All rendered as **meta tags**, plus an
Organization+Person **JSON-LD block**. Also lets you restrict WordPress's own
XML sitemap to a hand-picked list of published pages instead of listing
everything.

These feed the SEO structured data above and are reused wherever the site needs the business's own details:

#### Business & Contact

- Business name and type (schema.org)
- Contact person
- Email
- Phone number
- **Contact links:** `[amrf_email_link]` prints the email as a link whose address is assembled in the browser (ROT13 in the HTML, so scrapers don't see it), and `[amrf_phone_link]` prints a `tel:` link that becomes a copy button on desktop. A theme can reuse the same behavior for its own markup by calling `amrf_enqueue_contact_links()` and using these classes:

  ```html
  <a href="#" class="amrf-email-link" hidden data-user="<ROT13 user>" data-domain="<ROT13 domain>">
    <span class="amrf-email-link-text">Email</span>
  </a>
  <a href="tel:+46…" class="amrf-copy-link" data-copy-value="070-…">
    <span class="amrf-copy-text">070-…</span>
  </a>
  ```

#### Address

- Physical address
- Latitude & longitude

#### Social Media

- Facebook URL
- Instagram URL
- X (Twitter) URL

#### Icons & logo

Favicon tags, `/site.webmanifest`, the root `/favicon.ico` and `/apple-touch-icon.png`, the login page logo and the Support Genix portal's favicon and logo all come from the `amrf_brand_images` filter. The plugin ships no images of its own: an icon the theme leaves out is not output, and the login logo falls back to the WordPress Site Icon.

```php
add_filter('amrf_brand_images', function (): array {
  return [
    'icon_svg' => get_theme_file_uri('assets/images/favicon.svg'),
    'icon_ico' => get_theme_file_uri('assets/images/favicon.ico'),
    'icon_192' => get_theme_file_uri('assets/images/icon-192.png'),
    'icon_512' => get_theme_file_uri('assets/images/favicon.png'),
    'apple_touch_icon' => get_theme_file_uri('assets/images/apple-touch-icon.png'),
    'logo' => get_theme_file_uri('assets/images/logo.svg'),
  ];
});
```

The `/favicon.ico` and `/apple-touch-icon.png` rewrites are written to `.htaccess` when rewrite rules are flushed, so re-save **Settings → Permalinks** after changing those two. The portal's favicon and logo are copied into Support Genix's own settings when its "Apply Defaults" button is used.

### Forms

#### Contact Forms

- Default Contact Form: Select one of the pre-existing [Fluent Forms](https://fluentforms.com/) to open sitewide with links pointing to the `#kontakt` anchor
- A toggle that overrides Fluent Forms' colors/border-radius/fonts with the site's own `theme.json` tokens
- Enable/disable [ALTCHA](https://altcha.org/) proof-of-work spam protection on every Fluent Form on the site — a self-hosted alternative honeypot with no settings, no external account, and no site key tied to a specific domain: the signing secret is generated and stored automatically the first time it's needed, so it works unchanged across dev/staging/production clones of a site.

#### GDPR

- Registers form submissions with WordPress's own **Tools → Export/Erase Personal Data** tools
- A daily cron that deletes submissions past a configurable retention period for a chosen subset of forms

#### Swish

Generate a [Swish](https://www.swish.nu/) payment link to every link sitewide pointing to the `#swish` anchor:

- **Mobile devices**: Generate a link to the Swish app, if installed, and otherwise to an app download page.

- **Other devices**: On devices lacking the ability to install the app, the link is replaced by a dynamically generated QR code.

All links can be customized with a pre-filled amount or message.

### Umami Settings

- Configure a site ID for [Umami](https://umami.is/) analytics tracking on the front end, and pick which Umami server (self-hosted or cloud) it's tracked/viewed on
- The tracker and its helper script load only for logged-out visitors when a site ID is set; those pages also get a `preconnect` to the selected Umami server
- FluentCRM's public pages (unsubscribe, manage subscription, double opt-in confirmation, view in browser) are never tracked, since their URLs carry the contact's personal key. Other code can skip tracking for a request via the `amrf_umami_track_request` filter (return `false`)
- Adds a separate "Analytics" menu that shows the Umami report in an admin iframe for whichever roles are allowed to see it
- **Automatic button tracking:** elements matching the "Button Selectors" field (one CSS selector per line, defaults to the theme's `btn--*` classes, `.cta`, `.wp-element-button`, and FluentForm's `.ff-btn-submit`) are tracked automatically, named after their own visible text plus the current page title — no theme code required. Leaving the field empty reverts to the built-in defaults rather than disabling tracking.
- **Naming a specific button explicitly:** a theme (or another plugin) can override the auto-generated name for one exact element via the `amrf_umami_tracked_buttons` filter, checked before the generic sweep above:

  ```php
  add_filter('amrf_umami_tracked_buttons', function (array $buttons): array {
    $buttons[] = [
      'selector' => '#contact .ff-btn-submit',
      'name' => 'contact-form',
    ];

    return $buttons;
  });
  ```

### Hardening

A handful of always-on, no-downside protections
(blocking XML-RPC, a generic login error message instead of "unknown username", hiding the WordPress version tag, blocking `?username=` probing, removing the `/wp/v2/users` REST endpoint, and disabling WordPress's emoji fallback, which loads images from `s.w.org`), plus four
behavior changes that default to _on_ but can be switched off per site:

- Disable author archives
- Redirect logged-out 404s to the homepage
- Remove jQuery Migrate
- Disable WordPress's generated/responsive image sizes

### Per-role admin panel control

#### General tab

- Minimum password length for all users
- Optionally prevent non-admins from changing their own password
- Hide the Application Passwords section from non-admins
- Strip clutter (comments/new-content links) from the admin bar for non-admins
- Remove the default dashboard widgets (Activity, Quick Draft, etc.)
- Optionally add a "Page Editor" admin menu item pointing at a configurable front-end URL

#### One tab per WordPress user role (Editor, Author, …)

- Where that role lands after login
- Which admin page it sees by default when opening `/wp-admin/`
- A checklist of exactly which menu items the role is allowed to see

For non-administrators, FluentCRM and Fluent Forms are moved from the top of
the admin menu to right after Pages.

### Optional integrations

These only do anything if the corresponding plugin is also active —
otherwise they're inert:

#### Fluent Forms

- Adding Swedish personal identity number (personnummer) validation and display formatting for any text field marked with the specific CSS class `ff-personnummer`, on top of the Contact Forms handling above.

#### FluentCRM

- Blocks FluentCRM's visitor identification cookies (`fc_hash_secure`, `fc_cid`), including the ones its unsubscribe, confirmation and manage-subscription pages set regardless of its own `fluent_crm/will_use_cookie` filter.
- Gives the Editor role FluentCRM access in code — contacts, lists, tags, campaigns and email templates, but not automations, forms, settings or exports — so it survives a fresh database without FluentCRM's per-user Managers setting.
- Hides FluentCRM's in-app top bar (navigation, search, "Upgrade to Pro"), Pro upsell cards (dashboard, campaign link activity), the campaign recipient step's "Excluded contacts" section and the dashboard's "Getting started" checklist, "Active automations" card and quick links to pages they can't open for non-administrators; they navigate via the WordPress admin menu instead.

#### Support Genix Lite

- Adds a "Support Tickets" admin menu (an iframe onto the front-end ticket portal)
- An "Apply Defaults" button on the Support Genix plugin's own settings page to seed its default ticket categories, visibility/edit lockdown for non-administrators, and matching the portal's colors to the site's own brand colors.

## Requirements

- [WordPress](https://wordpress.org/) 6.6 or later
- [PHP](https://www.php.net/) 8.3 or later
- [Fluent Forms](https://wordpress.org/plugins/fluentform/), [FluentCRM](https://wordpress.org/plugins/fluent-crm/) and/or [Support Genix Lite](https://wordpress.org/plugins/support-genix-lite/), only if you want the optional integrations above — the plugin works fully without them.

## Installation

### Manually

1. Upload the `amrf-site-settings` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → Admin Panel Settings** to configure per-role access, and **Settings → Site Settings** for the optional modules above.

### With wp-cli

```sh
wp plugin install https://github.com/dreadted/amrf-site-settings/releases/latest/download/amrf-site-settings.zip --activate
```

## Frequently Asked Questions

### Can I hide admin panel menu items per role?

Yes. Each role's tab under **Admin Panel Settings** shows every admin menu
item currently registered on the site (scanned live, so it stays accurate
as other plugins add their own menus) as a checklist — check the ones that
role should see. Submenu items are listed individually, including those of
plugins such as FluentCRM or Fluent Forms, so a role can get only part of a
plugin's menu.

A checked item is an upper limit, not a grant: it only shows up if the
plugin itself also lets that role see it. For plugins whose submenu items
are in-app routes (`page#/…`, e.g. FluentCRM), unchecking only hides the
link — the plugin's own permissions decide what the role can open.
Menu items tied to an access toggle on the same tab (Site Settings Access,
Form Entries Access) follow that toggle and aren't listed as checkboxes.

### Do I have to configure every Site Settings tab?

No. Each tab/page saves independently and most start in a safe, inert
state (SEO output is off by default, for example) — turn on only what a
given site needs.

### Will this break a site that doesn't use Fluent Forms, FluentCRM or Support Genix Lite?

No. The integrations for those plugins hook onto filters/actions those
plugins define — if the plugin isn't installed, the hook is simply never
triggered and nothing happens.

## Localization

This plugin is fully **translation-ready**. The `.pot` file is in the
`languages` folder, alongside a complete Swedish (`sv_SE`) translation.

It also bundles its own Swedish translations for **Fluent Forms** (complete)
and **FluentCRM** (the visitor-facing pages plus the dashboard, contacts,
lists, tags, campaigns, email templates and the email editor). They're loaded
straight from this plugin's `languages` folder and take precedence over any
wordpress.org language pack — no files need to be copied into
`wp-content/languages`. That includes script translations FluentCRM's email
editor never loads on its own, and labels Fluent Forms' entries page leaves
out of its translation map.

Some texts in both plugins are hard-coded in their JavaScript (for example
FluentCRM's email editor sidebar and style panel, and Fluent Forms'
pagination and relative times) and stay in English.

### Updating the plugin's own translations

After adding, changing or removing strings, run from the plugin folder:

```sh
wp i18n make-pot . languages/amrf-admin.pot --domain=amrf-admin
wp i18n update-po languages/amrf-admin.pot languages/amrf-admin-sv_SE.po
# translate the new entries in the .po file, then:
wp i18n make-mo languages/amrf-admin-sv_SE.po
wp i18n make-json languages/amrf-admin-sv_SE.po --no-purge
```

`make-json` builds the `.json` files that JavaScript strings are translated
from. `--no-purge` keeps those strings in the `.po` file, so the next
`update-po` doesn't drop their translations.

## Releases

Releases are cut from `main` with one command, which needs the [GitHub CLI](https://cli.github.com/), logged in:

```sh
bin/release.sh            # patch
bin/release.sh minor      # or major, or an explicit version such as 0.4.0
```

The script bumps the version in `amrf-site-settings.php` and `readme.txt`, adds the commit subjects since the previous tag to [`changelog.txt`](changelog.txt), refreshes the badges above, commits and tags `vX.Y.Z`, and builds `amrf-site-settings.zip` with `git archive` (files marked `export-ignore` in `.gitattributes` are left out). After you confirm, it pushes the commit and tag and publishes a GitHub Release with the zip, which the wp-cli install command above always points to. Answering no removes the commit and tag again.

_Requires at least_ and _Requires PHP_ are kept in the plugin header and copied to `readme.txt` on release; _Tested up to_ is kept in `readme.txt`. Don't change the version by hand.

## License

This plugin is licensed under the GNU General Public License v2.0 or later.
<https://www.gnu.org/licenses/gpl-2.0.html>

## Changelog

See [changelog.txt](changelog.txt) for details on each release.
