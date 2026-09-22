// Wires matched elements to umami.track() via a real click listener, not
// Umami's own data-umami-event auto-tracking — for an <a>, that auto-tracking
// calls preventDefault() and manually navigates to its href once the tracking
// call resolves, which would hijack any click a theme script already
// intercepts (a modal trigger, an in-page anchor, etc.). Named after each
// element's own visible text + the current page title — no per-button setup
// needed in the theme. amrfUmamiButtonOverrides (from the
// amrf_umami_tracked_buttons PHP filter, see Umami\Provider) is checked first
// and wins on a match, letting a theme pin an exact name onto a specific
// element. Elements that already carry a manual data-umami-event (set
// directly in markup) are left untouched by both passes, for Umami's own
// auto-tracking to handle instead.
const trackButtons = () => {
  const pageTitle = typeof amrfUmamiPageTitle !== "undefined" ? amrfUmamiPageTitle : "";

  const trackClick = (element, name) => {
    if (element.getAttribute("data-umami-event") || element.dataset.amrfTracked) return;
    element.dataset.amrfTracked = "1";
    element.addEventListener("click", () => {
      if (typeof umami === "undefined" || !umami) return;
      umami.track(name, { page: pageTitle, url: element.href || undefined });
    });
  };

  const overrides = typeof amrfUmamiButtonOverrides !== "undefined" ? amrfUmamiButtonOverrides : [];
  overrides.forEach((button) => {
    document.querySelectorAll(button.selector).forEach((element) => trackClick(element, button.name));
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
    if (label) trackClick(element, label);
  });
};

// Marks outbound links for umami's own data-umami-event auto-tracking — safe
// here since these clicks really do leave the site, so Umami's wait-then-
// navigate behavior only confirms a navigation already under way, never
// hijacks one a script meant to intercept. Skips elements trackButtons()
// already wired up (data-amrf-tracked) so a button-styled outbound link keeps
// its own event name instead of firing twice.
const trackOutboundLinks = () => {
  document.querySelectorAll("a").forEach((a) => {
    if (
      a.host !== window.location.host &&
      a.protocol !== "tel:" &&
      a.protocol !== "mailto:" &&
      !a.getAttribute("data-umami-event") &&
      !a.dataset.amrfTracked
    ) {
      const name = "outbound-link-click";
      a.setAttribute("data-umami-event", name);
      a.setAttribute("data-umami-event-url", a.href);
    }
  });
};

document.addEventListener("DOMContentLoaded", () => {
  trackButtons();
  trackOutboundLinks();
});
