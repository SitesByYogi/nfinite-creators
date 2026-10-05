document.addEventListener('DOMContentLoaded', function () {
	const shell = document.querySelector('[data-nfinite-global-player]');
	if (!shell) return;

	const audio = shell.querySelector('[data-global-audio]');
	const playButton = shell.querySelector('[data-global-play]');
	const compactPlay = shell.querySelector('[data-global-compact-play]');
	const prevButton = shell.querySelector('[data-global-prev]');
	const nextButton = shell.querySelector('[data-global-next]');
	const shuffleButton = shell.querySelector('[data-global-shuffle]');
	const repeatButton = shell.querySelector('[data-global-repeat]');
	const progress = shell.querySelector('[data-global-progress]');
	const volume = shell.querySelector('[data-global-volume]');
	const currentTime = shell.querySelector('[data-global-current]');
	const duration = shell.querySelector('[data-global-duration]');
	const queuePanel = shell.querySelector('[data-global-queue]');
	const queueToggle = shell.querySelector('[data-global-queue-toggle]');
	const panel = shell.querySelector('[data-player-panel]');
	const expand = shell.querySelector('[data-player-expand]');
	const collapse = shell.querySelector('[data-player-collapse]');
	const dismiss = shell.querySelector('[data-player-dismiss]');
	const provider = shell.querySelector('[data-global-provider]');
	const providerFrame = shell.querySelector('[data-global-provider-frame]');
	const providerLink = shell.querySelector('[data-global-provider-link]');

	/* Player state remains in this long-lived DOM while WPNfinite swaps #primary. */
	const preferencesKey = 'nfiniteMusicPlayerPreferencesV2';
	const legacyStateKey = 'nfiniteMusicPlayerStateV1';
	const navigationStateKey = 'nfiniteMusicPlayerNavigationStateV3';
	let restoringNavigationState = false;
	let lastNavigationPersistAt = 0;

	let queue = [];
	let index = 0;
	let repeat = false;
	let shuffle = false;
	let providerController = null;
	let externalPlaying = false;
	let spotifyApiPromise = null;
	let soundcloudApiPromise = null;
	const spotifyControllerCache = new Map();
	let spotifyPreloadHost = null;
	let activeSpotifyEntry = null;
	let youtubeApiPromise = null;
	const youtubePlayers = new Map();

	/*
	 * Cross-media coordination. PairOfDice can keep its audio player alive while
	 * WPNfinite swaps page content, so embedded page video must participate in
	 * the same one-media-at-a-time rule. In particular, iOS will often pause
	 * native audio when YouTube starts, but it will not automatically pause the
	 * YouTube iframe when Nfinite audio later resumes.
	 */
	function isYouTubeFrame(iframe) {
		if (!iframe || !iframe.src) return false;
		try {
			const host = new URL(iframe.src, window.location.href).hostname.replace(/^www\./, '');
			return host === 'youtube.com' || host === 'youtube-nocookie.com' || host === 'm.youtube.com';
		} catch (e) {
			return false;
		}
	}

	function ensureYouTubeApiUrl(iframe) {
		if (!isYouTubeFrame(iframe)) return false;
		try {
			const url = new URL(iframe.src, window.location.href);
			let changed = false;
			if (url.searchParams.get('enablejsapi') !== '1') { url.searchParams.set('enablejsapi', '1'); changed = true; }
			if (!url.searchParams.get('origin')) { url.searchParams.set('origin', window.location.origin); changed = true; }
			if (!url.searchParams.get('playsinline')) { url.searchParams.set('playsinline', '1'); changed = true; }
			if (changed) iframe.src = url.toString();
			return true;
		} catch (e) {
			return false;
		}
	}

	function getYouTubeApi() {
		if (window.YT && window.YT.Player) return Promise.resolve(window.YT);
		if (youtubeApiPromise) return youtubeApiPromise;
		youtubeApiPromise = new Promise(function(resolve, reject) {
			const previous = window.onYouTubeIframeAPIReady;
			window.onYouTubeIframeAPIReady = function() {
				if (typeof previous === 'function') { try { previous(); } catch (e) {} }
				resolve(window.YT);
			};
			loadScriptOnce('https://www.youtube.com/iframe_api', () => !!(window.YT && window.YT.Player)).then(function() {
				if (window.YT && window.YT.Player) resolve(window.YT);
			}).catch(reject);
		});
		return youtubeApiPromise;
	}

	function pauseGlobalForPageVideo() {
		if (!audio.paused) audio.pause();
		if (providerController && typeof providerController.pause === 'function') {
			try { providerController.pause(); } catch (e) {}
		}
		externalPlaying = false;
		updateButtons();
	}

	document.addEventListener('nfinite:video-play', function () {
		pauseGlobalForPageVideo();
	});

	function pausePageVideos() {
		document.querySelectorAll('video').forEach(function(video) {
			if (!video.paused) { try { video.pause(); } catch (e) {} }
		});

		youtubePlayers.forEach(function(player, iframe) {
			if (!document.documentElement.contains(iframe)) {
				youtubePlayers.delete(iframe);
				return;
			}
			if (player && typeof player.pauseVideo === 'function') {
				try { player.pauseVideo(); } catch (e) {}
			}
		});

		/* Immediate fallback while the iframe API is still initializing. */
		document.querySelectorAll('iframe').forEach(function(iframe) {
			if (!isYouTubeFrame(iframe) || !iframe.contentWindow) return;
			try {
				iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'pauseVideo', args: [] }), '*');
			} catch (e) {}
		});
	}

	function bindYouTubePlayers(root) {
		root = root || document;
		const frames = Array.from(root.querySelectorAll('iframe')).filter(isYouTubeFrame);
		if (!frames.length) return;
		frames.forEach(ensureYouTubeApiUrl);
		getYouTubeApi().then(function(YT) {
			frames.forEach(function(iframe) {
				if (!document.documentElement.contains(iframe) || youtubePlayers.has(iframe)) return;
				try {
					const player = new YT.Player(iframe, {
						events: {
							onStateChange: function(event) {
								if (event && event.data === YT.PlayerState.PLAYING) pauseGlobalForPageVideo();
							}
						}
					});
					youtubePlayers.set(iframe, player);
				} catch (e) {}
			});
		}).catch(function() {});
	}


	/*
	 * Cross-browser press handling.
	 *
	 * Mobile Safari needs playback to begin inside the original touch/pointer
	 * gesture, but binding every pointerup event also captures desktop mouse
	 * input in Chrome. That can interfere with the browser's normal click path.
	 *
	 * Use pointerup only for actual touch pointers, keep plain click as the
	 * desktop/mouse/keyboard path, and suppress only the synthetic click that
	 * immediately follows a handled touch gesture.
	 */
	function bindPress(element, handler) {
		if (!element || typeof handler !== 'function') return;

		let lastTouchPress = 0;

		const invokeTouch = function (event) {
			if (event && event.type === 'pointerup' && event.pointerType && event.pointerType !== 'touch') return;
			lastTouchPress = Date.now();
			handler(event || null);
		};

		const invokeClick = function (event) {
			/* Ignore only the compatibility click generated after a real touch. */
			if (Date.now() - lastTouchPress < 900) return;
			handler(event || null);
		};

		if (window.PointerEvent) {
			element.addEventListener('pointerup', invokeTouch, { passive: false });
		} else {
			element.addEventListener('touchend', invokeTouch, { passive: false });
		}

		element.addEventListener('click', invokeClick);
	}

	function providerName(source) {
		return { soundcloud: 'SoundCloud', spotify: 'Spotify', apple_music: 'Apple Music', internet_archive: 'Internet Archive' }[source] || 'Streaming service';
	}

	function loadScriptOnce(src, test) {
		if (typeof test === 'function' && test()) return Promise.resolve();
		return new Promise(function(resolve, reject){
			let script = document.querySelector('script[src="' + src + '"]');
			if (script) {
				script.addEventListener('load', resolve, { once: true });
				setTimeout(resolve, 1000);
				return;
			}
			script = document.createElement('script');
			script.src = src;
			script.async = true;
			script.onload = resolve;
			script.onerror = reject;
			document.head.appendChild(script);
		});
	}

	function spotifyUri(raw) {
		try {
			const url = new URL(raw);
			const parts = url.pathname.split('/').filter(Boolean);
			const typeIndex = parts.findIndex(part => ['track', 'album', 'playlist', 'episode', 'show', 'artist'].includes(part));
			if (typeIndex >= 0 && parts[typeIndex + 1]) return 'spotify:' + parts[typeIndex] + ':' + parts[typeIndex + 1];
		} catch (e) {}
		return '';
	}

	function getSpotifyApi() {
		if (window.SpotifyIframeApi) return Promise.resolve(window.SpotifyIframeApi);
		if (spotifyApiPromise) return spotifyApiPromise;
		spotifyApiPromise = new Promise(function(resolve, reject){
			const previous = window.onSpotifyIframeApiReady;
			window.onSpotifyIframeApiReady = function(IFrameAPI){
				window.SpotifyIframeApi = IFrameAPI;
				if (typeof previous === 'function') { try { previous(IFrameAPI); } catch(e) {} }
				resolve(IFrameAPI);
			};
			loadScriptOnce('https://open.spotify.com/embed/iframe-api/v1', () => !!window.SpotifyIframeApi).catch(reject);
		});
		return spotifyApiPromise;
	}

	function getSoundCloudApi() {
		if (window.SC && window.SC.Widget) return Promise.resolve(window.SC);
		if (!soundcloudApiPromise) {
			soundcloudApiPromise = loadScriptOnce('https://w.soundcloud.com/player/api.js', () => !!(window.SC && window.SC.Widget)).then(() => window.SC);
		}
		return soundcloudApiPromise;
	}



	function ensureSpotifyPreloadHost() {
		if (spotifyPreloadHost && document.body.contains(spotifyPreloadHost)) return spotifyPreloadHost;
		spotifyPreloadHost = document.createElement('div');
		spotifyPreloadHost.setAttribute('aria-hidden', 'true');
		spotifyPreloadHost.style.position = 'fixed';
		spotifyPreloadHost.style.left = '-10000px';
		spotifyPreloadHost.style.top = '0';
		spotifyPreloadHost.style.width = '320px';
		spotifyPreloadHost.style.height = '160px';
		spotifyPreloadHost.style.overflow = 'hidden';
		spotifyPreloadHost.style.pointerEvents = 'none';
		document.body.appendChild(spotifyPreloadHost);
		return spotifyPreloadHost;
	}

	function parkActiveSpotify() {
		if (!activeSpotifyEntry || !activeSpotifyEntry.mount) return;
		clearSpotifyEndTimer(activeSpotifyEntry);
		ensureSpotifyPreloadHost().appendChild(activeSpotifyEntry.mount);
		activeSpotifyEntry = null;
	}

	function clearSpotifyEndTimer(entry) {
		if (!entry || !entry.endTimer) return;
		window.clearTimeout(entry.endTimer);
		entry.endTimer = null;
	}

	function scheduleSpotifyEnd(entry, state) {
		if (!entry || activeSpotifyEntry !== entry || !state) return;
		clearSpotifyEndTimer(entry);

		const durationMs = Number(state.duration) || 0;
		const positionMs = Number(state.position) || 0;
		if (!durationMs || state.isPaused) return;

		const remainingMs = Math.max(0, durationMs - positionMs);
		/*
		 * Spotify does not expose a dedicated ended event. Keep 0.18.3's
		 * known-good playback behavior untouched and use a passive timer based
		 * on the provider's duration/position updates. Each update refreshes
		 * the timer, so seeks/buffering do not leave a stale transition behind.
		 */
		entry.endTimer = window.setTimeout(function () {
			if (activeSpotifyEntry !== entry || !externalPlaying) return;
			entry.endTimer = null;
			externalPlaying = false;
			updateButtons();
			nextTrack();
		}, remainingMs + 900);
	}

	function attachSpotifyEvents(entry) {
		if (!entry || !entry.controller || entry.eventsBound) return;
		entry.eventsBound = true;
		entry.endTimer = null;
		if (typeof entry.controller.addListener === 'function') {
			entry.controller.addListener('playback_started', function () {
				externalPlaying = true;
				updateButtons();
			});
			entry.controller.addListener('playback_update', function (event) {
				if (!event || !event.data) return;
				externalPlaying = !event.data.isPaused;
				if (activeSpotifyEntry === entry) {
					const durationMs = Number(event.data.duration) || 0;
					const positionMs = Number(event.data.position) || 0;
					if (durationMs > 0) {
						currentTime.textContent = formatTime(positionMs / 1000);
						duration.textContent = formatTime(durationMs / 1000);
						progress.value = Math.min(100, (positionMs / durationMs) * 100);
					}
					emitMediaProgress(positionMs, durationMs, !event.data.isPaused);
					scheduleSpotifyEnd(entry, event.data);
				}
				updateButtons();
			});
		}
	}

	function prepareSpotifyController(rawUrl) {
		const uri = spotifyUri(rawUrl);
		if (!uri) return Promise.resolve(null);
		if (spotifyControllerCache.has(uri)) return spotifyControllerCache.get(uri).promise;

		const entry = { uri: uri, mount: document.createElement('div'), controller: null, ready: false, eventsBound: false, promise: null };
		ensureSpotifyPreloadHost().appendChild(entry.mount);
		entry.promise = getSpotifyApi().then(function (IFrameAPI) {
			return new Promise(function (resolve) {
				IFrameAPI.createController(entry.mount, { uri: uri, height: 152 }, function (controller) {
					entry.controller = controller;
					entry.ready = true;
					attachSpotifyEvents(entry);
					resolve(entry);
				});
			});
		}).catch(function () { return null; });
		spotifyControllerCache.set(uri, entry);
		return entry.promise;
	}

	function discoverSpotifyUrls() {
		const urls = [];
		const seen = new Set();
		document.querySelectorAll('[data-nfinite-track-queue], [data-nfinite-release-player]').forEach(function (element) {
			let items = [];
			try { items = JSON.parse(element.dataset.queue || '[]'); } catch (e) {}
			items.forEach(function (item) {
				if (!item || item.source !== 'spotify' || !item.sourceUrl) return;
				const uri = spotifyUri(item.sourceUrl);
				if (!uri || seen.has(uri)) return;
				seen.add(uri);
				urls.push(item.sourceUrl);
			});
		});
		return urls;
	}

	function preloadSpotifyControllers() {
		const urls = discoverSpotifyUrls().slice(0, 8);
		if (!urls.length) return;
		getSpotifyApi().then(function () {
			urls.forEach(function (url) { prepareSpotifyController(url); });
		}).catch(function () {});
	}

	function providerEmbed(track) {
		const source = track && track.source ? track.source : 'local';
		const raw = track && track.sourceUrl ? String(track.sourceUrl).trim() : '';
		if (!raw || source === 'local') return null;

		if (source === 'soundcloud') {
			return {
				url: 'https://w.soundcloud.com/player/?url=' + encodeURIComponent(raw) + '&auto_play=true&hide_related=true&show_comments=false&show_user=true&show_reposts=false&visual=false',
				height: 122,
				allow: 'autoplay'
			};
		}

		if (source === 'spotify') {
			try {
				const url = new URL(raw);
				const parts = url.pathname.split('/').filter(Boolean);
				const typeIndex = parts.findIndex(part => ['track', 'album', 'playlist', 'episode', 'show', 'artist'].includes(part));
				if (typeIndex >= 0 && parts[typeIndex + 1]) {
					return {
						url: 'https://open.spotify.com/embed/' + parts[typeIndex] + '/' + parts[typeIndex + 1] + '?utm_source=generator',
						height: 152,
						allow: 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture'
					};
				}
			} catch (e) {}
		}

		if (source === 'apple_music') {
			try {
				const url = new URL(raw);
				return {
					url: 'https://embed.music.apple.com' + url.pathname + url.search,
					height: 175,
					allow: 'autoplay *; encrypted-media *; fullscreen *; clipboard-write'
				};
			} catch (e) {}
		}

		return null;
	}

	function clearProvider() {
		parkActiveSpotify();
		if (providerController && typeof providerController.pause === 'function') { try { providerController.pause(); } catch (e) {} }
		providerController = null;
		externalPlaying = false;
		if (providerFrame) providerFrame.innerHTML = '';
		if (provider) provider.hidden = true;
		if (providerLink) { providerLink.href = '#'; providerLink.textContent = ''; }
		shell.classList.remove('is-external');
	}

	function loadExternalTrack(track, autoplay = true) {
		const embed = providerEmbed(track);
		if (!embed || !provider || !providerFrame) return false;
		audio.pause();
		audio.removeAttribute('src');
		audio.load();
		providerController = null;
		externalPlaying = false;
		providerFrame.innerHTML = '';
		provider.hidden = false;
		shell.classList.add('is-external');
		if (providerLink) {
			providerLink.href = track.sourceUrl || '#';
			providerLink.textContent = 'Open in ' + providerName(track.source) + ' ↗';
		}

		if (track.source === 'spotify') {
			const uri = spotifyUri(track.sourceUrl);
			const cached = uri ? spotifyControllerCache.get(uri) : null;

			/*
			 * Safari/iOS only treats playback as user-initiated while we are still
			 * inside the original tap/click event. Controllers discovered on the page
			 * are therefore prepared ahead of time and moved into the visible player
			 * when selected. This lets the first PairOfDice play tap call Spotify's
			 * play() synchronously instead of waiting for createController().
			 */
			if (cached && cached.ready && cached.controller) {
				parkActiveSpotify();
				providerFrame.innerHTML = '';
				providerFrame.appendChild(cached.mount);
				activeSpotifyEntry = cached;
				providerController = cached.controller;
				attachSpotifyEvents(cached);
				if (autoplay && typeof cached.controller.play === 'function') {
					try { cached.controller.play(); } catch (e) {}
				}
				return true;
			}

			const mount = document.createElement('div');
			providerFrame.appendChild(mount);
			prepareSpotifyController(track.sourceUrl).then(function(entry){
				if (!entry || !entry.controller) return;
				parkActiveSpotify();
				providerFrame.innerHTML = '';
				providerFrame.appendChild(entry.mount);
				activeSpotifyEntry = entry;
				providerController = entry.controller;
				attachSpotifyEvents(entry);
				/* Desktop browsers may allow this fallback. Safari may still require
				 * one provider tap if the controller was not ready before the click. */
				if (autoplay && typeof entry.controller.play === 'function') {
					try { entry.controller.play(); } catch (e) {}
				}
			}).catch(function(){
				const iframe = document.createElement('iframe');
				iframe.src = embed.url; iframe.height = String(embed.height || 152); iframe.allow = embed.allow || 'autoplay; encrypted-media'; iframe.setAttribute('allowfullscreen','');
				mount.appendChild(iframe);
			});
			return true;
		}

		const iframe = document.createElement('iframe');
		iframe.src = embed.url;
		iframe.height = String(embed.height || 152);
		iframe.loading = 'eager';
		iframe.allow = embed.allow || 'autoplay; encrypted-media';
		iframe.setAttribute('allowfullscreen', '');
		iframe.setAttribute('title', providerName(track.source) + ' player for ' + (track.title || 'track'));
		providerFrame.appendChild(iframe);

		if (track.source === 'soundcloud') {
			getSoundCloudApi().then(function(){
				const widget = window.SC.Widget(iframe);
				providerController = widget;
				widget.bind(window.SC.Widget.Events.READY, function(){
					if (autoplay) widget.play();
				});
				widget.bind(window.SC.Widget.Events.PLAY, function(){ externalPlaying = true; updateButtons(); emitMediaProgress(0, 0, true); });
				widget.bind(window.SC.Widget.Events.PAUSE, function(){ externalPlaying = false; updateButtons(); emitMediaProgress(0, 0, false); });
				if (window.SC.Widget.Events.PLAY_PROGRESS) {
					widget.bind(window.SC.Widget.Events.PLAY_PROGRESS, function(data){
						const positionMs = data && data.currentPosition ? Number(data.currentPosition) : 0;
						widget.getDuration(function(durationMs){ emitMediaProgress(positionMs, Number(durationMs || 0), true); });
					});
				}
				widget.bind(window.SC.Widget.Events.FINISH, function(){
					externalPlaying = false; updateButtons();
					widget.getDuration(function(durationMs){ emitMediaProgress(Number(durationMs || 0), Number(durationMs || 0), false); });
					nextTrack();
				});
			});
		}
		return true;
	}

	function formatTime(seconds) {
		if (!Number.isFinite(seconds)) return '0:00';
		const mins = Math.floor(seconds / 60);
		const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
		return mins + ':' + secs;
	}

	function currentTrack() {
		return queue[index] || null;
	}

	function emitMediaProgress(positionMs, durationMs, playing) {
		const track = currentTrack();
		if (!track) return;
		const creatorId = Number(track.creatorId || track.creator_id || 0);
		const objectId = Number(track.analyticsObjectId || track.analytics_object_id || track.id || track.trackId || track.track_id || 0);
		if (!creatorId || !objectId) return;
		document.dispatchEvent(new CustomEvent('nfinite:media-progress', {
			detail: {
				creatorId: creatorId,
				objectId: objectId,
				objectType: track.analyticsObjectType || track.analytics_object_type || 'track',
				title: track.analyticsTitle || track.analytics_title || track.title || '',
				source: track.source || 'local',
				positionMs: Math.max(0, Number(positionMs || 0)),
				durationMs: Math.max(0, Number(durationMs || 0)),
				playing: !!playing,
				clockMs: performance.now()
			}
		}));
	}

	function emitMediaReset(track) {
		track = track || currentTrack();
		if (!track) return;
		const creatorId = Number(track.creatorId || track.creator_id || 0);
		const objectId = Number(track.analyticsObjectId || track.analytics_object_id || track.id || track.trackId || track.track_id || 0);
		if (!creatorId || !objectId) return;
		document.dispatchEvent(new CustomEvent('nfinite:media-reset', { detail: { creatorId: creatorId, objectId: objectId } }));
	}

	function setArtwork(target, url) {
		if (!target) return;
		target.innerHTML = url ? '<img src="' + String(url).replace(/"/g, '&quot;') + '" alt="">' : '';
	}

	function syncMediaSessionPosition() {
		/* iOS Safari already owns native <audio> lock-screen position. Repeated
		 * setPositionState() calls while the document is backgrounding can make
		 * that session less stable, so leave iOS on the native transport path. */
		if (isIOSMediaSession()) return;
		if (!('mediaSession' in navigator) || typeof navigator.mediaSession.setPositionState !== 'function') return;
		if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
		try {
			navigator.mediaSession.setPositionState({
				duration: audio.duration,
				playbackRate: Number.isFinite(audio.playbackRate) && audio.playbackRate > 0 ? audio.playbackRate : 1,
				position: Math.min(Math.max(0, audio.currentTime || 0), audio.duration)
			});
		} catch (e) {}
	}

	function reinforceBackgroundPlaybackState() {
		/*
		 * Locking a phone backgrounds the document but should not be treated as a
		 * navigation or a request to stop playback. Keep the active native audio
		 * element and Media Session explicitly marked as playing so iOS/Android can
		 * retain lock-screen transport ownership. Never call play() here because
		 * background autoplay policies still require a prior user gesture.
		 */
		if (!queue.length || audio.paused || audio.ended) return;
		/* On iPhone/iPad, do not mutate Media Session as Safari transitions the
		 * document to the lock screen. Native audio playback is the reliable path. */
		if (isIOSMediaSession()) return;
		if ('mediaSession' in navigator) {
			try { navigator.mediaSession.playbackState = 'playing'; } catch (e) {}
		}
		syncMediaSessionPosition();
	}

	function updateMetadata() {
		const track = currentTrack();
		if (!track) return;

		shell.querySelectorAll('[data-global-title]').forEach(el => el.textContent = track.title || 'Untitled Track');
		shell.querySelectorAll('[data-global-title-large]').forEach(el => el.textContent = track.title || 'Untitled Track');
		shell.querySelectorAll('[data-global-artist]').forEach(el => el.textContent = track.artist || '');
		shell.querySelectorAll('[data-global-artist-large]').forEach(el => el.textContent = track.artist || '');
		shell.querySelectorAll('[data-global-release]').forEach(el => el.textContent = track.release || '');
		setArtwork(shell.querySelector('[data-global-art]'), track.artwork);
		setArtwork(shell.querySelector('[data-global-art-large]'), track.artwork);

		if ('mediaSession' in navigator) {
			const artwork = track.artwork ? [
				{ src: track.artwork, sizes: '512x512' },
				{ src: track.artwork, sizes: '256x256' }
			] : [];
			try {
				navigator.mediaSession.metadata = new MediaMetadata({
					title: track.title || '',
					artist: track.artist || '',
					album: track.release || '',
					artwork: artwork
				});
			} catch (e) {}
		}

		renderQueue();
		bindMediaSessionHandlers();
	}


	function navigationStatePayload() {
		if (!queue.length) return null;
		const track = currentTrack();
		if (!track) return null;
		const isDirect = (track.source || 'local') === 'local' || track.source === 'internet_archive';
		return {
			queue: queue,
			index: index,
			currentTime: isDirect && Number.isFinite(audio.currentTime) ? audio.currentTime : 0,
			wasPlaying: isDirect ? !audio.paused : !!externalPlaying,
			expanded: shell.classList.contains('is-expanded') || (panel && panel.classList.contains('is-open')),
			savedAt: Date.now()
		};
	}

	function persistNavigationState(force) {
		if (restoringNavigationState) return;
		const now = Date.now();
		if (!force && now - lastNavigationPersistAt < 750) return;
		lastNavigationPersistAt = now;
		try {
			const state = navigationStatePayload();
			if (state) sessionStorage.setItem(navigationStateKey, JSON.stringify(state));
			else sessionStorage.removeItem(navigationStateKey);
		} catch (e) {}
	}

	function clearNavigationState() {
		try { sessionStorage.removeItem(navigationStateKey); } catch (e) {}
	}

	function restoreNavigationState() {
		let saved = null;
		try { saved = JSON.parse(sessionStorage.getItem(navigationStateKey) || 'null'); } catch (e) {}
		if (!saved || !Array.isArray(saved.queue) || !saved.queue.length) return;
		/* Only revive recent same-tab navigation state, not an abandoned old session. */
		if (!saved.savedAt || Date.now() - Number(saved.savedAt) > 120000) {
			clearNavigationState();
			return;
		}
		restoringNavigationState = true;
		queue = saved.queue.map(normalizeQueueItem).filter(Boolean);
		if (!queue.length) { restoringNavigationState = false; clearNavigationState(); return; }
		index = Math.max(0, Math.min(Number(saved.index) || 0, queue.length - 1));
		loadTrack(index, false);
		if (saved.expanded && panel) {
			shell.classList.add('is-expanded');
			panel.classList.add('is-open');
		}
		const track = currentTrack();
		const isDirect = track && (((track.source || 'local') === 'local') || track.source === 'internet_archive');
		if (isDirect) {
			const resumeAt = Math.max(0, Number(saved.currentTime) || 0);
			const resume = function () {
				try { if (resumeAt && Number.isFinite(audio.duration)) audio.currentTime = Math.min(resumeAt, Math.max(0, audio.duration - .25)); } catch (e) {}
				if (saved.wasPlaying) {
					pauseOtherAudio(audio);
					audio.play().catch(function () {
						/* Some mobile browsers require another tap after a full reload. */
						updateButtons();
					});
				}
			};
			if (audio.readyState >= 1) resume();
			else audio.addEventListener('loadedmetadata', resume, { once: true });
		}
		restoringNavigationState = false;
	}

	function secureShuffle(items) {
		const copy = Array.isArray(items) ? items.slice() : [];
		for (let i = copy.length - 1; i > 0; i--) {
			let r;
			if (window.crypto && window.crypto.getRandomValues) {
				const values = new Uint32Array(1);
				window.crypto.getRandomValues(values);
				r = values[0] / 4294967296;
			} else {
				r = Math.random();
			}
			const j = Math.floor(r * (i + 1));
			const temp = copy[i]; copy[i] = copy[j]; copy[j] = temp;
		}
		return copy;
	}

	function persistPreferences() {
		try {
			localStorage.setItem(preferencesKey, JSON.stringify({
				volume: audio.volume,
				repeat: repeat,
				shuffle: shuffle
			}));
		} catch (e) {}
	}

	function restorePreferences() {
		try {
			/*
			 * Remove the 0.4.2 state so an old queue cannot resurrect the
			 * persistent player after this patch is installed.
			 */
			localStorage.removeItem(legacyStateKey);

			const saved = JSON.parse(localStorage.getItem(preferencesKey) || 'null');
			if (!saved) return;

			repeat = !!saved.repeat;
			shuffle = !!saved.shuffle;

			if (typeof saved.volume === 'number') {
				audio.volume = Math.max(0, Math.min(1, saved.volume));
				volume.value = audio.volume;
			}
		} catch (e) {}
	}

	function pauseOtherAudio(currentAudio) {
		document.querySelectorAll('audio').forEach(function (candidate) {
			if (candidate !== currentAudio && !candidate.paused) {
				candidate.pause();
			}
		});
	}

	function loadTrack(newIndex, autoplay = true) {
		if (!queue.length) return;

		/* A new audio selection always wins over any video currently playing. */
		pausePageVideos();

		emitMediaReset(currentTrack());
		index = Math.max(0, Math.min(newIndex, queue.length - 1));
		const track = currentTrack();
		if (!track || !track.sourceUrl) return;

		shell.hidden = false;
		updateMetadata();
		document.dispatchEvent(new CustomEvent('nfinite:track-change', { detail: { track: track, index: index, queueLength: queue.length } }));

		if ((track.source || 'local') !== 'local' && track.source !== 'internet_archive') {
			loadExternalTrack(track, autoplay);
			currentTime.textContent = '0:00';
			duration.textContent = providerName(track.source);
			progress.value = 0;
			updateButtons();
			return;
		}

		clearProvider();
		audio.src = track.source === 'internet_archive' ? track.sourceUrl : (track.audioUrl || track.sourceUrl);
		updateButtons();

		if (autoplay) {
			pauseOtherAudio(audio);
			audio.play().catch(() => {});
		}
	}

	function normalizeQueueItem(item) {
		if (!item || typeof item !== 'object') return null;

		const source = item.source || item.sourceType || 'local';
		const audioUrl = item.audioUrl || item.audio_url || '';
		const sourceUrl = item.sourceUrl || item.source_url || (source === 'local' ? audioUrl : '');

		if (!sourceUrl) return null;

		return Object.assign({}, item, {
			source: source,
			sourceUrl: sourceUrl,
			audioUrl: audioUrl || (source === 'local' ? sourceUrl : '')
		});
	}

	function setQueue(nextQueue, startIndex = 0, autoplay = true) {
		queue = Array.isArray(nextQueue) ? nextQueue.map(normalizeQueueItem).filter(Boolean) : [];
		if (!queue.length) return;

		index = Math.max(0, Math.min(startIndex, queue.length - 1));
		loadTrack(index, autoplay);
		persistNavigationState(true);
	}

	function nextTrack() {
		if (!queue.length) return;

		if (shuffle && queue.length > 1) {
			let next = index;
			while (next === index) next = Math.floor(Math.random() * queue.length);
			loadTrack(next, true);
			return;
		}

		if (index < queue.length - 1) {
			loadTrack(index + 1, true);
		} else if (repeat) {
			loadTrack(0, true);
		}
	}

	function prevTrack() {
		if (!queue.length) return;

		if (audio.currentTime > 4) {
			audio.currentTime = 0;
			return;
		}

		loadTrack(index > 0 ? index - 1 : (repeat ? queue.length - 1 : 0), true);
	}

	/*
	 * Lock-screen / hardware Previous should always mean previous queue item.
	 * The visible in-player Previous button keeps the familiar restart-after-4s
	 * behavior, while Media Session transport controls can reliably skip tracks.
	 */
	function mediaSessionPrevTrack() {
		if (!queue.length) return;
		loadTrack(index > 0 ? index - 1 : (repeat ? queue.length - 1 : 0), true);
	}

	function isIOSMediaSession() {
		const ua = navigator.userAgent || '';
		const platform = navigator.platform || '';
		return /iPhone|iPad|iPod/i.test(ua) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	}

	function updateButtons() {
		const track = currentTrack();
		const isExternal = track && (track.source || 'local') !== 'local' && track.source !== 'internet_archive';
		const paused = isExternal ? !externalPlaying : audio.paused;

		if (playButton) playButton.textContent = paused ? '▶' : '❚❚';
		if (compactPlay) compactPlay.textContent = paused ? '▶' : '❚❚';
		if (shuffleButton) shuffleButton.classList.toggle('is-active', shuffle);
		if (repeatButton) repeatButton.classList.toggle('is-active', repeat);
	}

	function renderQueue() {
		if (!queuePanel) return;

		queuePanel.innerHTML = queue.map((track, queueIndex) => {
			const active = queueIndex === index ? ' is-active' : '';
			return '<button type="button" class="nfinite-global-queue-item' + active + '" data-queue-index="' + queueIndex + '">' +
				'<span>' + String(queueIndex + 1).padStart(2, '0') + '</span>' +
				'<span><strong>' + escapeHtml(track.title || 'Untitled Track') + '</strong><small>' + escapeHtml(track.artist || '') + '</small></span>' +
				'<span>▶</span>' +
			'</button>';
		}).join('');
	}

	function resetMetadata() {
		shell.querySelectorAll('[data-global-title], [data-global-title-large]').forEach(el => el.textContent = 'Nothing playing');
		shell.querySelectorAll('[data-global-artist], [data-global-artist-large], [data-global-release]').forEach(el => el.textContent = '');
		setArtwork(shell.querySelector('[data-global-art]'), '');
		setArtwork(shell.querySelector('[data-global-art-large]'), '');
		clearProvider();

		currentTime.textContent = '0:00';
		duration.textContent = '0:00';
		progress.value = 0;
		if (queuePanel) {
			queuePanel.innerHTML = '';
			queuePanel.hidden = true;
		}

		if ('mediaSession' in navigator) {
			try {
				navigator.mediaSession.metadata = null;
				navigator.mediaSession.playbackState = 'none';
			} catch (e) {}
		}
	}

	function dismissPlayer() {
		audio.pause();

		try {
			audio.currentTime = 0;
		} catch (e) {}

		audio.removeAttribute('src');
		audio.load();

		queue = [];
		index = 0;

		shell.classList.remove('is-expanded');
		if (panel) panel.classList.remove('is-open');

		resetMetadata();
		updateButtons();
		shell.hidden = true;

		/*
		 * Defensive cleanup for sites that visited while 0.4.2 was active.
		 */
		try {
			localStorage.removeItem(legacyStateKey);
		} catch (e) {}
		clearNavigationState();
	}

	function escapeHtml(value) {
		const div = document.createElement('div');
		div.textContent = value;
		return div.innerHTML;
	}

	/*
	 * One-audio-at-a-time rule.
	 *
	 * This catches the Nfinite global player, legacy profile audio players, and
	 * other native HTMLAudioElements on the page. Starting one pauses the rest.
	 */
	document.addEventListener('play', function (event) {
		if (event.target instanceof HTMLAudioElement) {
			pauseOtherAudio(event.target);
			if (event.target !== audio) pauseGlobalForPageVideo();
			return;
		}
		if (event.target instanceof HTMLVideoElement) {
			pauseGlobalForPageVideo();
		}
	}, true);


	/*
	 * Bind queue/release controls in the current document and again after a
	 * WPNfinite continuous navigation swaps page content. Bound markers prevent
	 * duplicate handlers when the same DOM survives.
	 */
	function bindPagePlayers(root) {
		root = root || document;
		root.querySelectorAll('[data-nfinite-track-queue]').forEach(function (queueElement) {
			if (queueElement.dataset.nfinitePersistentBound === '1') return;
			queueElement.dataset.nfinitePersistentBound = '1';
			let elementQueue = [];
			try { elementQueue = JSON.parse(queueElement.dataset.queue || '[]'); } catch (e) {}
			queueElement.querySelectorAll('[data-play-queue-index]').forEach(function (button) {
				bindPress(button, function (event) {
					if (event) event.preventDefault();
					shuffle = false;
					setQueue(elementQueue, Number(button.dataset.playQueueIndex) || 0, true);
				});
			});
		});

		root.querySelectorAll('[data-nfinite-radio]').forEach(function (radio) {
			if (radio.dataset.nfiniteRadioBound === '1') return;
			radio.dataset.nfiniteRadioBound = '1';
			let radioQueue = [];
			try { radioQueue = JSON.parse(radio.dataset.queue || '[]'); } catch (e) {}
			/* PHP output may be page-cached. Shuffle in the browser on every visit so Radio never inherits a cached order. */
			// Programming supplies the mix with artist spacing and editorial priority.
			const start = radio.querySelector('[data-radio-play]');
			const toggle = radio.querySelector('[data-radio-toggle]');
			const prev = radio.querySelector('[data-radio-prev]');
			const next = radio.querySelector('[data-radio-next]');
			if (start) bindPress(start, function (event) {
				if (event) event.preventDefault();
				shuffle = false;
				setQueue(radioQueue, 0, true);
			});
			if (toggle) bindPress(toggle, function (event) {
				if (event) event.preventDefault();
				if (!queue.length && radioQueue.length) { setQueue(radioQueue, 0, true); return; }
				if (audio.paused) { pauseOtherAudio(audio); audio.play().catch(function(){}); } else { audio.pause(); }
			});
			if (prev) prev.addEventListener('click', mediaSessionPrevTrack);
			if (next) next.addEventListener('click', nextTrack);
		});

		root.querySelectorAll('[data-nfinite-release-player]').forEach(function (releasePlayer) {
			if (releasePlayer.dataset.nfinitePersistentBound === '1') return;
			releasePlayer.dataset.nfinitePersistentBound = '1';
			let releaseQueue = [];
			try { releaseQueue = JSON.parse(releasePlayer.dataset.queue || '[]'); } catch (e) {}
			const playRelease = releasePlayer.querySelector('[data-play-release]');
			const shuffleRelease = releasePlayer.querySelector('[data-shuffle-release]');
			if (playRelease) bindPress(playRelease, function (event) {
				if (event) event.preventDefault();
				shuffle = false;
				setQueue(releaseQueue, 0, true);
			});
			if (shuffleRelease) shuffleRelease.addEventListener('click', function () {
				shuffle = true;
				const start = releaseQueue.length > 1 ? Math.floor(Math.random() * releaseQueue.length) : 0;
				setQueue(releaseQueue, start, true);
			});
			releasePlayer.querySelectorAll('[data-play-track-index]').forEach(function (button) {
				bindPress(button, function (event) {
					if (event) event.preventDefault();
					shuffle = false;
					setQueue(releaseQueue, Number(button.dataset.playTrackIndex) || 0, true);
				});
			});
		});
		preloadSpotifyControllers();
		bindYouTubePlayers(root);
	}

	const radioHistory = [];
	function syncRadioUi(track) {
		document.querySelectorAll('[data-nfinite-radio]').forEach(function(radio) {
			if (!track) return;
			const title = radio.querySelector('[data-radio-title]');
			const artist = radio.querySelector('[data-radio-artist]');
			const release = radio.querySelector('[data-radio-release]');
			const art = radio.querySelector('[data-radio-art]');
			const creator = radio.querySelector('[data-radio-creator]');
			if (title) title.textContent = track.title || 'Untitled Track';
			if (artist) artist.textContent = track.artist || '';
			if (release) release.textContent = track.release || '';
			if (art) art.innerHTML = track.artwork ? '<img src="' + String(track.artwork).replace(/"/g,'&quot;') + '" alt="">' : '';
			if (creator) {
				if (track.creatorUrl) { creator.href = track.creatorUrl; creator.hidden = false; }
				else { creator.hidden = true; }
			}
			const recent = radio.querySelector('[data-radio-recent]');
			if (recent) {
				const key = String(track.id || '') + '|' + String(track.sourceUrl || '');
				const existing = radioHistory.findIndex(function(item){ return item.key === key; });
				if (existing >= 0) radioHistory.splice(existing, 1);
				radioHistory.unshift({ key:key, title:track.title || 'Untitled Track', artist:track.artist || '' });
				if (radioHistory.length > 8) radioHistory.length = 8;
				recent.innerHTML = radioHistory.map(function(item){ return '<div class="nfinite-radio__recent-item"><strong>' + escapeHtml(item.title) + '</strong><span>' + escapeHtml(item.artist) + '</span></div>'; }).join('');
			}
		});
	}
	document.addEventListener('nfinite:track-change', function(event){ syncRadioUi(event && event.detail ? event.detail.track : null); });
	audio.addEventListener('play', function(){
		document.dispatchEvent(new CustomEvent('nfinite:audio-play', { detail: { source: 'global' } }));
		document.querySelectorAll('[data-radio-toggle]').forEach(function(button){ button.textContent='❚❚'; });
	});
	audio.addEventListener('pause', function(){ document.querySelectorAll('[data-radio-toggle]').forEach(function(button){ button.textContent='▶'; }); });

	bindPagePlayers(document);
	document.addEventListener('wpnfinite:navigation-complete', function (event) {
		bindPagePlayers(event && event.detail && event.detail.primary ? event.detail.primary : document);
		// The global player DOM lives outside #primary, so playback, queue, current
		// time and provider controller remain untouched during the content swap.
	});

	bindPress(playButton, function (event) {
		if (event) event.preventDefault();
		const track = currentTrack();
		if (!track) return;
		if ((track.source || 'local') !== 'local' && track.source !== 'internet_archive') {
			if (providerController && typeof providerController.play === 'function') {
				if (externalPlaying && typeof providerController.pause === 'function') { providerController.pause(); externalPlaying = false; }
				else { pausePageVideos(); document.dispatchEvent(new CustomEvent('nfinite:audio-play', { detail: { source: 'provider' } })); providerController.play(); externalPlaying = true; }
				updateButtons();
			}
			return;
		}

		if (audio.paused) {
			pauseOtherAudio(audio);
			audio.play().catch(() => {});
		} else {
			audio.pause();
		}
	});

	bindPress(compactPlay, function (event) {
		if (event) {
			event.preventDefault();
			event.stopPropagation();
		}
		/* Invoke the same playback path synchronously within the user gesture. */
		if (!queue.length) return;
		const track = currentTrack();
		if (!track) return;
		if ((track.source || 'local') !== 'local' && track.source !== 'internet_archive') {
			if (providerController && typeof providerController.play === 'function') {
				if (externalPlaying && typeof providerController.pause === 'function') { providerController.pause(); externalPlaying = false; }
				else { pausePageVideos(); document.dispatchEvent(new CustomEvent('nfinite:audio-play', { detail: { source: 'provider' } })); providerController.play(); externalPlaying = true; }
				updateButtons();
			}
			return;
		}
		if (audio.paused) {
			pauseOtherAudio(audio);
			audio.play().catch(function () {});
		} else {
			audio.pause();
		}
	});

	prevButton.addEventListener('click', prevTrack);
	nextButton.addEventListener('click', nextTrack);

	shuffleButton.addEventListener('click', function () {
		shuffle = !shuffle;
		updateButtons();
		persistPreferences();
	});

	repeatButton.addEventListener('click', function () {
		repeat = !repeat;
		updateButtons();
		persistPreferences();
	});

	progress.addEventListener('input', function () {
		const track = currentTrack();
		if (track && (track.source || 'local') !== 'local') return;
		if (audio.duration) {
			audio.currentTime = (Number(progress.value) / 100) * audio.duration;
		}
	});

	volume.addEventListener('input', function () {
		audio.volume = Number(volume.value);
		persistPreferences();
	});

	audio.addEventListener('play', function () {
		bindMediaSessionHandlers();
		updateButtons();
		emitMediaProgress(audio.currentTime * 1000, (audio.duration || 0) * 1000, true);
		if ('mediaSession' in navigator) {
			try { navigator.mediaSession.playbackState = 'playing'; } catch (e) {}
		}
		persistNavigationState(true);
	});

	audio.addEventListener('pause', function () {
		updateButtons();
		emitMediaProgress(audio.currentTime * 1000, (audio.duration || 0) * 1000, false);
		if ('mediaSession' in navigator) {
			try { navigator.mediaSession.playbackState = 'paused'; } catch (e) {}
		}
		persistNavigationState(true);
	});

	audio.addEventListener('ended', function () {
		emitMediaProgress((audio.duration || audio.currentTime || 0) * 1000, (audio.duration || 0) * 1000, false);
		nextTrack();
	});

	audio.addEventListener('loadedmetadata', function () {
		bindMediaSessionHandlers();
		duration.textContent = formatTime(audio.duration);
		syncMediaSessionPosition();
	});

	audio.addEventListener('timeupdate', function () {
		currentTime.textContent = formatTime(audio.currentTime);
		duration.textContent = formatTime(audio.duration);
		progress.value = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
		emitMediaProgress(audio.currentTime * 1000, (audio.duration || 0) * 1000, !audio.paused);
		syncMediaSessionPosition();
		persistNavigationState(false);
	});

	queueToggle.addEventListener('click', function () {
		queuePanel.hidden = !queuePanel.hidden;
	});

	queuePanel.addEventListener('click', function (event) {
		const button = event.target.closest('[data-queue-index]');
		if (button) loadTrack(Number(button.dataset.queueIndex) || 0, true);
	});

	expand.addEventListener('click', function () {
		shell.classList.add('is-expanded');
		panel.classList.add('is-open');
	});

	collapse.addEventListener('click', function () {
		shell.classList.remove('is-expanded');
		panel.classList.remove('is-open');
	});

	if (dismiss) {
		dismiss.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			dismissPlayer();
		});
	}


	function bindMediaSessionHandlers() {
		if (!('mediaSession' in navigator)) return;

		try {
			navigator.mediaSession.setActionHandler('play', () => {
				pauseOtherAudio(audio);
				return audio.play();
			});
			navigator.mediaSession.setActionHandler('pause', () => audio.pause());
			navigator.mediaSession.setActionHandler('previoustrack', mediaSessionPrevTrack);
			navigator.mediaSession.setActionHandler('nexttrack', nextTrack);

			/*
			 * iOS lock-screen transport controls are especially sensitive to the
			 * actions advertised by the active media session. For iPhone/iPad we
			 * intentionally expose music-style transport only: play, pause, previous
			 * and next. Do not advertise any seek action, including seekto.
			 */
			if (isIOSMediaSession()) {
				try { navigator.mediaSession.setActionHandler('seekbackward', null); } catch (e) {}
				try { navigator.mediaSession.setActionHandler('seekforward', null); } catch (e) {}
				try { navigator.mediaSession.setActionHandler('seekto', null); } catch (e) {}
			} else {
				navigator.mediaSession.setActionHandler('seekbackward', details => {
					audio.currentTime = Math.max(0, audio.currentTime - (details.seekOffset || 10));
				});
				navigator.mediaSession.setActionHandler('seekforward', details => {
					audio.currentTime = Math.min(audio.duration || Infinity, audio.currentTime + (details.seekOffset || 10));
				});
				navigator.mediaSession.setActionHandler('seekto', details => {
					if (typeof details.seekTime === 'number') audio.currentTime = details.seekTime;
				});
			}
		} catch (e) {}
	}

	/* Register once at startup, then refresh after track/playback activation. */
	bindMediaSessionHandlers();

	preloadSpotifyControllers();
	restorePreferences();
	restoreNavigationState();
	updateButtons();

	/* Save immediately before same-origin navigation/full unload. */
	document.addEventListener('click', function (event) {
		const link = event.target.closest && event.target.closest('a[href]');
		if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
		try {
			const url = new URL(link.href, window.location.href);
			if (url.origin === window.location.origin && url.href !== window.location.href) persistNavigationState(true);
		} catch (e) {}
	}, true);
	document.addEventListener('visibilitychange', function () {
		/* Never pause, reload, or replace the active <audio> element when a phone
		 * locks. On iOS we intentionally leave the native session untouched. */
		if (document.visibilityState === 'hidden') {
			persistNavigationState(true);
			reinforceBackgroundPlaybackState();
		}
	});
	window.addEventListener('pagehide', function () { persistNavigationState(true); });
	window.addEventListener('beforeunload', function () { persistNavigationState(true); });

	/* The player starts only after a user action; WPNfinite navigation then keeps this instance alive. */
});

/* 0.28.0 PairOfDice Radio page bridge. Uses the same persistent global queue. */
