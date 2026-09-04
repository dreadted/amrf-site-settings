/* addUmamiTracking
-------------------------------------------------------------------
Add umami tracking for non-logged in users. Ported from amrf-theme's
assets/scripts.js — self-contained here (own DOMContentLoaded listener
below) instead of relying on a theme's own init sequence, since this now
needs to work for any theme, not just one that happens to load it.
*/
const addUmamiTracking = () => {
  return new Promise((resolve) => {
    if (
      !document.body.classList.contains("logged-in") &&
      typeof umamiSite !== "undefined" &&
      umamiSite
    ) {
      let script = document.createElement("script");
      script.src = "https://cloud.umami.is/script.js";
      script.defer = true;
      script.setAttribute("data-website-id", umamiSite);
      script.onload = () => {
        setTimeout(resolve, 0);
      };
      document.head.appendChild(script);
    } else {
      resolve();
    }
  });
};

/* trackFormSubmit
-------------------------------------------------------------------
Sends event to umami when a configured button is clicked. The original
theme code hardcoded a single "#contact .ff-btn-submit" selector; this
reads amrfUmamiButtons instead — localized from
apply_filters('amrf_umami_tracked_buttons', []) (see Umami\Provider) —
so any theme/plugin can register its own buttons without editing this file.
*/
const trackFormSubmit = () => {
  const buttons = typeof amrfUmamiButtons !== "undefined" ? amrfUmamiButtons : [];

  if (typeof umami !== "undefined" && umami) {
    buttons.forEach((button) => {
      const element = document.querySelector(button.selector);
      if (element) element.onclick = () => umami.track(button.name);
    });
  }
};

/* trackOutboundLinks
-------------------------------------------------------------------
Sends event to umami when outbound link is clicked
*/
const trackOutboundLinks = () => {
  document.querySelectorAll("a").forEach((a) => {
    if (
      a.host !== window.location.host &&
      !a.getAttribute("data-umami-event")
    ) {
      const name = "outbound-link-click";
      a.setAttribute("data-umami-event", name);
      a.setAttribute("data-umami-event-url", a.href);
    }
  });
};

document.addEventListener("DOMContentLoaded", () => {
  addUmamiTracking().then(() => {
    trackFormSubmit();
  });
  trackOutboundLinks();
});
