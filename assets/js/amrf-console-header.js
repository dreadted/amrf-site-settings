// Prints a stylized console badge with the site host, theme version, and
// author, using amrfBranding (localized by Branding\Provider).
const consoleHeader = () => {
	const { author, version } = window.amrfBranding || {};
	const styles = getComputedStyle(document.documentElement);
	const color = (name, fallback) =>
		styles.getPropertyValue(name).trim() || fallback;
	const primary = color("--amrf-primary-color", "#1976d2");
	const secondary = color("--amrf-secondary-color", "#1976d2");
	const text = color("--amrf-text-color", "#fff");
	const year = new Date().getFullYear();

	console.log(
		`%c ${window.location.hostname} %c v${version || "Unknown"} %c\n© ${year} ${author || "Unknown"}`,
		`background-color: ${primary}; color: ${text}; border-radius: 3px 0 0 3px;`,
		`background-color: ${secondary}; color: ${text}; border-radius: 0 3px 3px 0;`,
		`background-color: transparent; color: ${text}; border-radius: 0 3px 3px 0;`
	);
};

document.addEventListener("DOMContentLoaded", consoleHeader);
