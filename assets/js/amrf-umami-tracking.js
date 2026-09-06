// Adds umami tracking for logged-out visitors only.
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

// Tracks clicks on buttons registered via apply_filters('amrf_umami_tracked_buttons', [])
// (see Umami\Provider), localized into amrfUmamiButtons.
const trackFormSubmit = () => {
  const buttons = typeof amrfUmamiButtons !== "undefined" ? amrfUmamiButtons : [];

  if (typeof umami !== "undefined" && umami) {
    buttons.forEach((button) => {
      const element = document.querySelector(button.selector);
      if (element) element.onclick = () => umami.track(button.name);
    });
  }
};

// Marks outbound links for umami's data-umami-event auto-tracking.
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
