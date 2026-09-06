// Prints a stylized console badge with the site host, theme version, and
// author, using amrfBranding (localized by Branding\Provider).
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
