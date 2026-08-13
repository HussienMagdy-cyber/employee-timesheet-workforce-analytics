(function () {
	'use strict';

	const SIDEBAR_COLLAPSED_KEY = 'etwa_sidebar_collapsed';
	const DESKTOP_MQ = window.matchMedia('(min-width: 961px)');

	let root = null;
	let sidebar = null;
	let overlay = null;
	let toggle = null;
	let collapseToggle = null;
	let shellInitialized = false;
	let openFlyoutEl = null;
	let openFlyoutButton = null;

	function bindElements() {
		root = document.getElementById('etwa-portal-root');
		sidebar = document.getElementById('etwa-portal-sidebar');
		overlay = document.getElementById('etwa-portal-overlay');
		toggle = document.getElementById('etwa-portal-nav-toggle');
		collapseToggle = document.getElementById('etwa-portal-sidebar-collapse');
		return !!root;
	}

	function isDesktop() {
		return DESKTOP_MQ.matches;
	}

	function isSidebarCollapsed() {
		return !!root && root.classList.contains('etwa-sidebar-collapsed');
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

	function closeFlyouts() {
		if (openFlyoutEl) {
			openFlyoutEl.hidden = true;
			openFlyoutEl = null;
		}
		if (openFlyoutButton) {
			openFlyoutButton.setAttribute('aria-expanded', 'false');
			openFlyoutButton = null;
		}
	}

	function positionFlyout(button, flyout) {
		const rect = button.getBoundingClientRect();
		const margin = 8;
		let top = rect.top;
		let left = rect.right + margin;

		flyout.style.top = `${top}px`;
		flyout.style.left = `${left}px`;

		const flyoutRect = flyout.getBoundingClientRect();
		if (flyoutRect.bottom > window.innerHeight - margin) {
			top = Math.max(margin, window.innerHeight - flyoutRect.height - margin);
			flyout.style.top = `${top}px`;
		}
		if (flyoutRect.right > window.innerWidth - margin) {
			left = Math.max(margin, rect.left - flyoutRect.width - margin);
			flyout.style.left = `${left}px`;
		}
	}

	function openFlyout(button, flyout) {
		if (openFlyoutEl && openFlyoutEl !== flyout) {
			closeFlyouts();
		}
		flyout.hidden = false;
		positionFlyout(button, flyout);
		button.setAttribute('aria-expanded', 'true');
		openFlyoutEl = flyout;
		openFlyoutButton = button;
	}

	function setSidebarCollapsed(collapsed, persist) {
		if (!root) return;
		closeFlyouts();
		root.classList.toggle('etwa-sidebar-collapsed', collapsed);
		if (collapseToggle) {
			collapseToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			collapseToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
		}
		if (persist && isDesktop()) {
			try {
				localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
			} catch (e) {
				/* ignore */
			}
		}
	}

	function initSidebarCollapse() {
		if (!root || !collapseToggle || collapseToggle.dataset.etwaCollapseBound === '1') {
			return;
		}
		collapseToggle.dataset.etwaCollapseBound = '1';

		if (isDesktop()) {
			setSidebarCollapsed(isSidebarCollapsed(), false);
		}

		collapseToggle.addEventListener('click', () => {
			if (!isDesktop()) return;
			setSidebarCollapsed(!isSidebarCollapsed(), true);
		});

		DESKTOP_MQ.addEventListener('change', (event) => {
			if (event.matches) {
				let collapsed = false;
				try {
					collapsed = localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
				} catch (e) {
					collapsed = false;
				}
				setSidebarCollapsed(collapsed, false);
				setNavOpen(false);
			} else {
				root.classList.remove('etwa-sidebar-collapsed');
				closeFlyouts();
			}
		});
	}

	function initNavGroups() {
		if (!root || root.dataset.etwaNavGroupsBound === '1') {
			return;
		}
		root.dataset.etwaNavGroupsBound = '1';

		root.querySelectorAll('.etwa-portal-nav-group-toggle').forEach((button) => {
			button.addEventListener('click', (event) => {
				if (isSidebarCollapsed() && isDesktop()) {
					event.preventDefault();
					const group = button.closest('.etwa-portal-nav-group');
					const flyout = group?.querySelector('.etwa-portal-nav-flyout');
					if (!group || !flyout) {
						return;
					}
					if (openFlyoutEl === flyout && !flyout.hidden) {
						closeFlyouts();
						return;
					}
					openFlyout(button, flyout);
					return;
				}

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

		document.addEventListener('click', (event) => {
			if (!openFlyoutEl || !isSidebarCollapsed()) {
				return;
			}
			if (
				event.target.closest('.etwa-portal-nav-flyout') ||
				event.target.closest('.etwa-portal-nav-group-toggle')
			) {
				return;
			}
			closeFlyouts();
		});

		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && openFlyoutEl) {
				closeFlyouts();
			}
		});

		root.querySelectorAll('.etwa-portal-nav-flyout-link, .etwa-portal-nav-rail-direct').forEach((link) => {
			link.addEventListener('click', () => {
				closeFlyouts();
				if (!isDesktop()) {
					setNavOpen(false);
				}
			});
		});

		window.addEventListener('resize', () => {
			if (openFlyoutEl && openFlyoutButton) {
				positionFlyout(openFlyoutButton, openFlyoutEl);
			}
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
				if (!isDesktop()) {
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
		initSidebarCollapse();
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
