document.addEventListener('DOMContentLoaded', function () {
    let shell = null;
    let frame = null;
    let lastTrigger = null;
    let currentUrl = '';
    let minimized = false;

    function withApiParams(url) {
        try {
            const parsed = new URL(url, window.location.href);
            const host = parsed.hostname.replace(/^www\./, '');
            if (host === 'youtube.com' || host === 'youtube-nocookie.com' || host === 'm.youtube.com') {
                parsed.searchParams.set('enablejsapi', '1');
                parsed.searchParams.set('playsinline', '1');
                parsed.searchParams.set('origin', window.location.origin);
            }
            return parsed.toString();
        } catch (e) {
            return url;
        }
    }

    function ensureShell() {
        if (shell && document.documentElement.contains(shell)) return shell;

        shell = document.createElement('div');
        shell.className = 'nfinite-video-player';
        shell.setAttribute('data-nfinite-video-player', '');
        shell.setAttribute('aria-hidden', 'true');
        shell.innerHTML =
            '<div class="nfinite-video-player__backdrop" data-video-close></div>' +
            '<div class="nfinite-video-player__dialog" role="dialog" aria-modal="true" aria-label="Video player">' +
                '<div class="nfinite-video-player__toolbar">' +
                    '<button type="button" class="nfinite-video-player__minimize" data-video-minimize aria-label="Minimize video">−</button>' +
                    '<button type="button" class="nfinite-video-player__restore" data-video-restore aria-label="Expand video">↗</button>' +
                    '<button type="button" class="nfinite-video-player__close" data-video-close aria-label="Close video">×</button>' +
                '</div>' +
                '<div class="nfinite-video-player__stage" data-video-restore>' +
                    '<iframe title="Video player" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>' +
                '</div>' +
            '</div>';

        document.body.appendChild(shell);
        frame = shell.querySelector('iframe');
        frame.addEventListener('load', function () { setTimeout(registerPlayerEvents, 250); });

        shell.addEventListener('click', function (event) {
            if (event.target.closest('[data-video-close]')) {
                closeVideo();
                return;
            }
            if (event.target.closest('[data-video-minimize]')) {
                minimizeVideo();
                return;
            }
            if (event.target.closest('[data-video-restore]') && minimized) {
                restoreVideo();
            }
        });

        return shell;
    }

    function sendYouTubeCommand(func) {
        if (!frame || !frame.contentWindow) return;
        try {
            frame.contentWindow.postMessage(JSON.stringify({
                event: 'command',
                func: func,
                args: []
            }), '*');
        } catch (e) {}
    }

    function sendVimeoPause() {
        if (!frame || !frame.contentWindow) return;
        try {
            frame.contentWindow.postMessage(JSON.stringify({ method: 'pause' }), '*');
        } catch (e) {}
    }

    function pauseVideo() {
        if (!frame || !currentUrl) return;
        sendYouTubeCommand('pauseVideo');
        sendVimeoPause();
        shell.classList.add('is-paused-by-audio');
        document.dispatchEvent(new CustomEvent('nfinite:video-pause'));
    }

    function openVideo(trigger, url) {
        if (!url) return;
        ensureShell();

        document.dispatchEvent(new CustomEvent('nfinite:video-play', { detail: { url: url } }));
        document.dispatchEvent(new CustomEvent('nfinite:video-open', { detail: { url: url, trigger: trigger || null } }));

        lastTrigger = trigger || null;
        minimized = false;
        currentUrl = url;
        shell.classList.remove('is-minimized', 'is-paused-by-audio');
        shell.classList.add('is-open');
        shell.setAttribute('aria-hidden', 'false');
        document.body.classList.add('nfinite-video-player-open');

        const autoplayUrl = withApiParams(url);
        const joiner = autoplayUrl.indexOf('?') > -1 ? '&' : '?';
        frame.src = 'about:blank';
        frame.src = autoplayUrl + joiner + 'autoplay=1';
    }

    function minimizeVideo() {
        if (!shell || !shell.classList.contains('is-open')) return;
        minimized = true;
        shell.classList.add('is-minimized');
        document.body.classList.remove('nfinite-video-player-open');
    }

    function restoreVideo() {
        if (!shell || !shell.classList.contains('is-open')) return;
        minimized = false;
        shell.classList.remove('is-minimized');
        document.body.classList.add('nfinite-video-player-open');
    }

    function closeVideo() {
        if (!shell) return;
        document.dispatchEvent(new CustomEvent('nfinite:video-close')); 
        if (frame) frame.src = 'about:blank';
        currentUrl = '';
        minimized = false;
        shell.classList.remove('is-open', 'is-minimized', 'is-paused-by-audio');
        shell.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('nfinite-video-player-open');
        if (lastTrigger && document.documentElement.contains(lastTrigger)) {
            try { lastTrigger.focus({ preventScroll: true }); } catch (e) {}
        }
    }

    function registerPlayerEvents() {
        if (!frame || !frame.contentWindow) return;
        try {
            frame.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 'nfinite-video' }), '*');
            frame.contentWindow.postMessage(JSON.stringify({ method: 'addEventListener', value: 'playProgress' }), '*');
            frame.contentWindow.postMessage(JSON.stringify({ method: 'addEventListener', value: 'play' }), '*');
            frame.contentWindow.postMessage(JSON.stringify({ method: 'addEventListener', value: 'pause' }), '*');
            frame.contentWindow.postMessage(JSON.stringify({ method: 'addEventListener', value: 'finish' }), '*');
        } catch (e) {}
    }

    window.addEventListener('message', function (event) {
        if (!frame || event.source !== frame.contentWindow) return;
        let data = event.data;
        try { if (typeof data === 'string') data = JSON.parse(data); } catch (e) { return; }
        if (!data || typeof data !== 'object') return;
        if (data.event === 'infoDelivery' && data.info) {
            const info = data.info;
            const detail = {};
            if (typeof info.currentTime === 'number') detail.position = info.currentTime;
            if (typeof info.duration === 'number') detail.duration = info.duration;
            if (typeof info.playerState === 'number') {
                detail.playing = info.playerState === 1;
                if (info.playerState === 1) document.dispatchEvent(new CustomEvent('nfinite:video-playing'));
                if (info.playerState === 2) document.dispatchEvent(new CustomEvent('nfinite:video-pause'));
                if (info.playerState === 0) document.dispatchEvent(new CustomEvent('nfinite:video-close'));
            }
            document.dispatchEvent(new CustomEvent('nfinite:video-time', { detail: detail }));
        }
        if (data.event === 'playProgress' && data.data) {
            document.dispatchEvent(new CustomEvent('nfinite:video-time', { detail: { position: Number(data.data.seconds || 0), duration: Number(data.data.duration || 0), playing: true } }));
        } else if (data.event === 'play') {
            document.dispatchEvent(new CustomEvent('nfinite:video-playing'));
        } else if (data.event === 'pause') {
            document.dispatchEvent(new CustomEvent('nfinite:video-pause'));
        } else if (data.event === 'finish') {
            document.dispatchEvent(new CustomEvent('nfinite:video-close'));
        }
    });

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-video-embed]');
        if (!trigger) return;
        const url = trigger.getAttribute('data-video-embed');
        if (!url) return;
        event.preventDefault();
        openVideo(trigger, url);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !shell || !shell.classList.contains('is-open')) return;
        if (minimized) return;
        minimizeVideo();
    });

    /*
     * One active media session:
     * when Nfinite audio starts, pause the persistent video without destroying it.
     */
    document.addEventListener('nfinite:audio-play', function () {
        pauseVideo();
    });

    /*
     * Native/legacy audio outside the global player also wins over video.
     */
    document.addEventListener('play', function (event) {
        if (event.target instanceof HTMLAudioElement) pauseVideo();
    }, true);

    /*
     * WPNfinite swaps #primary only. This shell is attached to body and survives
     * navigation, so intentionally do nothing on navigation complete.
     */
});