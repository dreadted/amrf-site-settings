// A real click listener, not data-umami-event: on links that calls preventDefault() and hijacks theme click handlers.
// Named from visible text + page title; amrfUmamiButtonOverrides wins, and manual data-umami-event elements are skipped.
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

// Outbound links can use Umami's own auto-tracking, since they leave the site anyway. Skips elements trackButtons() wired.
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
