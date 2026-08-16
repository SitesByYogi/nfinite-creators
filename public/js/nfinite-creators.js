document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('[data-nfinite-player]').forEach(function (player) {
		const audio = player.querySelector('[data-audio]');
		const play = player.querySelector('[data-play]');
		const progress = player.querySelector('[data-progress]');
		const volume = player.querySelector('[data-volume]');
		const current = player.querySelector('[data-current-time]');
		const duration = player.querySelector('[data-duration]');
		const title = player.querySelector('[data-track-title]');
		const type = player.querySelector('[data-track-type]');
		const cover = player.querySelector('[data-cover]');

		if (!audio || !play) return;

		function formatTime(seconds) {
			if (!Number.isFinite(seconds)) return '0:00';
			const mins = Math.floor(seconds / 60);
			const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
			return mins + ':' + secs;
		}

		function updateButton() {
			play.textContent = audio.paused ? '▶' : '❚❚';
		}

		play.addEventListener('click', function () {
			if (audio.paused) audio.play();
			else audio.pause();
		});

		audio.addEventListener('play', updateButton);
		audio.addEventListener('pause', updateButton);
		audio.addEventListener('loadedmetadata', function () {
			duration.textContent = formatTime(audio.duration);
		});
		audio.addEventListener('timeupdate', function () {
			current.textContent = formatTime(audio.currentTime);
			progress.value = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
		});
		audio.addEventListener('ended', function () {
			const active = player.querySelector('[data-track].is-active');
			const next = active && active.nextElementSibling && active.nextElementSibling.matches('[data-track]') ? active.nextElementSibling : null;
			if (next) {
				next.click();
				audio.play();
			}
		});

		progress.addEventListener('input', function () {
			if (audio.duration) audio.currentTime = (parseFloat(progress.value) / 100) * audio.duration;
		});
		volume.addEventListener('input', function () {
			audio.volume = parseFloat(volume.value);
		});

		player.querySelectorAll('[data-track]').forEach(function (trackButton) {
			trackButton.addEventListener('click', function () {
				player.querySelectorAll('[data-track]').forEach(function (item) { item.classList.remove('is-active'); });
				trackButton.classList.add('is-active');

				const src = trackButton.dataset.src || '';
				audio.src = src;
				title.textContent = trackButton.dataset.title || 'Untitled Track';
				type.textContent = trackButton.dataset.type || 'Track';

				const coverUrl = trackButton.dataset.cover || '';
				cover.innerHTML = coverUrl ? '<img src="' + coverUrl.replace(/"/g, '&quot;') + '" alt="">' : '';
				audio.play();
			});
		});
	});

	document.querySelectorAll('[data-track-editor]').forEach(function (editor) {
		const add = editor.querySelector('[data-add-track]');
		if (!add) return;

		add.addEventListener('click', function () {
			const rows = editor.querySelectorAll('[data-track-row]');
			const index = rows.length;
			const row = document.createElement('div');
			row.className = 'nfinite-track-row';
			row.setAttribute('data-track-row', '');
			row.innerHTML = `
				<div class="nfinite-track-row__head"><strong>Track</strong><button type="button" class="nfinite-track-remove" data-remove-track>Remove</button></div>
				<div class="nfinite-form-grid">
					<label><span>Title</span><input type="text" name="tracks[${index}][title]"></label>
					<label><span>Track Type</span><select name="tracks[${index}][type]"><option>Song</option><option>Beat</option><option>Demo</option><option>Mix</option><option>Production Reel</option><option>Other</option></select></label>
					<label class="nfinite-form-full"><span>Existing Audio URL</span><input type="url" name="tracks[${index}][audio_url]"></label>
					<label><span>Upload Audio</span><input type="file" name="track_audio_${index}" accept="audio/*"></label>
					<label><span>Cover Art URL</span><input type="url" name="tracks[${index}][cover_url]"></label>
					<label><span>Credits / Notes</span><textarea name="tracks[${index}][credits]" rows="3"></textarea></label>
				</div>`;
			editor.insertBefore(row, add);
		});

		editor.addEventListener('click', function (event) {
			if (!event.target.matches('[data-remove-track]')) return;
			const row = event.target.closest('[data-track-row]');
			if (row) row.remove();
		});
	});

	document.querySelectorAll('[data-video-editor]').forEach(function (editor) {
		const add = editor.querySelector('[data-add-video]');
		if (!add) return;

		function renumberVideos() {
			editor.querySelectorAll('[data-video-row]').forEach(function (row, index) {
				const number = row.querySelector('[data-video-number]');
				if (number) number.textContent = index + 1;

				row.querySelectorAll('[name]').forEach(function (field) {
					field.name = field.name.replace(/videos\[\d+\]/, 'videos[' + index + ']');
				});
			});
		}

		add.addEventListener('click', function () {
			const index = editor.querySelectorAll('[data-video-row]').length;
			const row = document.createElement('div');
			row.className = 'nfinite-video-row';
			row.setAttribute('data-video-row', '');
			row.innerHTML = `
				<div class="nfinite-track-row__head">
					<strong>Video <span data-video-number>${index + 1}</span></strong>
					<button type="button" class="nfinite-track-remove" data-remove-video>Remove</button>
				</div>
				<div class="nfinite-form-grid">
					<label><span>Title</span><input type="text" name="videos[${index}][title]"></label>
					<label><span>Video Type</span><select name="videos[${index}][type]"><option>Music Video</option><option>Performance</option><option>Interview</option><option>Reel</option><option>Behind the Scenes</option><option>Tutorial</option><option>Showreel</option><option>Other</option></select></label>
					<label class="nfinite-form-full"><span>YouTube / Vimeo URL</span><input type="url" name="videos[${index}][url]" placeholder="https://www.youtube.com/watch?v=..."></label>
					<label class="nfinite-form-full"><span>Description</span><textarea name="videos[${index}][description]" rows="3"></textarea></label>
					<label class="nfinite-form-full nfinite-toggle"><input type="checkbox" data-frontend-featured-video name="videos[${index}][featured]" value="1"><span>Featured Video</span></label>
				</div>`;
			editor.insertBefore(row, add);
			renumberVideos();
		});

		editor.addEventListener('click', function (event) {
			if (!event.target.matches('[data-remove-video]')) return;
			const row = event.target.closest('[data-video-row]');
			if (row) row.remove();
			renumberVideos();
		});

		editor.addEventListener('change', function (event) {
			if (!event.target.matches('[data-frontend-featured-video]') || !event.target.checked) return;
			editor.querySelectorAll('[data-frontend-featured-video]').forEach(function (checkbox) {
				if (checkbox !== event.target) checkbox.checked = false;
			});
		});

		renumberVideos();
	});

});
