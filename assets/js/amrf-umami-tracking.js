// Loads Umami's tracker for logged-out visitors only.
const addUmamiTracking = () => {
  if (
    !document.body.classList.contains("logged-in") &&
    typeof umamiSite !== "undefined" &&
    umamiSite &&
    typeof umamiScriptUrl !== "undefined" &&
    umamiScriptUrl
  ) {
    let script = document.createElement("script");
    script.src = umamiScriptUrl;
    script.defer = true;
    script.setAttribute("data-website-id", umamiSite);
    document.head.appendChild(script);
  }
};

// Auto-tags button-like elements for Umami's own data-umami-event auto-tracking,
// named after each element's own visible text + the current page title — no
// per-button setup needed in the theme. amrfUmamiButtonOverrides (from the
// amrf_umami_tracked_buttons PHP filter, see Umami\Provider) is checked first
// and wins on a match, letting a theme pin an exact name onto a specific
// element instead. Elements that already carry a manual data-umami-event
// (set directly in markup) are left untouched by both passes.
const trackButtons = () => {
  const pageTitle = typeof amrfUmamiPageTitle !== "undefined" ? amrfUmamiPageTitle : "";

  const tag = (element, name) => {
    if (element.getAttribute("data-umami-event")) return;
    element.setAttribute("data-umami-event", name);
    element.setAttribute("data-umami-event-page", pageTitle);
    if (element.href) element.setAttribute("data-umami-event-url", element.href);
  };

  const overrides = typeof amrfUmamiButtonOverrides !== "undefined" ? amrfUmamiButtonOverrides : [];
  overrides.forEach((button) => {
    document.querySelectorAll(button.selector).forEach((element) => tag(element, button.name));
  });

  const selectors = typeof amrfUmamiButtonSelectors !== "undefined" ? amrfUmamiButtonSelectors : [];
  if (!selectors.length) return;

  let elements;
  try {
    elements = document.querySelectorAll(selectors.join(","));
  } catch (e) {
    // An invalid selector in the admin-configured list shouldn't break tracking.
    return;
  }

  elements.forEach((element) => {
    const label = (element.textContent || element.getAttribute("aria-label") || "")
      .trim()
      .replace(/\s+/g, " ");
    if (label) tag(element, label);
  });
};

// Marks outbound links for umami's data-umami-event auto-tracking. Runs after
// trackButtons() so a button-styled link that's also outbound keeps its
// button name instead of being overwritten by the generic fallback below.
const trackOutboundLinks = () => {
  document.querySelectorAll("a").forEach((a) => {
    if (
      a.host !== window.location.host &&
      a.protocol !== "tel:" &&
      a.protocol !== "mailto:" &&
      !a.getAttribute("data-umami-event")
    ) {
      const name = "outbound-link-click";
      a.setAttribute("data-umami-event", name);
      a.setAttribute("data-umami-event-url", a.href);
    }
  });
};

document.addEventListener("DOMContentLoaded", () => {
  addUmamiTracking();
  trackButtons();
  trackOutboundLinks();
});
