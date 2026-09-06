# AMRF Site Settings

<img src="https://gist.githubusercontent.com/dreadted/216967826e7a59ab91e8a3a16f6fed3f/raw/plugin_version.svg" alt="Plugin Version"> <img src="https://gist.githubusercontent.com/dreadted/216967826e7a59ab91e8a3a16f6fed3f/raw/requires_wp.svg" alt="Requires WP"> <img src="https://gist.githubusercontent.com/dreadted/216967826e7a59ab91e8a3a16f6fed3f/raw/tested_wp.svg" alt="Tested WP"> [![License: GPLv2+](https://img.shields.io/badge/License-GPLv2+-blue.svg?logo=gnu)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress plugin with general site settings for

- [Basic on-site SEO](#site-settings)
- [Contact Forms settings](#forms)
- [Swish payments functionality](#swish)
- [GDPR tools](#gdpr)
- [Analytics tracking with Umami](#umami-settings)
- [Security hardening](#hardening)
- [Customizing admin panels for user roles](#per-role-admin-panel-control)
- [Optional integrations for Fluent Forms & Support Genix Lite](#optional-integrations)

## Author

Christofer Laurin ([@dreadted](https://github.com/dreadted/)), with prompt assitance from [@claude](https://github.com/claude/).

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

All rendered as **meta tags** plus an
Organization+Person **JSON-LD block**. Also lets you restrict WordPress's own
XML sitemap to a hand-picked list of published pages instead of listing
everything.

These feed the SEO structured data above and are reused wherever the site needs the business's own details:

#### Business & Contact

- Business name and type
  (schema.org)
- Contact person
- Email
- Phone number

#### Address

- Physical address
- Latitude & longitude

#### Social Media

- Facebook URL
- Instagram URL
- X (Twitter) URL

### Forms

#### Contact Forms

- Default Contact Form:
  select one of the pre-existing [Fluent Forms](https://fluentforms.com/) to open sitewide with links pointing to
  the `#kontakt` anchor
- A toggle that overrides Fluent Forms' colors/border-radius/fonts with the site's own `theme.json` tokens
- Enable/disable [ALTCHA](https://altcha.org/) proof-of-work spam
  protection on every Fluent Form on the site — a self-hosted alternative honeypot with no settings, no external account, and no site key tied to a specific domain: the signing secret is generated and stored automatically the first time it's needed, so it works unchanged across dev/staging/production clones of a site.

#### GDPR

- Registers form submissions with WordPress's own **Tools → Export/Erase Personal Data** tools
- A daily cron that deletes submissions past a configurable retention period for a chosen subset of forms

#### Swish

- Generate a [Swish](https://www.swish.nu/) payment link to every link sitewide pointing to the `#swish` anchor:

  #### Mobile devices

  Generates a link to the Swish app, if installed, and otherwise to an app download page.

  #### Other devices

  On devices without the ability to install the app, the link is replaced by a dynamically generated QR code.

  All links can be customized with a pre-filled amount or message.

### Umami Settings

- Configure a site ID for [Umami](https://umami.is/) analytics tracking on the front end
- Adds a separate "Analytics" menu that shows the Umami report in an admin iframe for whichever roles are allowed to see it

### Hardening

A handful of always-on, no-downside protections
(blocking XML-RPC, a generic login error message instead of "unknown username", hiding the WordPress version tag, blocking `?username=` probing, and removing the `/wp/v2/users` REST endpoint), plus four
behavior changes that default to _on_ but can be switched off per site:

- Disable author archives
- Redirect logged-out 404s to the homepage
- Remove jQuery Migrate
- Disable WordPress's generated/responsive image sizes

### Per-role admin panel control

#### General tab

- Minimum password length for all
  users
- Optionally prevent non-admins from changing their own password
- Hide the Application Passwords section from non-admins
- Strip clutter (comments/new-content links) from the admin bar for non-admins
- Remove the default dashboard widgets (Activity, Quick Draft, etc.)
- Optionally add a "Page Editor" admin menu item pointing at a configurable front-end URL

#### One tab per WordPress user role (Editor, Author, …):

- Where that role lands after login
- Which admin page it sees by default when opening
  `/wp-admin/`
- A checklist of exactly which menu items the
  role is allowed to see

### Optional integrations

These only do anything if the corresponding plugin is also active —
otherwise they're inert:

#### Fluent Forms

- Adding Swedish personal identity number (personnummer)
  validation and display formatting for any text field marked with the specific CSS class `ff-personnummer`, on top of the Contact Forms handling above.

#### Support Genix Lite

- Adds a "Support Tickets" admin menu (an iframe onto
  the front-end ticket portal)
- An "Apply Defaults" button on the Support Genix plugin's
  own settings page to seed its default ticket categories, visibility/edit lockdown for non-administrators, and matching the portal's colors to the site's own brand colors.

## Requirements

- [WordPress](https://wordpress.org/) 5.0 or later (tested up to 7.1)
- [PHP](https://www.php.net/) 8.1 or later
- [Fluent Forms](https://wordpress.org/plugins/fluentform/) and/or [Support Genix Lite](https://wordpress.org/plugins/support-genix-lite/), only if you want the optional integrations above — the plugin works fully without them.

## Installation

### Manually

1. Upload the `amrf-site-settings` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → Admin Panel Settings** to configure per-role access, and **Settings → Site Settings** for the optional modules above.

### With wp-cli

```sh
wp plugin install https://github.com/dreadted/amrf-site-settings.git --activate
```

## Frequently Asked Questions

### Can I hide admin panel menu items per role?

Yes. Each role's tab under **Admin Panel Settings** shows every admin menu
item currently registered on the site (scanned live, so it stays accurate
as other plugins add their own menus) as a checklist — check the ones that
role should see.

### Do I have to configure every Site Settings tab?

No. Each tab/page saves independently and most start in a safe, inert
state (SEO output is off by default, for example) — turn on only what a
given site needs.

### Will this break a site that doesn't use Fluent Forms or Support Genix Lite?

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
