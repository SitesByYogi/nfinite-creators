(function () {
	'use strict';
	if (window.__nfiniteEngagementV1) return;
	window.__nfiniteEngagementV1 = true;
	const cfg = window.NfiniteEngagement;
	if (!cfg || !cfg.endpoint) return;
	if (navigator.doNotTrack === '1' || window.doNotTrack === '1') return;

	const visitorKey = 'nfiniteAnalyticsVisitorV1';
	const sessionKey = 'nfiniteAnalyticsSessionV1';
	function randomId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
		const bytes = new Uint8Array(16);
		if (window.crypto && typeof window.crypto.getRandomValues === 'function') window.crypto.getRandomValues(bytes);
		else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
		return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
	}
	function getStorage(storage, key) { try { return storage.getItem(key) || ''; } catch (e) { return ''; } }
	function setStorage(storage, key, value) { try { storage.setItem(key, value); } catch (e) {} }
	let visitorId = getStorage(window.localStorage, visitorKey);
	if (!visitorId) { visitorId = randomId(); setStorage(window.localStorage, visitorKey, visitorId); }
	let sessionId = getStorage(window.sessionStorage, sessionKey);
	if (!sessionId) { sessionId = randomId(); setStorage(window.sessionStorage, sessionKey, sessionId); }

	function classifySurface() {
		const path = String(window.location.pathname || '/').toLowerCase();
		if (document.querySelector('[data-nfinite-radio]') || /\/radio\/?$/.test(path)) return 'radio';
		if (/rotation/.test(path)) return 'rotation';
		if (document.body.classList.contains('single-nfinite_release') || /\/releases?\//.test(path)) return 'release';
		if (document.body.classList.contains('single-nfinite_creator') || /\/creators?\//.test(path)) return 'creator_profile';
		if (/playlist/.test(path)) return 'playlist';
		if (/\/music\/?$/.test(path) || /\/releases?\/?$/.test(path)) return 'music_hub';
		if (/\/beats\/?$/.test(path)) return 'beats';
		if (/\/tv\/?/.test(path)) return 'tv';
		if (document.body.classList.contains('single-post')) return 'editorial';
		return 'other';
	}

	function send(eventType, state, deltaMs, beacon) {
		if (!state || !state.creatorId || !state.objectId || !state.playbackId) return;
		const data = new URLSearchParams();
		data.set('action', cfg.action || 'nfinite_engagement_event');
		data.set('event_type', eventType);
		data.set('creator_id', String(state.creatorId));
		data.set('object_id', String(state.objectId));
		data.set('object_type', state.objectType || 'track');
		data.set('visitor_id', visitorId);
		data.set('session_id', sessionId);
		data.set('playback_id', state.playbackId);
		data.set('played_delta_ms', String(Math.max(0, Math.round(deltaMs || 0))));
		data.set('position_ms', String(Math.max(0, Math.round(state.positionMs || 0))));
		data.set('duration_ms', String(Math.max(0, Math.round(state.durationMs || 0))));
		data.set('surface', state.surface || 'other');
		data.set('media_source', state.source || 'unknown');
		data.set('media_title', state.title || '');
		if (beacon && navigator.sendBeacon) {
			const blob = new Blob([data.toString()], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
			navigator.sendBeacon(cfg.endpoint, blob);
			return;
		}
		fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: data.toString(), keepalive: true }).catch(function () {});
	}

	const states = new Map();
	const interval = Number(cfg.progressIntervalMs || 10000);
	function keyFor(d) { return String(d.creatorId || 0) + ':' + String(d.objectId || 0); }
	function stateFor(d) {
		const key = keyFor(d);
		let s = states.get(key);
		if (!s) {
			s = {
				creatorId: Number(d.creatorId || 0), objectId: Number(d.objectId || 0), objectType: d.objectType || 'track',
				title: d.title || '', source: d.source || 'unknown', surface: classifySurface(), playbackId: randomId(),
				positionMs: Number(d.positionMs || 0), durationMs: Number(d.durationMs || 0),
				started: false, playing: false, lastClock: 0, unsentMs: 0
			};
			states.set(key, s);
		}
		return s;
	}
	function flushState(s, end, beacon) {
		if (!s || !s.started) return;
		const delta = Math.max(0, Math.min(Number(cfg.maxPulseMs || 15000), s.unsentMs));
		if (delta > 0 || end) send(end ? 'audio_end' : 'audio_progress', s, delta, !!beacon);
		s.unsentMs = Math.max(0, s.unsentMs - delta);
		if (end) { s.started = false; s.playing = false; s.lastClock = 0; }
	}

	document.addEventListener('nfinite:media-progress', function (event) {
		const d = event && event.detail ? event.detail : {};
		if (!Number(d.creatorId || 0) || !Number(d.objectId || 0)) return;
		const s = stateFor(d);
		s.positionMs = Number(d.positionMs || 0);
		s.durationMs = Number(d.durationMs || s.durationMs || 0);
		s.title = d.title || s.title;
		s.source = d.source || s.source;
		const clock = Number(d.clockMs || performance.now());
		if (d.playing) {
			if (!s.started) { s.surface = classifySurface(); s.playbackId = randomId(); s.started = true; send('audio_start', s, 0, false); }
			if (s.lastClock > 0) s.unsentMs += Math.max(0, Math.min(2000, clock - s.lastClock));
			s.lastClock = clock; s.playing = true;
			if (s.unsentMs >= interval) flushState(s, false, false);
		} else {
			s.lastClock = 0; s.playing = false;
			if (s.unsentMs >= 1000) flushState(s, false, false);
		}
	});

	document.addEventListener('nfinite:media-reset', function (event) {
		const d = event && event.detail ? event.detail : {};
		const s = states.get(keyFor(d));
		if (s && s.started) flushState(s, true, false);
		states.delete(keyFor(d));
	});

	window.addEventListener('pagehide', function () {
		states.forEach(function (s) { if (s.started) flushState(s, true, true); });
	});

	// WPNfinite 1.3.13 swaps page content without destroying the persistent
	// player. Surface classification intentionally updates only for the next
	// playback session, so a song started on Radio remains attributed to Radio.
	document.addEventListener('wpnfinite:navigation-complete', function () {});
})();
