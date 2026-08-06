(function () {
	'use strict';

	const SESSION_PATH = 'portal/session';
	const REFRESH_INTERVAL_MS = 10 * 60 * 1000;

	let refreshPromise = null;

	function cfg() {
		return window.etwaApp || {};
	}

	function i18n(key, fallback) {
		return cfg().i18n?.[key] || fallback;
	}

	function getNonce() {
		return cfg().nonce || '';
	}

	function setNonce(nonce) {
		if (window.etwaApp && nonce) {
			window.etwaApp.nonce = nonce;
		}
	}

	function setAjaxNonce(nonce) {
		if (window.etwaApp && nonce) {
			window.etwaApp.ajaxNonce = nonce;
		}
		if (window.etwaPortalShell && nonce) {
			window.etwaPortalShell.ajaxNonce = nonce;
		}
	}

	function restUrl(path) {
		const root = cfg().root || '';
		return root + String(path).replace(/^\//, '');
	}

	function isAuthError(data, status) {
		if (!data || typeof data !== 'object') {
			return status === 401 || status === 403;
		}

		const code = String(data.code || '');
		const message = String(data.message || '');

		return (
			code === 'rest_cookie_invalid_nonce' ||
			code === 'rest_not_logged_in' ||
			/cookie check failed/i.test(message)
		);
	}

	function isLoggedOut(data) {
		return String(data?.code || '') === 'rest_not_logged_in';
	}

	function sessionExpiredMessage() {
		return i18n('sessionExpired', 'Your session expired. Please refresh the page and log in again.');
	}

	function agentDebugLog(location, message, data, hypothesisId) {
		const payload = JSON.stringify({
			sessionId: '154178',
			location,
			message,
			data: data || {},
			hypothesisId: hypothesisId || 'H5',
			timestamp: Date.now(),
			runId: 'pre-fix',
		});
		fetch('http://127.0.0.1:7920/ingest/d692f9a8-0e0c-45a7-824f-e64b88eed57e', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': '154178' },
			body: payload,
		}).catch(() => {});
		const ajaxUrl = cfg().ajaxUrl || (document.getElementById('etwa-portal-root')?.dataset?.ajaxUrl);
		if (ajaxUrl) {
			fetch(ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'action=etwa_agent_debug_log', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: payload,
			}).catch(() => {});
		}
	}

	async function fetchSession() {
		const response = await fetch(restUrl(SESSION_PATH), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': getNonce() },
		});
		const data = await response.json().catch(() => ({}));

		// #region agent log
		agentDebugLog('etwa-api.js:fetchSession', 'fetchSession response', {
			status: response.status,
			ok: response.ok,
			code: data?.code || null,
			hasMessage: !!data?.message,
			hasSuccessNonce: !!(data?.success && data?.data?.nonce),
			nonceLen: getNonce().length,
		}, 'H4-H5');
		// #endregion

		if (response.ok && data.success && data.data?.nonce) {
			setNonce(data.data.nonce);
			if (data.data.ajaxNonce) {
				setAjaxNonce(data.data.ajaxNonce);
			}
			return data.data;
		}

		if (isLoggedOut(data) || response.status === 401) {
			throw new Error(sessionExpiredMessage());
		}

		throw new Error(data.message || i18n('error', 'Something went wrong.'));
	}

	function refreshNonce() {
		if (!refreshPromise) {
			refreshPromise = fetchSession().finally(() => {
				refreshPromise = null;
			});
		}
		return refreshPromise;
	}

	async function api(path, options = {}, retried = false) {
		const requestOptions = Object.assign({}, options);
		requestOptions.credentials = 'same-origin';

		const headers = Object.assign({}, requestOptions.headers || {});
		if (requestOptions.body && !headers['Content-Type']) {
			headers['Content-Type'] = 'application/json';
		}
		headers['X-WP-Nonce'] = getNonce();
		requestOptions.headers = headers;

		const response = await fetch(restUrl(path), requestOptions);
		const data = await response.json().catch(() => ({}));

		if (!response.ok && isAuthError(data, response.status) && !retried) {
			// #region agent log
			agentDebugLog('etwa-api.js:api', 'auth error — attempting nonce refresh', {
				path,
				status: response.status,
				code: data?.code || null,
				nonceLen: getNonce().length,
			}, 'H4');
			// #endregion
			try {
				await refreshNonce();
				return api(path, options, true);
			} catch (err) {
				// #region agent log
				agentDebugLog('etwa-api.js:api', 'nonce refresh failed after auth error', {
					path,
					errMessage: err?.message || null,
				}, 'H4-H5');
				// #endregion
				return {
					success: false,
					message: err.message || sessionExpiredMessage(),
					code: data.code || 'session_expired',
				};
			}
		}

		if (!response.ok && !data.message) {
			data.message = i18n('error', 'Something went wrong.');
		}
		if (!response.ok && data.success !== false) {
			data.success = false;
		}

		return data;
	}

	function startNonceRefreshTimer() {
		if (window.etwaApiRefreshStarted) {
			return;
		}

		if (!cfg().root) {
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', startNonceRefreshTimer, { once: true });
			} else {
				window.setTimeout(startNonceRefreshTimer, 0);
			}
			return;
		}

		window.etwaApiRefreshStarted = true;
		setInterval(() => {
			refreshNonce().catch(() => {});
		}, REFRESH_INTERVAL_MS);
	}

	window.etwaAPI = {
		api,
		refreshNonce,
		startNonceRefreshTimer,
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startNonceRefreshTimer, { once: true });
	} else {
		startNonceRefreshTimer();
	}
})();
