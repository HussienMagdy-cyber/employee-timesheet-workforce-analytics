(function () {
	'use strict';

	let root = null;
	let sidebar = null;
	let overlay = null;
	let toggle = null;
	let shellInitialized = false;

	function bindElements() {
		root = document.getElementById('etwa-portal-root');
		sidebar = document.getElementById('etwa-portal-sidebar');
		overlay = document.getElementById('etwa-portal-overlay');
		toggle = document.getElementById('etwa-portal-nav-toggle');
		return !!root;
	}

	function setNavOpen(open) {
		if (!root) return;
		root.classList.toggle('etwa-nav-open', open);
		if (sidebar) {
			sidebar.classList.toggle('is-open', open);
		}
		if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (overlay) {
			overlay.hidden = !open;
			overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
		}
	}

	function initNavGroups() {
		if (!root || root.dataset.etwaNavGroupsBound === '1') {
			return;
		}
		root.dataset.etwaNavGroupsBound = '1';

		root.querySelectorAll('.etwa-portal-nav-group-toggle').forEach((button) => {
			button.addEventListener('click', () => {
				const group = button.closest('.etwa-portal-nav-group');
				const panel = group?.querySelector('.etwa-portal-nav-group-items');
				if (!group || !panel) {
					return;
				}

				const isOpen = group.classList.toggle('is-open');
				panel.hidden = !isOpen;
				button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});
		});
	}

	function initMobileNav() {
		if (!root || (toggle && toggle.dataset.etwaNavBound === '1')) {
			return;
		}

		if (toggle) {
			toggle.dataset.etwaNavBound = '1';
			toggle.addEventListener('click', () => setNavOpen(!root.classList.contains('etwa-nav-open')));
		}

		if (overlay) {
			overlay.addEventListener('click', () => setNavOpen(false));
		}

		root.querySelectorAll('.etwa-portal-nav-link').forEach((link) => {
			link.addEventListener('click', () => {
				if (window.matchMedia('(max-width: 960px)').matches) {
					setNavOpen(false);
				}
			});
		});

		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && root.classList.contains('etwa-nav-open')) {
				setNavOpen(false);
			}
		});
	}

	function portalConfig() {
		if (!root) return null;
		const shell = window.etwaPortalShell || {};
		if (shell.ajaxUrl && shell.ajaxNonce) {
			return shell;
		}
		if (window.etwaApp && window.etwaApp.ajaxUrl && window.etwaApp.ajaxNonce) {
			return window.etwaApp;
		}
		const ajaxUrl = root.dataset.ajaxUrl;
		const ajaxNonce = root.dataset.ajaxNonce;
		if (ajaxUrl && ajaxNonce) {
			return { ajaxUrl, ajaxNonce };
		}
		return null;
	}

	function setDarkModeUi(isDark) {
		if (!root) return;
		root.classList.toggle('etwa-dark', isDark);
		document.querySelectorAll('.etwa-portal-layout .etwa-app, #etwa-portal-root.etwa-app').forEach((el) => {
			el.classList.toggle('etwa-dark', isDark);
		});
		const darkToggle = document.getElementById('etwa-dark-toggle');
		if (darkToggle) {
			darkToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
		}
	}

	function persistDarkMode(isDark) {
		const cfg = portalConfig();
		if (!cfg || !cfg.ajaxUrl || !cfg.ajaxNonce) {
			return Promise.resolve();
		}

		const body = new URLSearchParams({
			action: 'etwa_toggle_dark_mode',
			nonce: cfg.ajaxNonce,
		});

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		})
			.then((response) => response.json())
			.then((payload) => {
				if (payload && payload.success && typeof payload.data?.dark_mode === 'boolean') {
					setDarkModeUi(payload.data.dark_mode);
					return;
				}
				throw new Error('Theme preference was not saved.');
			})
			.catch(() => {
				setDarkModeUi(!isDark);
			});
	}

	function bindDarkToggle() {
		if (!root || root.dataset.etwaThemeBound === '1') {
			return;
		}

		const cfg = portalConfig();
		if (!cfg || !cfg.ajaxUrl || !cfg.ajaxNonce) {
			return;
		}

		root.dataset.etwaThemeBound = '1';

		root.addEventListener('click', (event) => {
			const darkToggle = event.target.closest('.etwa-dark-toggle, #etwa-dark-toggle');
			if (!darkToggle || !root.contains(darkToggle)) {
				return;
			}

			event.preventDefault();

			const nextDark = !root.classList.contains('etwa-dark');
			setDarkModeUi(nextDark);
			persistDarkMode(nextDark);
		});
	}

	function initPortalShell() {
		if (shellInitialized) {
			return;
		}
		if (!bindElements()) {
			return;
		}
		shellInitialized = true;
		initNavGroups();
		initMobileNav();
		bindDarkToggle();
		loadNavBadges();
	}

	function loadNavBadges() {
		const cfg = window.etwaApp;
		if (!cfg || !cfg.root || !window.etwaAPI || !document.querySelector('[data-nav-badge]')) {
			return;
		}

		window.etwaAPI.api('portal/nav-badges')
			.then((payload) => {
				if (!payload || !payload.success) return;
				const badges = payload.data || {};
				document.querySelectorAll('[data-nav-badge]').forEach((el) => {
					const key = el.getAttribute('data-nav-badge');
					const count = badges[key] || 0;
					if (count > 0) {
						el.textContent = String(count);
						el.removeAttribute('hidden');
					} else {
						el.setAttribute('hidden', 'hidden');
						el.textContent = '';
					}
				});
			})
			.catch(() => {});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPortalShell);
	} else {
		initPortalShell();
	}
})();
