/* consoleHeader
-------------------------------------------------------------------
Output a stylized console badge with the site host, theme version, and
author. Ported from amrf-theme's assets/scripts.js — that version fetched
the theme's own style.css and regex-parsed Author/Version/--primary/--dark
out of it; here amrfBranding (localized by Branding\Provider from
wp_get_theme() + apply_filters('amrf_site_colors', [...])) already has
everything needed, so no network request or parsing is required.
*/
const consoleHeader = () => {
  const { author, version, primary, dark } = window.amrfBranding || {};
  const year = new Date().getFullYear();

  console.log(
    `%c ${window.location.hostname} %c v${version || "Unknown"} %c\n© ${year} ${author || "Unknown"}`,
    `background-color: ${primary}80; color: #fff; border-radius: 3px 0 0 3px;`,
    `background-color: ${dark}; color: #fff; border-radius: 0 3px 3px 0;`,
    `background-color: transparent; color: #fff; border-radius: 0 3px 3px 0;`
  );
};

document.addEventListener("DOMContentLoaded", consoleHeader);
