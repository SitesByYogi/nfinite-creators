(function () {
	'use strict';

	const cfg = window.NfiniteCreatorAnalytics;
	if (!cfg || !cfg.endpoint) return;
	if (navigator.doNotTrack === '1' || window.doNotTrack === '1') return;

	const visitorKey = 'nfiniteAnalyticsVisitorV1';
	const sessionKey = 'nfiniteAnalyticsSessionV1';
	const sessionSeenKey = 'nfiniteAnalyticsSessionSeenV1';
	const now = Date.now();

	function randomId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
		const bytes = new Uint8Array(16);
		if (window.crypto && typeof window.crypto.getRandomValues === 'function') window.crypto.getRandomValues(bytes);
		else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
		return Array.from(bytes).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
	}

	function storageGet(storage, key) {
		try { return storage.getItem(key) || ''; } catch (e) { return ''; }
	}
	function storageSet(storage, key, value) {
		try { storage.setItem(key, value); } catch (e) {}
	}

	let visitorId = storageGet(window.localStorage, visitorKey);
	if (!visitorId) {
		visitorId = randomId();
		storageSet(window.localStorage, visitorKey, visitorId);
	}

	let sessionId = storageGet(window.sessionStorage, sessionKey);
	let lastSeen = parseInt(storageGet(window.sessionStorage, sessionSeenKey), 10) || 0;
	if (!sessionId || !lastSeen || (now - lastSeen) > Number(cfg.sessionTimeoutMs || 1800000)) {
		sessionId = randomId();
	}
	storageSet(window.sessionStorage, sessionKey, sessionId);
	storageSet(window.sessionStorage, sessionSeenKey, String(now));

	// Mirror anonymous IDs into first-party cookies so WooCommerce can attach
	// the same visitor/session attribution to an eventual order.
	try {
		document.cookie = 'nfinite_analytics_visitor=' + encodeURIComponent(visitorId) + '; path=/; max-age=31536000; SameSite=Lax';
		document.cookie = 'nfinite_analytics_session=' + encodeURIComponent(sessionId) + '; path=/; max-age=1800; SameSite=Lax';
	} catch (e) {}

	function deviceType() {
		const width = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
		if (width <= 767) return 'mobile';
		if (width <= 1100) return 'tablet';
		return 'desktop';
	}

	function payload(eventType, options) {
		options = options || {};
		const creatorId = Number(options.creatorId || cfg.creatorId || 0);
		const objectId = Number(options.objectId || cfg.objectId || creatorId || 0);
		const data = new URLSearchParams();
		data.set('action', cfg.action || 'nfinite_analytics_event');
		data.set('event_type', eventType);
		data.set('creator_id', String(creatorId));
		data.set('object_type', options.objectType || cfg.objectType || 'creator');
		data.set('object_id', String(objectId));
		data.set('visitor_id', visitorId);
		data.set('session_id', sessionId);
		data.set('engagement_ms', String(Math.max(0, Math.round(options.engagementMs || 0))));
		data.set('page_url', window.location.href);
		data.set('referrer', document.referrer || '');
		data.set('device_type', deviceType());
		if (options.mediaSource) data.set('media_source', String(options.mediaSource));
		if (options.mediaTitle) data.set('media_title', String(options.mediaTitle));
		if (options.positionMs != null) data.set('position_ms', String(Math.max(0, Math.round(options.positionMs))));
		if (options.durationMs != null) data.set('duration_ms', String(Math.max(0, Math.round(options.durationMs))));
		return data;
	}

	function send(eventType, options, beacon) {
		options = options || {};
		const creatorId = Number(options.creatorId || cfg.creatorId || 0);
		if (!creatorId && eventType !== 'page_view' && eventType !== 'site_engagement') return;
		const data = payload(eventType, options);
		if (beacon && navigator.sendBeacon) {
			const blob = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
			navigator.sendBeacon(cfg.endpoint, blob);
			return;
		}
		fetch(cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
			body: data.toString(),
			keepalive: true
		}).catch(function () {});
	}

	// Profile analytics only exist on a creator profile. Media events may be
	// captured on any Nfinite discovery/release surface and carry their creator.
	send('page_view', { creatorId: 0, objectId: 0, objectType: 'site' }, false);
	if (Number(cfg.creatorId || 0)) send('creator_view', {}, false);

	let activeSince = document.visibilityState === 'visible' && document.hasFocus() ? performance.now() : null;
	let accumulated = 0;
	let lastInputAt = performance.now();
	const idleLimit = 60000;

	function markActivity() {
		lastInputAt = performance.now();
		if (activeSince === null && document.visibilityState === 'visible' && document.hasFocus()) activeSince = performance.now();
	}
	['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (name) {
		window.addEventListener(name, markActivity, { passive: true });
	});

	function stopActive() {
		if (activeSince !== null) {
			const nowPerf = performance.now();
			const end = Math.min(nowPerf, lastInputAt + idleLimit);
			if (end > activeSince) accumulated += end - activeSince;
			activeSince = null;
		}
	}

	function startActive() {
		if (document.visibilityState === 'visible' && document.hasFocus()) {
			lastInputAt = performance.now();
			activeSince = performance.now();
		}
	}

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') stopActive();
		else startActive();
	});
	window.addEventListener('blur', stopActive);
	window.addEventListener('focus', startActive);

	function flush(beacon) {
		stopActive();
		const ms = Math.round(accumulated);
		accumulated = 0;
		if (ms >= Number(cfg.minEngagementMs || 1000)) {
			send('site_engagement', { creatorId: 0, objectId: 0, objectType: 'site', engagementMs: ms }, !!beacon);
			if (Number(cfg.creatorId || 0)) send('engagement', { engagementMs: ms }, !!beacon);
		}
		if (document.visibilityState === 'visible' && document.hasFocus()) startActive();
		storageSet(window.sessionStorage, sessionSeenKey, String(Date.now()));
	}

	window.setInterval(function () { flush(false); }, Number(cfg.engagementPingMs || 15000));
	window.addEventListener('pagehide', function () { flush(true); });

	// Creator commerce funnel analytics. Product views are emitted only for
	// Nfinite-owned WooCommerce products. Add-to-cart uses WooCommerce's
	// normalized jQuery event when available.
	if (Number(cfg.productCreatorId || 0) && Number(cfg.productId || 0)) {
		send('product_view', { creatorId: Number(cfg.productCreatorId), objectId: Number(cfg.productId), objectType: 'product' }, false);
	}

	if (window.jQuery) {
		window.jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, button) {
			const $button = window.jQuery(button || []);
			const productId = Number($button.data('product_id') || $button.val() || 0);
			if (!productId) return;
			// On a creator-owned product page we already know the creator. Archive
			// buttons may not expose creator ownership, so avoid guessing.
			if (Number(cfg.productCreatorId || 0) && productId === Number(cfg.productId || 0)) {
				send('add_to_cart', { creatorId: Number(cfg.productCreatorId), objectId: productId, objectType: 'product' }, false);
			}
		});
	}

	/*
	 * Media analytics are emitted by the Nfinite global player as normalized
	 * progress snapshots. This keeps provider-specific logic in the player and
	 * analytics-specific thresholds here.
	 */
	const mediaStates = new Map();
	const meaningfulPlayMs = Number(cfg.meaningfulPlayMs || 5000);

	function mediaKey(detail) {
		return String(detail.creatorId || 0) + ':' + String(detail.objectId || 0);
	}

	function milestoneEvent(percent) {
		if (percent >= 99.5) return 'media_complete';
		if (percent >= 75) return 'media_75';
		if (percent >= 50) return 'media_50';
		if (percent >= 25) return 'media_25';
		return '';
	}

	function sendMedia(eventType, detail) {
		send(eventType, {
			creatorId: Number(detail.creatorId || 0),
			objectId: Number(detail.objectId || 0),
			objectType: detail.objectType || 'track',
			mediaTitle: detail.title || '',
			mediaSource: detail.source || 'unknown',
			positionMs: Number(detail.positionMs || 0),
			durationMs: Number(detail.durationMs || 0)
		}, false);
	}

	document.addEventListener('nfinite:media-progress', function (event) {
		const detail = event && event.detail ? event.detail : {};
		const creatorId = Number(detail.creatorId || 0);
		const objectId = Number(detail.objectId || 0);
		if (!creatorId || !objectId) return;

		const key = mediaKey(detail);
		let state = mediaStates.get(key);
		if (!state) {
			state = { playedMs: 0, lastClock: 0, sent: new Set() };
			mediaStates.set(key, state);
		}

		const clock = Number(detail.clockMs || performance.now());
		if (detail.playing) {
			if (state.lastClock > 0) {
				const delta = Math.max(0, Math.min(2000, clock - state.lastClock));
				state.playedMs += delta;
			}
			state.lastClock = clock;
		} else {
			state.lastClock = 0;
		}

		if (state.playedMs >= meaningfulPlayMs && !state.sent.has('media_play')) {
			state.sent.add('media_play');
			sendMedia('media_play', detail);
		}

		const durationMs = Number(detail.durationMs || 0);
		const positionMs = Number(detail.positionMs || 0);
		if (durationMs > 0 && positionMs >= 0) {
			const percent = Math.min(100, (positionMs / durationMs) * 100);
			[25, 50, 75, 99.5].forEach(function (threshold) {
				if (percent < threshold) return;
				const eventType = milestoneEvent(threshold);
				if (!eventType || state.sent.has(eventType)) return;
				// Completion milestones should represent actual listening, not a seek
				// directly to the end. Require meaningful playback first.
				if (state.playedMs < meaningfulPlayMs) return;
				state.sent.add(eventType);
				sendMedia(eventType, detail);
			});
		}
	});

	document.addEventListener('nfinite:media-reset', function (event) {
		const detail = event && event.detail ? event.detail : {};
		const key = mediaKey(detail);
		const state = mediaStates.get(key);
		if (state) state.lastClock = 0;
	});
})();
