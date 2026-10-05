document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('[data-nfinite-multiselect]').forEach(function (field) {
		const trigger = field.querySelector('[data-nfinite-multiselect-trigger]');
		const menu = field.querySelector('[data-nfinite-multiselect-menu]');
		const label = field.querySelector('[data-nfinite-multiselect-label]');
		const options = field.querySelectorAll('[data-nfinite-multiselect-option]');
		const search = field.querySelector('[data-nfinite-multiselect-search]');
		const clear = field.querySelector('[data-nfinite-multiselect-clear]');
		const empty = field.querySelector('[data-nfinite-multiselect-empty]');
		if (!trigger || !menu || !label) return;

		function updateLabel() {
			const selected = Array.from(options).filter(function (option) { return option.checked; }).map(function (option) {
				const optionLabel = option.closest('.nfinite-multiselect__option');
				const text = optionLabel ? optionLabel.querySelector('span') : null;
				return text ? text.textContent.trim() : '';
			}).filter(Boolean);
			label.textContent = selected.length ? selected.join(', ') : 'Choose creator types';
		}

		function filterOptions() {
			const query = search ? search.value.trim().toLowerCase() : '';
			let visible = 0;
			options.forEach(function (option) {
				const optionLabel = option.closest('.nfinite-multiselect__option');
				if (!optionLabel) return;
				const text = optionLabel.textContent.trim().toLowerCase();
				const show = !query || text.indexOf(query) !== -1;
				optionLabel.hidden = !show;
				if (show) visible++;
			});
			if (empty) empty.hidden = visible !== 0;
		}

		function closeMenu() {
			menu.hidden = true;
			field.classList.remove('is-open');
			trigger.setAttribute('aria-expanded', 'false');
		}

		trigger.addEventListener('click', function () {
			const willOpen = menu.hidden;
			document.querySelectorAll('[data-nfinite-multiselect]').forEach(function (other) {
				if (other !== field) {
					const otherMenu = other.querySelector('[data-nfinite-multiselect-menu]');
					const otherTrigger = other.querySelector('[data-nfinite-multiselect-trigger]');
					if (otherMenu) otherMenu.hidden = true;
					if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
					other.classList.remove('is-open');
				}
			});
			menu.hidden = !willOpen;
			field.classList.toggle('is-open', willOpen);
			trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			if (willOpen && search) { window.setTimeout(function () { search.focus(); }, 0); }
		});

		if (search) search.addEventListener('input', filterOptions);
		if (clear) clear.addEventListener('click', function () { options.forEach(function (option) { option.checked = false; }); updateLabel(); if (search) { search.value = ''; search.focus(); } filterOptions(); });
		options.forEach(function (option) { option.addEventListener('change', updateLabel); });
		document.addEventListener('click', function (event) { if (!field.contains(event.target)) closeMenu(); });
		document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeMenu(); });
		updateLabel();
		filterOptions();
	});
	document.querySelectorAll('[data-studio-tabs]').forEach(function (tabs) {
		const dashboard = tabs.closest('.nfinite-creator-dashboard');
		if (!dashboard) return;
		const buttons = tabs.querySelectorAll('[data-studio-tab]');
		const panels = dashboard.querySelectorAll('[data-studio-panel]');
		const groups = tabs.querySelectorAll('[data-studio-group]');

		function closeGroups(exceptGroup) {
			groups.forEach(function (group) {
				if (exceptGroup && group === exceptGroup) return;
				const menu = group.querySelector('[data-studio-group-menu]');
				const trigger = group.querySelector('[data-studio-group-trigger]');
				if (menu) menu.hidden = true;
				if (trigger) trigger.setAttribute('aria-expanded', 'false');
				group.classList.remove('is-open');
			});
		}

		function activate(name) {
			buttons.forEach(function (button) { button.classList.toggle('is-active', button.dataset.studioTab === name); });
			panels.forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.studioPanel === name); });
			groups.forEach(function (group) {
				const child = group.querySelector('[data-studio-tab="' + name + '"]');
				const trigger = group.querySelector('[data-studio-group-trigger]');
				group.classList.toggle('has-active-child', !!child);
				if (trigger) trigger.classList.toggle('is-active', !!child);
			});
			closeGroups();
			try { window.sessionStorage.setItem('nfiniteStudioTab', name); } catch (e) {}
		}

		buttons.forEach(function (button) {
			button.addEventListener('click', function () { activate(button.dataset.studioTab); });
		});

		groups.forEach(function (group) {
			const trigger = group.querySelector('[data-studio-group-trigger]');
			const menu = group.querySelector('[data-studio-group-menu]');
			if (!trigger || !menu) return;
			trigger.addEventListener('click', function (event) {
				event.stopPropagation();
				const opening = menu.hidden;
				closeGroups(group);
				menu.hidden = !opening;
				trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
				group.classList.toggle('is-open', opening);
			});
		});

		document.addEventListener('click', function (event) {
			if (!tabs.contains(event.target)) closeGroups();
		});
		document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeGroups(); });

		let saved = '';
		try { saved = window.sessionStorage.getItem('nfiniteStudioTab') || ''; } catch (e) {}
		if (saved && tabs.querySelector('[data-studio-tab="' + saved + '"]')) activate(saved);
	});

	document.querySelectorAll('[data-nfinite-player]').forEach(function (player) {
		const audio = player.querySelector('[data-audio]');
		const play = player.querySelector('[data-play]');
		const progress = player.querySelector('[data-progress]');
		const current = player.querySelector('[data-current-time]');
		const duration = player.querySelector('[data-duration]');
		const title = player.querySelector('[data-track-title]');
		const type = player.querySelector('[data-track-type]');
		const cover = player.querySelector('[data-cover]');
        const providerPlayer = player.querySelector('[data-provider-player]'); const sourceLabel = player.querySelector('[data-source-label]'); const nativeControls = player.querySelector('.nfinite-audio-controls');

		if (!audio || !play) return;

		function formatTime(seconds) {
			if (!Number.isFinite(seconds)) return '0:00';
			const mins = Math.floor(seconds / 60);
			const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
			return mins + ':' + secs;
		}

		function updateButton() { play.textContent = audio.paused ? '▶' : '❚❚'; }

		function activeTrackButton() {
			const active = player.querySelector('[data-track].is-active [data-track-play]');
			return active || player.querySelector('[data-track-play]');
		}

		function emitLegacyMediaProgress(playing, forcePosition, forceDuration) {
			const btn = activeTrackButton();
			if (!btn) return;
			const creatorId = Number(btn.dataset.analyticsCreatorId || 0);
			const objectId = Number(btn.dataset.analyticsObjectId || 0);
			if (!creatorId || !objectId) return;
			const sourceType = btn.dataset.sourceType || 'local';
			// The legacy profile player can report authoritative progress for native
			// HTML5 audio. Embedded providers are cross-origin and are intentionally
			// not guessed here.
			if (sourceType !== 'local' && sourceType !== 'internet_archive') return;
			const position = Number.isFinite(forcePosition) ? forcePosition : (Number(audio.currentTime || 0) * 1000);
			const length = Number.isFinite(forceDuration) ? forceDuration : (Number.isFinite(audio.duration) ? audio.duration * 1000 : 0);
			document.dispatchEvent(new CustomEvent('nfinite:media-progress', {
				detail: {
					creatorId: creatorId,
					objectId: objectId,
					objectType: btn.dataset.analyticsObjectType || 'legacy_track',
					title: btn.dataset.title || 'Untitled Track',
					source: sourceType,
					positionMs: Math.max(0, position),
					durationMs: Math.max(0, length),
					playing: !!playing,
					clockMs: performance.now()
				}
			}));
		}

		function emitLegacyMediaReset(btn) {
			btn = btn || activeTrackButton();
			if (!btn) return;
			const creatorId = Number(btn.dataset.analyticsCreatorId || 0);
			const objectId = Number(btn.dataset.analyticsObjectId || 0);
			if (!creatorId || !objectId) return;
			document.dispatchEvent(new CustomEvent('nfinite:media-reset', { detail: { creatorId: creatorId, objectId: objectId } }));
		}

        function loadProvider(btn){ const sourceType=(btn.dataset.sourceType||'local'); const external=sourceType!=='local'&&sourceType!=='internet_archive'; if(sourceLabel)sourceLabel.textContent=btn.dataset.sourceLabel||'PairOfDice'; if(nativeControls)nativeControls.hidden=external; if(providerPlayer){providerPlayer.hidden=!external;providerPlayer.innerHTML=external&&btn.dataset.embedUrl?'<iframe src="'+btn.dataset.embedUrl.replace(/"/g,'&quot;')+'" title="'+(btn.dataset.sourceLabel||'External audio').replace(/"/g,'&quot;')+'" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>':'';} if(external){audio.pause();audio.removeAttribute('src');audio.load();} return !external;}

		play.addEventListener('click', function () {
			if (audio.paused) audio.play();
			else audio.pause();
		});

		audio.addEventListener('play', function () { updateButton(); emitLegacyMediaProgress(true); });
		audio.addEventListener('pause', function () { updateButton(); emitLegacyMediaProgress(false); });
		audio.addEventListener('loadedmetadata', function () {
			duration.textContent = formatTime(audio.duration);
		});
		audio.addEventListener('timeupdate', function () {
			current.textContent = formatTime(audio.currentTime);
			progress.value = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
			emitLegacyMediaProgress(!audio.paused);
		});
		audio.addEventListener('ended', function () {
			emitLegacyMediaProgress(false, Number.isFinite(audio.duration) ? audio.duration * 1000 : 0, Number.isFinite(audio.duration) ? audio.duration * 1000 : 0);
			const active = player.querySelector('[data-track].is-active');
			const nextRow = active && active.nextElementSibling && active.nextElementSibling.matches('[data-track]') ? active.nextElementSibling : null;
			const next = nextRow ? nextRow.querySelector('[data-track-play]') : null;
			if (next) {
				next.click();
				audio.play();
			}
		});

		progress.addEventListener('input', function () {
			if (audio.duration) audio.currentTime = (parseFloat(progress.value) / 100) * audio.duration;
		});

		player.querySelectorAll('[data-track-play]').forEach(function (trackButton) {
			trackButton.addEventListener('click', function () {
				const previousButton = activeTrackButton();
				if (previousButton && previousButton !== trackButton) emitLegacyMediaReset(previousButton);
				player.querySelectorAll('[data-track]').forEach(function (item) { item.classList.remove('is-active'); });
				const row = trackButton.closest('[data-track]');
				if (row) row.classList.add('is-active');

				const src = trackButton.dataset.src || ''; const isLocal=loadProvider(trackButton); if(isLocal)audio.src=src;
				title.textContent = trackButton.dataset.title || 'Untitled Track';
				type.textContent = trackButton.dataset.type || 'Track';

				const coverUrl = trackButton.dataset.cover || '';
				cover.innerHTML = coverUrl ? '<img src="' + coverUrl.replace(/"/g, '&quot;') + '" alt="">' : '';

				const commerce = player.querySelector('[data-current-commerce]');
				const buyLink = commerce ? commerce.querySelector('[data-current-buy]') : null;
				const price = commerce ? commerce.querySelector('[data-current-price]') : null;
				const buyUrl = trackButton.dataset.buyUrl || '';
				const buyLabel = trackButton.dataset.buyLabel || 'Buy Now';
				const priceText = trackButton.dataset.priceText || '';

				if (commerce) commerce.hidden = !buyUrl;
				if (buyLink) {
					buyLink.href = buyUrl || '#';
					buyLink.textContent = buyLabel + ' →';
				}
				if (price) {
					price.textContent = priceText;
					price.hidden = !priceText;
				}

				if(isLocal)audio.play().catch(function(){});
			});
		});
        const initialTrack=player.querySelector('[data-track-play]'); if(initialTrack)loadProvider(initialTrack);
	});

	document.querySelectorAll('[data-track-editor]').forEach(function (editor) {
		const panel = editor.closest('[data-studio-panel]');
		const add = panel ? panel.querySelector('[data-add-track]') : null;
		if (!add) return;

		add.addEventListener('click', function () {
			const rows = editor.querySelectorAll('[data-track-row]');
			const index = rows.length;
			const row = document.createElement('div');
			row.className = 'nfinite-track-row';
			row.setAttribute('data-track-row', '');
			row.innerHTML = `
				<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--audio">
					<div class="nfinite-track-summary__main"><strong data-track-summary-title>Untitled Track</strong><small>Needs audio</small></div>
					<span data-track-summary-type>Single</span><span class="nfinite-studio-commerce-state"><small>—</small></span><span class="nfinite-status-pill is-draft">Incomplete</span>
					<div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-track>Done</button><button type="button" class="nfinite-track-remove" data-remove-track>Remove</button></div>
				</div>
				<div class="nfinite-track-row__body" data-track-body>
				<div class="nfinite-form-grid">
					<label><span>Title</span><input type="text" name="tracks[${index}][title]"></label>
					<label><span>Track Type</span><select name="tracks[${index}][type]"><option>Single</option><option>Beat</option><option>Demo</option><option>Mix</option><option>Production Reel</option><option>Other</option></select></label>
                    <label><span>Playback Source</span><select name="tracks[${index}][playback_source]"><option value="local">Uploaded / Direct Audio</option><option value="soundcloud">SoundCloud</option><option value="spotify">Spotify</option><option value="apple_music">Apple Music</option></select></label>
                    <label class="nfinite-form-full"><span>Uploaded / Direct Audio URL</span><input type="url" name="tracks[${index}][audio_url]"></label>
                    <label><span>SoundCloud Track URL</span><input type="url" name="tracks[${index}][soundcloud_url]"></label>
                    <label><span>Spotify Track URL</span><input type="url" name="tracks[${index}][spotify_url]"></label>
                    <label><span>Apple Music Track URL</span><input type="url" name="tracks[${index}][apple_music_url]"></label>
					<label><span>Upload Audio</span><input type="file" name="track_audio_${index}" accept="audio/*"></label>
					<label><span>Cover Art URL</span><input type="url" name="tracks[${index}][cover_url]"></label>
					<label><span>Credits / Notes</span><textarea name="tracks[${index}][credits]" rows="3"></textarea></label>
					<input type="hidden" name="tracks[${index}][uuid]" value="">
					<div class="nfinite-form-full nfinite-monetization-card">
						<div class="nfinite-monetization-card__head"><div><strong>Music Monetization</strong><small>Enroll eligible original music for future earnings from qualified PairOfDice listening.</small></div><span class="nfinite-status-pill nfinite-mon-status-not_enrolled">Not Enrolled</span></div>
						<label class="nfinite-toggle"><input type="checkbox" name="tracks[${index}][monetization][enrolled]" value="1"><span>Enroll this track in PairOfDice music monetization</span></label>
						<div class="nfinite-form-grid">
							<label><span>Master owner</span><input type="text" name="tracks[${index}][monetization][master_owner]"></label>
							<label><span>Primary artist</span><input type="text" name="tracks[${index}][monetization][primary_artist]"></label>
							<label><span>Featured artist(s)</span><input type="text" name="tracks[${index}][monetization][featured_artists]"></label>
							<label><span>Producer(s)</span><input type="text" name="tracks[${index}][monetization][producers]"></label>
							<label><span>ISRC (if available)</span><input type="text" maxlength="15" name="tracks[${index}][monetization][isrc]" placeholder="USABC2600001"></label>
							<label class="nfinite-form-full"><span>Songwriter / publishing credits</span><textarea rows="2" name="tracks[${index}][monetization][songwriters]"></textarea></label>
						</div>
						<label class="nfinite-toggle"><input type="checkbox" name="tracks[${index}][monetization][rights_confirmed]" value="1"><span>I control the rights necessary to authorize PairOfDice to monetize this recording.</span></label>
						<label class="nfinite-toggle"><input type="checkbox" name="tracks[${index}][monetization][monetization_terms]" value="1"><span>I agree to the PairOfDice music monetization terms.</span></label>
						<p class="nfinite-field-help">Enrollment is reviewed before a track becomes Monetized. Public play counts remain separate from payable qualified streams.</p>
					</div>
					<div class="nfinite-beat-commerce" data-beat-commerce hidden>
                        <label class="nfinite-form-full nfinite-toggle"><input type="checkbox" name="tracks[${index}][sell_beat]" value="1"><span>Sell this beat on PairOfDice</span></label>
                        <div class="nfinite-license-grid">
                            <label><span>MP3 Lease</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="tracks[${index}][licenses][mp3]" placeholder="0.00"></div></label>
                            <label><span>WAV Lease</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="tracks[${index}][licenses][wav]" placeholder="0.00"></div></label>
                            <label><span>Trackout</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="tracks[${index}][licenses][trackout]" placeholder="0.00"></div></label>
                            <label><span>Unlimited</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="tracks[${index}][licenses][unlimited]" placeholder="0.00"></div></label>
                            <label><span>Exclusive</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="tracks[${index}][licenses][exclusive]" placeholder="0.00"></div></label>
                        </div>
                        <p class="nfinite-field-help">Set only the licenses you want to offer. Nfinite creates the WooCommerce product automatically.</p>
                        <div class="nfinite-delivery-assets"><strong>Delivery files</strong><p class="nfinite-field-help">Upload the files buyers should receive after purchase.</p><div class="nfinite-form-grid"><label><span>MP3 master</span><input type="file" name="beat_mp3_${index}" accept="audio/mpeg,audio/mp3"></label><label><span>WAV master</span><input type="file" name="beat_wav_${index}" accept="audio/wav,audio/x-wav"></label><label><span>Trackout ZIP</span><input type="file" name="beat_trackout_${index}" accept=".zip,application/zip"></label></div></div>
                        <input type="hidden" name="tracks[${index}][product_id]" value="0">
                    </div>
					<label><span>External Buy URL (optional)</span><input type="url" name="tracks[${index}][buy_url]"></label>
					<label><span>Buy Button Label</span><input type="text" name="tracks[${index}][buy_label]" placeholder="Buy Now"></label>
					<label class="nfinite-toggle"><input type="checkbox" name="tracks[${index}][show_price]" value="1"><span>Show starting price</span></label>
				</div>
				</div>`;
			editor.appendChild(row);
			if(panel){const empty=panel.querySelector('[data-empty-audio]');const head=panel.querySelector('[data-audio-list-head]');if(empty)empty.hidden=true;if(head)head.hidden=false;}
		});

		editor.addEventListener('click', function (event) {
			if (event.target.matches('[data-edit-track]')) {
				const row = event.target.closest('[data-track-row]');
				const body = row ? row.querySelector('[data-track-body]') : null;
				if (body) {
					body.hidden = !body.hidden;
					row.classList.toggle('is-open', !body.hidden);
					event.target.textContent = body.hidden ? 'Edit' : 'Done';
				}
				return;
			}
			if (!event.target.matches('[data-remove-track]')) return;
			const row = event.target.closest('[data-track-row]');
			if (row) row.remove();
		});

		editor.addEventListener('input', function (event) {
			const row = event.target.closest('[data-track-row]');
			if (!row) return;
			if (event.target.name && /\[title\]$/.test(event.target.name)) {
				const label = row.querySelector('[data-track-summary-title]');
				if (label) label.textContent = event.target.value.trim() || 'Untitled Track';
			}
			if (event.target.name && /\[type\]$/.test(event.target.name)) {
				const label = row.querySelector('[data-track-summary-type]');
				if (label) label.textContent = event.target.value || 'Track';
				const commerce = row.querySelector('[data-beat-commerce]');
				if (commerce) {
					const isBeat = event.target.value === 'Beat';
					commerce.hidden = !isBeat;
					if (!isBeat) {
						const sell = commerce.querySelector('input[name*=\"[sell_beat]\"]');
						if (sell) sell.checked = false;
					}
				}
			}
		});
	});


	// Track type controls whether beat licensing is relevant.
	document.querySelectorAll('[data-track-row]').forEach(function (row) {
		const select = row.querySelector('select[name*="[type]"]');
		const commerce = row.querySelector('[data-beat-commerce]');
		if (select && commerce) commerce.hidden = select.value !== 'Beat';
	});

	// Universal creator product manager.
	document.querySelectorAll('[data-product-editor]').forEach(function (editor) {
		const panel = editor.closest('[data-studio-panel]');
		const add = panel ? panel.querySelector('[data-add-product]') : null;
		if (!add) return;

		function refreshProductTypeFields(row) {
			if (!row) return;
			const select = row.querySelector('select[name*="[type]"]');
			const type = select ? select.value : 'digital';
			row.querySelectorAll('[data-product-field]').forEach(function(field) {
				const allowed = (field.getAttribute('data-product-field') || '').split(/\s+/);
				field.hidden = allowed.indexOf(type) === -1;
			});
		}

		editor.querySelectorAll('[data-product-row]').forEach(refreshProductTypeFields);

		add.addEventListener('click', function () {
			const index = editor.querySelectorAll('[data-product-row]').length;
			const row = document.createElement('div');
			row.className = 'nfinite-product-row is-open';
			row.setAttribute('data-product-row', '');
			row.innerHTML = `
				<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--product">
					<div class="nfinite-track-summary__main"><strong data-product-summary-title>Untitled Product</strong><small>Creator product</small></div>
					<span data-product-summary-type>Digital Product</span><b data-product-summary-price>—</b><span class="nfinite-status-pill is-live">Active</span>
					<div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-product>Done</button><button type="button" class="nfinite-track-edit" data-duplicate-product>Duplicate</button><button type="button" class="nfinite-track-remove" data-remove-product>Remove</button></div>
				</div>
				<div class="nfinite-product-row__body" data-product-body>
					<div class="nfinite-form-grid">
						<label><span>Product Name</span><input type="text" name="products[${index}][title]"></label>
						<label><span>Product Type</span><select name="products[${index}][type]"><option value="digital">Digital Product</option><option value="physical">Physical Product</option><option value="service">Service</option><option value="booking">Booking</option></select></label>
						<label><span>Price</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="products[${index}][price]" placeholder="0.00"></div></label>
						<label class="nfinite-product-image-field"><span>Product Image</span><input type="file" name="product_image_${index}" accept="image/*"><input type="hidden" name="products[${index}][image_id]" value="0"><details class="nfinite-advanced-field"><summary>Use an image URL instead</summary><input type="url" name="products[${index}][image_url]" placeholder="https://..."></details></label>
						<label class="nfinite-form-full"><span>Description</span><textarea name="products[${index}][description]" rows="4"></textarea></label>
						<label class="nfinite-form-full nfinite-product-type-field" data-product-field="digital"><span>Digital Download File</span><input type="file" name="product_download_${index}"><input type="hidden" name="products[${index}][download_url]" value=""></label>
						<label class="nfinite-product-type-field" data-product-field="physical"><span>Inventory Quantity</span><input type="number" min="0" step="1" name="products[${index}][stock]"></label>
						<label class="nfinite-product-type-field" data-product-field="physical"><span>Weight (lbs)</span><input type="number" min="0" step="0.01" name="products[${index}][weight]"></label>
						<label class="nfinite-product-type-field" data-product-field="service booking"><span>Duration / Session Length</span><input type="text" name="products[${index}][duration]" placeholder="Example: 2 hours"></label>
						<label class="nfinite-product-type-field" data-product-field="booking"><span>Booking Instructions</span><input type="text" name="products[${index}][booking_instructions]" placeholder="We will contact you to confirm a time."></label>
						<label><span>Delivery / Turnaround</span><input type="text" name="products[${index}][delivery]" placeholder="Example: 3–5 business days"></label>
						<label class="nfinite-toggle"><input type="checkbox" name="products[${index}][active]" value="1" checked><span>Available for purchase</span></label>
						<input type="hidden" name="products[${index}][product_id]" value="0">
					</div>
				</div>`;
			editor.appendChild(row);
			if(panel){const empty=panel.querySelector('[data-empty-products]');const head=panel.querySelector('[data-product-list-head]');if(empty)empty.hidden=true;if(head)head.hidden=false;}
		});

		editor.addEventListener('click', function (event) {
			if (event.target.matches('[data-edit-product]')) {
				const row = event.target.closest('[data-product-row]');
				const body = row ? row.querySelector('[data-product-body]') : null;
				if (body) {
					body.hidden = !body.hidden;
					row.classList.toggle('is-open', !body.hidden);
					event.target.textContent = body.hidden ? 'Edit' : 'Done';
				}
				return;
			}
			if (event.target.matches('[data-duplicate-product]')) {
				const source = event.target.closest('[data-product-row]');
				if (source) {
					const clone = source.cloneNode(true);
					const index = editor.querySelectorAll('[data-product-row]').length;
					clone.querySelectorAll('[name]').forEach(function(field) {
						field.name = field.name.replace(/products\[\d+\]/, 'products[' + index + ']');
						if (/\[product_id\]$/.test(field.name) || /\[image_id\]$/.test(field.name)) field.value = '0';
						if (field.type === 'file') field.value = '';
					});
					const title = clone.querySelector('input[name$="[title]"]');
					if (title) title.value = (title.value || 'Untitled Product') + ' Copy';
					clone.classList.add('is-open');
					const body = clone.querySelector('[data-product-body]'); if (body) body.hidden = false;
					const edit = clone.querySelector('[data-edit-product]'); if (edit) edit.textContent = 'Done';
					editor.appendChild(clone); refreshProductTypeFields(clone);
				}
				return;
			}
			if (event.target.matches('[data-remove-product]')) {
				const row = event.target.closest('[data-product-row]');
				if (row) row.remove();
			}
		});

		editor.addEventListener('input', function (event) {
			const row = event.target.closest('[data-product-row]');
			if (!row || !event.target.name) return;
			if (/\\[title\\]$/.test(event.target.name)) {
				const el = row.querySelector('[data-product-summary-title]');
				if (el) el.textContent = event.target.value.trim() || 'Untitled Product';
			}
			if (/\\[price\\]$/.test(event.target.name)) {
				const el = row.querySelector('[data-product-summary-price]');
				if (el) el.textContent = event.target.value ? '$' + Number(event.target.value).toFixed(2) : '—';
			}
		});
		editor.addEventListener('change', function (event) {
			if (!event.target.name || !/\\[type\\]$/.test(event.target.name)) return;
			const row = event.target.closest('[data-product-row]');
			const el = row ? row.querySelector('[data-product-summary-type]') : null;
			const labels = {digital:'Digital Product', physical:'Physical Product', service:'Service', booking:'Booking'};
			if (el) el.textContent = labels[event.target.value] || 'Product';
			refreshProductTypeFields(row);
		});
	});


	// 0.26.1 Printful merch builder: visual color swatches + size chips.
	function nfinitePrintfulColorFallback(name) {
		const key = String(name || '').toLowerCase().trim();
		const map = {
			'black':'#111111','white':'#ffffff','forest green':'#1f4d36','green':'#2e7d32','graphite':'#52565a',
			'navy':'#182b49','purple':'#5b2c83','royal':'#2457c5','royal blue':'#2457c5','safety orange':'#ff6a00',
			'safety yellow':'#f4ea2a','silver':'#c0c0c0','gray':'#808080','grey':'#808080','red':'#c62828','maroon':'#6b1f2b',
			'pink':'#e88ba8','light blue':'#8ecae6','blue':'#2867b2','orange':'#f57c00','yellow':'#f4d03f','brown':'#795548',
			'beige':'#d8c3a5','natural':'#e8dcc7'
		};
		return map[key] || '#64748b';
	}

	function nfinitePrintfulSwatchBackground(color, code) {
		const parts = String(color || '').split(/\s*\/\s*|\s*\|\s*/).filter(Boolean);
		if (parts.length > 1) {
			const colors = parts.slice(0, 2).map(nfinitePrintfulColorFallback);
			return 'linear-gradient(135deg,' + colors[0] + ' 0 50%,' + colors[1] + ' 50% 100%)';
		}
		return code || nfinitePrintfulColorFallback(color);
	}

	function nfiniteRenderPrintfulOptions(box, variants, savedValue) {
		box._nfinitePrintfulVariants = variants || [];
		const hidden = box.querySelector('[data-printful-variant-values]');
		const saved = new Set(String(savedValue || '').split(',').map(v => v.trim()).filter(Boolean));
		const colorMap = new Map();
		const sizes = [];
		variants.forEach(v => {
			const color = v.color || 'Default';
			if (!colorMap.has(color)) colorMap.set(color, { name: color, code: v.color_code || '' });
			if (v.size && !sizes.includes(v.size)) sizes.push(v.size);
		});
		const selectedColors = new Set();
		const selectedSizes = new Set();
		variants.forEach(v => {
			if (saved.has(String(v.id))) { if (v.color) selectedColors.add(v.color); if (v.size) selectedSizes.add(v.size); }
		});
		if (!saved.size && colorMap.size === 1) selectedColors.add(Array.from(colorMap.keys())[0]);

		const colorsHtml = Array.from(colorMap.values()).map(c => {
			const selected = selectedColors.has(c.name);
			const bg = nfinitePrintfulSwatchBackground(c.name, c.code);
			return '<button type="button" class="nfinite-printful-color' + (selected ? ' is-selected' : '') + '" data-printful-color="' + encodeURIComponent(c.name) + '" aria-pressed="' + (selected ? 'true' : 'false') + '"><span class="nfinite-printful-color__swatch" style="--nfinite-swatch:' + bg + '"></span><span>' + c.name + '</span><i aria-hidden="true">✓</i></button>';
		}).join('');
		const sizesHtml = sizes.map(size => '<button type="button" class="nfinite-printful-size' + (selectedSizes.has(size) ? ' is-selected' : '') + '" data-printful-size="' + encodeURIComponent(size) + '" aria-pressed="' + (selectedSizes.has(size) ? 'true' : 'false') + '">' + size + '</button>').join('');
		box.insertAdjacentHTML('beforeend', '<div class="nfinite-printful-picker"><div class="nfinite-printful-picker__group"><span class="nfinite-printful-picker__label">Choose Colors</span><div class="nfinite-printful-colors">' + colorsHtml + '</div></div><div class="nfinite-printful-picker__group"><span class="nfinite-printful-picker__label">Choose Sizes</span><div class="nfinite-printful-sizes">' + sizesHtml + '</div></div><p class="nfinite-printful-selection-note" data-printful-selection-note></p></div>');

		function sync() {
			const colors = new Set(Array.from(box.querySelectorAll('[data-printful-color].is-selected')).map(b => decodeURIComponent(b.dataset.printfulColor)));
			const chosenSizes = new Set(Array.from(box.querySelectorAll('[data-printful-size].is-selected')).map(b => decodeURIComponent(b.dataset.printfulSize)));
			const ids = variants.filter(v => (!v.color || colors.has(v.color)) && (!v.size || chosenSizes.has(v.size))).map(v => String(v.id));
			hidden.value = ids.join(',');
			const note = box.querySelector('[data-printful-selection-note]');
			if (note) note.textContent = ids.length ? ids.length + ' available Printful variant' + (ids.length === 1 ? '' : 's') + ' selected.' : 'Choose at least one color and size.';
		}
		box.querySelectorAll('[data-printful-color],[data-printful-size]').forEach(btn => btn.addEventListener('click', function () {
			this.classList.toggle('is-selected'); this.setAttribute('aria-pressed', this.classList.contains('is-selected') ? 'true' : 'false'); sync();
		}));
		sync();
	}

	function nfiniteLoadPrintfulProduct(select) {
		if (!select || !select.value || select.disabled) return;
		const row = select.closest('[data-merch-row]');
		const box = row ? row.querySelector('[data-printful-variants]') : null;
		if (!box || box.dataset.loading === '1') return;
		const existingHidden = box.querySelector('[data-printful-variant-values]');
		const savedValue = (existingHidden && existingHidden.value) || box.dataset.savedVariants || '';
		const hiddenName = existingHidden ? existingHidden.name : '';
		box.dataset.loading = '1';
		box.innerHTML = '<strong>Colors & Sizes</strong><p><small>Loading Printful colors and sizes…</small></p><input type="hidden" name="' + hiddenName + '" value="' + savedValue + '" data-printful-variant-values>';
		const body = new URLSearchParams({ action: 'nfinite_printful_product', nonce: select.dataset.nonce || '', product_id: select.value });
		fetch((window.NfiniteCreatorEngagement && NfiniteCreatorEngagement.ajaxUrl) || '/wp-admin/admin-ajax.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body: body.toString() })
		.then(r => r.json()).then(resp => {
			if (!resp.success) throw new Error('load');
			box.querySelector('p')?.remove();
			nfiniteRenderPrintfulOptions(box, resp.data.variants || [], savedValue);

			const placementWrap = row && row.querySelector('[data-printful-placement-options]');
			const placements = (resp.data && resp.data.placements) || [];
			if (placementWrap && placements.length) {
				const current = placementWrap.dataset.savedPlacement || (placementWrap.querySelector('input:checked') || {}).value || '';
				const name = placementWrap.dataset.fieldName || (placementWrap.querySelector('input') || {}).name || '';
				placementWrap.innerHTML = placements.map((p, i) => {
					const checked = current ? p.key === current : i === 0;
					return '<label><input type="radio" name="' + name + '" value="' + p.key + '" ' + (checked ? 'checked' : '') + '> <span>' + p.label + '</span></label>';
				}).join('');
			}
		}).catch(() => { box.insertAdjacentHTML('beforeend','<p><small>Could not load variants. Verify the Printful connection and try again.</small></p>'); })
		.finally(() => { box.dataset.loading = '0'; });
	}

	document.addEventListener('change', function (event) {
		const select = event.target.closest('[data-printful-product]');
		if (!select) return;
		const row = select.closest('[data-merch-row]');
		const hidden = row ? row.querySelector('[data-printful-variant-values]') : null;
		if (hidden) hidden.value = '';
		const box = row ? row.querySelector('[data-printful-variants]') : null;
		if (box) box.dataset.savedVariants = '';
		nfiniteLoadPrintfulProduct(select);
	});

	// Populate already-saved Printful products when an existing merch row is opened/rendered.
	document.querySelectorAll('[data-printful-product]').forEach(function (select) { if (select.value) nfiniteLoadPrintfulProduct(select); });

	// 0.25.0 Printful merch intake manager.
	document.querySelectorAll('[data-merch-editor]').forEach(function (editor) {
		const panel = editor.closest('[data-studio-panel]');
		const add = panel ? panel.querySelector('[data-add-merch]') : null;
		if (!add) return;

		function renumberMerch() {
			editor.querySelectorAll('[data-merch-row]').forEach(function (row, index) {
				row.querySelectorAll('[name]').forEach(function (field) {
					field.name = field.name.replace(/merch\[\d+\]/, 'merch[' + index + ']');
					if (/^merch_design_\d+$/.test(field.name)) field.name = 'merch_design_' + index;
				});
			});
		}

		add.addEventListener('click', function () {
			const index = editor.querySelectorAll('[data-merch-row]').length;
			const row = document.createElement('div');
			row.className = 'nfinite-product-row is-open';
			row.setAttribute('data-merch-row', '');
			row.innerHTML = `
				<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--product">
					<div class="nfinite-track-summary__main"><strong data-merch-summary-title>Untitled Merch</strong><small>Awaiting approval</small></div>
					<span data-merch-summary-kind>T-Shirt</span><b data-merch-summary-price>—</b><span class="nfinite-status-pill is-draft">Pending review</span>
					<div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-merch>Done Editing</button><button type="button" class="nfinite-track-remove" data-remove-merch>Remove</button></div>
				</div>
				<div class="nfinite-product-row__body" data-merch-body><div class="nfinite-form-grid">
					<label><span>Product Name</span><input type="text" name="merch[${index}][title]" placeholder="Logo Tee"></label>
					<label><span>Printful Product</span><select name="merch[${index}][catalog_product_id]" data-printful-product><option value="">Save first, then choose from the live Printful catalog</option></select><input type="hidden" name="merch[${index}][product_kind]" value="Printful Merch"></label>
					<label><span>Target Retail Price</span><div class="nfinite-price-input"><b>$</b><input type="number" min="0" step="0.01" name="merch[${index}][price]" placeholder="29.99"></div></label>
					<div class="nfinite-form-full nfinite-printful-variants" data-printful-variants><strong>Variants</strong><p><small>Choose a Printful product to load its real colors and sizes.</small></p><input type="hidden" name="merch[${index}][variant_ids]" value="" data-printful-variant-values></div>
					<label class="nfinite-form-full"><span>Design / Placement Notes</span><textarea name="merch[${index}][notes]" rows="3" placeholder="Front center chest, small logo on back neck, etc."></textarea></label>
					<label class="nfinite-form-full"><span>Print Design File</span><input type="file" name="merch_design_${index}" accept="image/png,image/jpeg,image/webp,application/pdf"><small>High-resolution PNG is preferred. PDF/JPG/WebP are also accepted for review.</small></label>
					<input type="hidden" name="merch[${index}][request_id]" value="">
					<input type="hidden" name="merch[${index}][status]" value="pending">
					<input type="hidden" name="merch[${index}][product_id]" value="0">
					<input type="hidden" name="merch[${index}][design_url]" value="">
				</div></div>`;
			editor.appendChild(row);
			if (panel) {
				const empty = panel.querySelector('[data-empty-merch]');
				const head = panel.querySelector('[data-merch-list-head]');
				if (empty) empty.hidden = true;
				if (head) head.hidden = false;
			}
			renumberMerch();
		});

		editor.addEventListener('click', function (event) {
			if (event.target.matches('[data-edit-merch]')) {
				const row = event.target.closest('[data-merch-row]');
				const body = row ? row.querySelector('[data-merch-body]') : null;
				if (!body) return;
				body.hidden = !body.hidden;
				row.classList.toggle('is-open', !body.hidden);
				event.target.textContent = body.hidden ? 'Edit' : 'Done Editing';
				return;
			}
			if (event.target.matches('[data-remove-merch]')) {
				const row = event.target.closest('[data-merch-row]');
				if (row) row.remove();
				renumberMerch();
				if (panel && !editor.querySelector('[data-merch-row]')) {
					const empty = panel.querySelector('[data-empty-merch]');
					const head = panel.querySelector('[data-merch-list-head]');
					if (empty) empty.hidden = false;
					if (head) head.hidden = true;
				}
			}
		});

		editor.addEventListener('input', function (event) {
			const row = event.target.closest('[data-merch-row]');
			if (!row || !event.target.name) return;
			if (/\[title\]$/.test(event.target.name)) {
				const el = row.querySelector('[data-merch-summary-title]');
				if (el) el.textContent = event.target.value.trim() || 'Untitled Merch';
			}
			if (/\[price\]$/.test(event.target.name)) {
				const el = row.querySelector('[data-merch-summary-price]');
				if (el) el.textContent = event.target.value ? '$' + Number(event.target.value).toFixed(2) : '—';
			}
		});
		editor.addEventListener('change', function (event) {
			if (!event.target.name || !/\[product_kind\]$/.test(event.target.name)) return;
			const row = event.target.closest('[data-merch-row]');
			const el = row ? row.querySelector('[data-merch-summary-kind]') : null;
			if (el) el.textContent = event.target.value || 'Merch';
		});
		renumberMerch();
	});

	document.querySelectorAll('[data-video-editor]').forEach(function (editor) {
		const panel = editor.closest('[data-studio-panel]');
		const add = panel ? panel.querySelector('[data-add-video]') : null;
		if (!add) return;

		function renumberVideos() {
			editor.querySelectorAll('[data-video-row]').forEach(function (row, index) {
				row.querySelectorAll('[name]').forEach(function (field) { field.name = field.name.replace(/videos\[\d+\]/, 'videos[' + index + ']'); });
			});
		}

		add.addEventListener('click', function () {
			const index = editor.querySelectorAll('[data-video-row]').length;
			const row = document.createElement('div'); row.className = 'nfinite-video-row is-open'; row.setAttribute('data-video-row', '');
			row.innerHTML = `
				<div class="nfinite-track-summary nfinite-studio-item-summary nfinite-studio-item-summary--video"><div class="nfinite-track-summary__main"><strong data-video-summary-title>Untitled Video</strong><small>Needs URL</small></div><span data-video-summary-type>Music Video</span><span class="nfinite-status-pill" data-video-summary-featured>—</span><div class="nfinite-track-summary__actions"><button type="button" class="nfinite-track-edit" data-edit-video>Done</button><button type="button" class="nfinite-track-remove" data-remove-video>Remove</button></div></div>
				<div class="nfinite-video-row__body" data-video-body><div class="nfinite-form-grid"><label><span>Title</span><input type="text" name="videos[${index}][title]"></label><label><span>Video Type</span><select name="videos[${index}][type]"><optgroup label="Music &amp; Performance"><option>Music Video</option><option>Performance</option><option>Live Performance</option><option>Freestyle</option></optgroup><optgroup label="Shows &amp; Conversations"><option>Podcast</option><option>Podcast Episode</option><option>Interview</option><option>Talk / Discussion</option><option>Live Stream</option><option>Vlog</option></optgroup><optgroup label="News &amp; Editorial"><option>News Clip</option><option>Breaking News</option><option>News Report</option><option>Commentary</option><option>Opinion</option><option>Analysis</option><option>Explainer</option></optgroup><optgroup label="Long Form"><option>Documentary</option><option>Mini Documentary</option><option>Movie / Film</option><option>Feature / Story</option></optgroup><optgroup label="Entertainment &amp; Culture"><option>Gaming</option><option>Sports</option><option>Fashion</option><option>Comedy</option><option>Trailer</option><option>Behind the Scenes</option><option>Short / Clip</option><option>Tutorial</option><option>Showreel</option><option>Other</option></optgroup></select></label><label><span>PairOfDice Channel / Programming</span><select name="videos[${index}][channel]"><option value="">No PairOfDice channel</option><option>The Wire</option><option>The Rotation</option><option>Mic Check</option><option>757 Radar</option><option>PairOfDice Gaming</option><option>PairOfDice Originals</option></select></label><label class="nfinite-form-full"><span>YouTube / Vimeo URL</span><input type="url" name="videos[${index}][url]" placeholder="https://www.youtube.com/watch?v=..."></label><label><span>Source / Publisher</span><input type="text" name="videos[${index}][source_name]"></label><label><span>Source / Publisher URL</span><input type="url" name="videos[${index}][source_url]"></label><label class="nfinite-form-full"><span>Original Source / Article URL</span><input type="url" name="videos[${index}][original_url]"></label><label class="nfinite-form-full"><span>Description</span><textarea name="videos[${index}][description]" rows="3"></textarea></label><label class="nfinite-form-full nfinite-toggle"><input type="checkbox" data-frontend-featured-video name="videos[${index}][featured]" value="1"><span>Featured Video</span></label></div></div>`;
			editor.appendChild(row); if(panel){const empty=panel.querySelector('[data-empty-videos]');const head=panel.querySelector('[data-video-list-head]');if(empty)empty.hidden=true;if(head)head.hidden=false;} renumberVideos();
		});

		editor.addEventListener('click', function (event) {
			if(event.target.matches('[data-edit-video]')){const row=event.target.closest('[data-video-row]');const body=row?row.querySelector('[data-video-body]'):null;if(!body)return;body.hidden=!body.hidden;row.classList.toggle('is-open',!body.hidden);event.target.textContent=body.hidden?'Edit':'Done';return;}
			if (!event.target.matches('[data-remove-video]')) return; const row=event.target.closest('[data-video-row]'); if(row)row.remove(); renumberVideos(); if(panel&&!editor.querySelector('[data-video-row]')){const empty=panel.querySelector('[data-empty-videos]');const head=panel.querySelector('[data-video-list-head]');if(empty)empty.hidden=false;if(head)head.hidden=true;}
		});

		editor.addEventListener('input', function(event){const row=event.target.closest('[data-video-row]');if(!row||!event.target.name)return;if(/\[title\]$/.test(event.target.name)){const el=row.querySelector('[data-video-summary-title]');if(el)el.textContent=event.target.value.trim()||'Untitled Video';}if(/\[url\]$/.test(event.target.name)){const small=row.querySelector('.nfinite-track-summary__main small');if(small)small.textContent=event.target.value?'Video ready':'Needs URL';}});
		editor.addEventListener('change', function (event) {
			const row=event.target.closest('[data-video-row]');if(row&&event.target.name&&/\[type\]$/.test(event.target.name)){const el=row.querySelector('[data-video-summary-type]');if(el)el.textContent=event.target.value||'Video';}
			if(row&&event.target.matches('[data-frontend-featured-video]')){const el=row.querySelector('[data-video-summary-featured]');if(el){el.textContent=event.target.checked?'Featured':'—';el.classList.toggle('is-live',event.target.checked);}}
			if (!event.target.matches('[data-frontend-featured-video]') || !event.target.checked) return; editor.querySelectorAll('[data-frontend-featured-video]').forEach(function (checkbox) { if (checkbox !== event.target){checkbox.checked=false;const other=checkbox.closest('[data-video-row]');const badge=other&&other.querySelector('[data-video-summary-featured]');if(badge){badge.textContent='—';badge.classList.remove('is-live');}} });
		});
		renumberVideos();
	});

	document.querySelectorAll('[data-share-profile]').forEach(function(button){button.addEventListener('click',async function(){const url=button.dataset.shareUrl||window.location.href;const title=button.dataset.shareTitle||document.title;try{if(navigator.share){await navigator.share({title:title,url:url});}else if(navigator.clipboard){await navigator.clipboard.writeText(url);const original=button.textContent;button.textContent='Link copied';setTimeout(function(){button.textContent=original;},1600);}else{window.prompt('Copy this profile link:',url);}}catch(e){}});});

	document.querySelectorAll('[data-onboarding-tab]').forEach(function(button){button.addEventListener('click',function(){const target=button.getAttribute('data-onboarding-tab');const tab=document.querySelector('[data-studio-tab="'+target+'"]');if(tab){tab.click();tab.scrollIntoView({behavior:'smooth',block:'center'});}});});

});

/* 0.20.2 Creator Feed engagement */
(function(){
 const cfg=window.NfiniteCreatorEngagement||{};
 document.addEventListener('click',async function(e){
  const like=e.target.closest('[data-nfinite-like]');
  if(like){e.preventDefault();const id=like.dataset.postId;if(!id||!cfg.ajaxUrl)return;const key='nfinite-liked-'+id;const was=localStorage.getItem(key)==='1';const liked=!was;like.classList.toggle('is-liked',liked);like.setAttribute('aria-pressed',liked?'true':'false');localStorage.setItem(key,liked?'1':'0');const body=new URLSearchParams({action:'nfinite_toggle_creator_post_like',nonce:cfg.nonce,post_id:id,liked:liked?'1':''});try{const r=await fetch(cfg.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body});const j=await r.json();if(j.success){const c=like.querySelector('[data-like-count]');if(c)c.textContent=j.data.count;}}catch(err){}return;}
  const share=e.target.closest('[data-nfinite-share]');if(share){e.preventDefault();const url=share.dataset.shareUrl||location.href,title=share.dataset.shareTitle||document.title;try{if(navigator.share)await navigator.share({title,url});else if(navigator.clipboard){await navigator.clipboard.writeText(url);const em=share.querySelector('em');if(em){const old=em.textContent;em.textContent='Copied';setTimeout(()=>em.textContent=old,1400);}}}catch(err){} }
 });
 document.querySelectorAll('[data-nfinite-like]').forEach(function(btn){if(localStorage.getItem('nfinite-liked-'+btn.dataset.postId)==='1'){btn.classList.add('is-liked');btn.setAttribute('aria-pressed','true');}});
})();


// 0.21.0 Community polls
(function(){
 document.addEventListener('click',function(e){
  var option=e.target.closest('[data-poll-option]'); if(!option)return;
  var poll=option.closest('[data-nfinite-poll]'); if(!poll||!window.NfiniteCreatorEngagement)return;
  var postId=poll.dataset.postId,key='nfinite-poll-voted-'+postId;if(localStorage.getItem(key)==='1')return;
  var data=new URLSearchParams(); data.set('action','nfinite_creator_poll_vote');data.set('nonce',NfiniteCreatorEngagement.nonce);data.set('post_id',postId);data.set('option',option.dataset.pollOption);
  fetch(NfiniteCreatorEngagement.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString()}).then(r=>r.json()).then(function(res){if(!res.success)return;localStorage.setItem(key,'1');poll.querySelectorAll('[data-poll-option]').forEach(function(btn,i){var pct=res.data.percentages[i]||0;var b=btn.querySelector('b');var bar=btn.querySelector('i');if(b)b.textContent=pct+'%';if(bar)bar.style.setProperty('--nfinite-poll-pct',pct+'%');});var total=poll.querySelector('[data-poll-total]');if(total)total.textContent=res.data.total;poll.classList.add('is-voted');});
 });
})();

// 0.21.7 Creator Wall + incremental community loading.
document.addEventListener('click', async function(e){
  const button=e.target.closest('[data-nfinite-load-more]');
  if(!button || button.disabled || !window.NfiniteCreatorEngagement) return;
  e.preventDefault();
  const cfg=window.NfiniteCreatorEngagement;
  const offset=parseInt(button.dataset.offset||'0',10);
  const total=parseInt(button.dataset.total||'0',10);
  const data=new URLSearchParams();
  data.set('action','nfinite_load_creator_posts');
  data.set('nonce',cfg.nonce);
  data.set('context',button.dataset.context||'community');
  data.set('creator_id',button.dataset.creatorId||'0');
  data.set('view',button.dataset.view||'latest');
  data.set('offset',String(offset));
  button.disabled=true; button.classList.add('is-loading');
  const label=button.querySelector('span'); const original=label?label.textContent:'';
  if(label) label.textContent='Loading posts…';
  try{
    const r=await fetch(cfg.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString()});
    const j=await r.json();
    if(!j.success) throw new Error('load failed');
    const wrap=button.closest('.nfinite-load-more-wrap');
    if(wrap && j.data.html) wrap.insertAdjacentHTML('beforebegin',j.data.html);
    button.dataset.offset=String(j.data.offset);
    const count=button.querySelector('[data-load-count]'); if(count) count.textContent=String(j.data.offset);
    if(!j.data.hasMore){ if(wrap) wrap.remove(); }
    else if(label) label.textContent='Load 10 more posts';
    document.querySelectorAll('[data-nfinite-like]').forEach(function(btn){if(localStorage.getItem('nfinite-liked-'+btn.dataset.postId)==='1'){btn.classList.add('is-liked');btn.setAttribute('aria-pressed','true');}});
  }catch(err){ if(label) label.textContent='Could not load posts — try again'; button.disabled=false; button.classList.remove('is-loading'); setTimeout(function(){if(label)label.textContent=original;},1800); return; }
  button.disabled=false; button.classList.remove('is-loading');
});

// 0.26.5 Printful preview viewer, persistence, stale-state tracking, and rate-limit retry.
(function () {
	const ajaxUrl = (window.NfiniteCreatorEngagement && NfiniteCreatorEngagement.ajaxUrl) || '/wp-admin/admin-ajax.php';
	function post(data) {
		return fetch(ajaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data).toString()}).then(r => r.json());
	}
	function poll(taskKey, nonce, stage, tries) {
		if (tries > 18) { stage.innerHTML = '<p>Preview is taking longer than expected. Try again in a moment.</p>'; return; }
		setTimeout(function () {
			post({action:'nfinite_printful_mockup_status', nonce:nonce, task_key:taskKey}).then(resp => {
				if (!resp.success) throw new Error((resp.data && resp.data.message) || 'Preview failed');
				const status = resp.data.status || 'pending';
				const mockups = resp.data.mockups || [];
				if (status === 'completed' && mockups.length) {
					const row = stage.closest('[data-merch-row]');
					const productBox = row && row.querySelector('[data-printful-variants]');
					const variantData = productBox && productBox._nfinitePrintfulVariants ? productBox._nfinitePrintfulVariants : [];
					const selected = mockups.slice(0,12);
					const labelFor = function(m){
						const id = (m.variant_ids && m.variant_ids[0]) || 0;
						const v = variantData.find(x => Number(x.id) === Number(id));
						return v ? [v.color, v.size].filter(Boolean).join(' · ') : 'Product mockup';
					};
					const first = selected[0];
					stage.innerHTML = '<div class="nfinite-printful-preview__viewer"><div class="nfinite-printful-preview__primary"><img data-printful-primary-preview src="' + first.url + '" alt="Printful product preview"></div><div class="nfinite-printful-preview__details"><strong data-printful-preview-label>' + labelFor(first) + '</strong><span>Printful mockup · ' + ((row && row.querySelector('input[name*="[placement]"]:checked') || {}).nextElementSibling?.textContent || 'Selected placement') + '</span><div class="nfinite-printful-preview__choices">' + selected.map((m,i) => '<button type="button" class="nfinite-printful-preview__choice' + (i===0?' is-active':'') + '" data-preview-url="' + m.url + '" data-preview-label="' + labelFor(m).replace(/"/g,'&quot;') + '"><img src="' + m.url + '" alt="' + labelFor(m).replace(/"/g,'&quot;') + '"><span>' + labelFor(m) + '</span></button>').join('') + '</div></div></div><p>Mockup preview. Actual printed appearance may vary slightly.</p>';
					const stale = row && row.querySelector('[data-printful-preview-stale]'); if(stale) stale.hidden = true;
					const button = row && row.querySelector('[data-generate-printful-preview]'); if(button){button.textContent='Regenerate Preview';button.disabled=false;}
					if (row && row.dataset.creatorId && row.dataset.requestId) { post({action:'nfinite_printful_save_preview',nonce:nonce,creator_id:row.dataset.creatorId,request_id:row.dataset.requestId,preview_url:first.url}).catch(()=>{}); }
					return;
				}
				if (status === 'failed') throw new Error('Printful could not generate this preview.');
				stage.innerHTML = '<p>Printful is rendering your preview…</p>';
				poll(taskKey, nonce, stage, tries + 1);
			}).catch(err => { stage.innerHTML = '<p>' + (err.message || 'Could not generate preview.') + '</p>'; });
		}, tries === 0 ? 10000 : 5000);
	}
	function startPreview(button, payload, stage, retryCount) {
		post(payload).then(resp => {
			if (!resp.success) {
				const data = resp.data || {};
				if (data.rate_limited && retryCount < 1) {
					const wait = Math.max(10, parseInt(data.retry_after || 11, 10));
					stage.innerHTML = '<p>Printful is preparing the preview service. Retrying automatically in ' + wait + ' seconds…</p>';
					setTimeout(function(){ startPreview(button, payload, stage, retryCount + 1); }, wait * 1000);
					return;
				}
				throw new Error(data.message || 'Could not start preview.');
			}
			stage.innerHTML = '<p>Printful is rendering your preview…</p>';
			poll(resp.data.task_key, payload.nonce || '', stage, 0);
		}).catch(err => {
			stage.innerHTML = '<p>' + (err.message || 'Could not generate preview.') + '</p>';
			button.disabled = false;
		}).finally(() => {
			if (retryCount > 0) button.disabled = false;
		});
	}
	document.addEventListener('click', function (event) {
		const button = event.target.closest('[data-generate-printful-preview]');
		if (!button || button.disabled) return;
		const row = button.closest('[data-merch-row]');
		const preview = row && row.querySelector('[data-printful-preview]');
		const stage = preview && preview.querySelector('[data-printful-preview-stage]');
		const product = row && row.querySelector('[data-printful-product]');
		const variants = row && row.querySelector('[data-printful-variant-values]');
		const placement = row && row.querySelector('input[type="radio"][name*="[placement]"]:checked');
		const imageUrl = preview ? (preview.dataset.designUrl || '') : '';
		if (!stage) return;
		if (!imageUrl) { stage.innerHTML = '<p>Save the creator profile after uploading artwork, then generate the preview.</p>'; return; }
		if (!product || !product.value || !variants || !variants.value) { stage.innerHTML = '<p>Choose a Printful product, at least one color, and at least one size first.</p>'; return; }
		button.disabled = true;
		stage.innerHTML = '<p>Preparing your Printful preview…</p>';
		const payload = {action:'nfinite_printful_mockup', nonce:button.dataset.nonce || '', product_id:product.value, variant_ids:variants.value, image_url:imageUrl, placement:placement ? placement.value : 'front'};
		startPreview(button, payload, stage, 0);
	});

	document.addEventListener('click', function(event){
		const choice = event.target.closest('[data-preview-url]');
		if(!choice) return;
		const stage = choice.closest('[data-printful-preview-stage]');
		const primary = stage && stage.querySelector('[data-printful-primary-preview]');
		const label = stage && stage.querySelector('[data-printful-preview-label]');
		if(primary) primary.src = choice.dataset.previewUrl || primary.src;
		if(label) label.textContent = choice.dataset.previewLabel || 'Product mockup';
		stage.querySelectorAll('[data-preview-url]').forEach(btn => btn.classList.toggle('is-active', btn === choice));
	});

	function markPreviewStale(row){
		if(!row) return;
		const preview=row.querySelector('[data-printful-preview]');
		if(!preview || !preview.querySelector('img')) return;
		const stale=preview.querySelector('[data-printful-preview-stale]'); if(stale) stale.hidden=false;
	}
	document.addEventListener('change',function(event){
		if(event.target.closest('[data-merch-row]') && (event.target.matches('[data-printful-product]') || event.target.matches('[data-printful-color]') || event.target.matches('[data-printful-size]') || event.target.matches('input[name*="[placement]"]') || event.target.matches('input[type="file"][name^="merch_design_"]'))) markPreviewStale(event.target.closest('[data-merch-row]'));
	});
	document.addEventListener('click',function(event){
		if(event.target.closest('[data-printful-color],[data-printful-size]')) setTimeout(()=>markPreviewStale(event.target.closest('[data-merch-row]')),0);
	});
})();

/* 0.50.0 Follow + Save + Personal Library */
document.addEventListener('click', function (event) {
	const button = event.target.closest('[data-nfinite-library-action]');
	if (!button) return;
	event.preventDefault();
	if (button.disabled) return;
	button.disabled = true;
	const data = new URLSearchParams();
	data.append('action', 'nfinite_library_toggle');
	data.append('nonce', (window.NfiniteCreatorLibrary || {}).nonce || '');
	data.append('mode', button.dataset.nfiniteLibraryAction || '');
	data.append('object_id', button.dataset.objectId || '0');
	fetch((window.NfiniteCreatorLibrary || {}).ajaxUrl || '/wp-admin/admin-ajax.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data.toString(),credentials:'same-origin'})
		.then(function(r){return r.json();}).then(function(res){
			if (!res || !res.success) { if (res && res.data && res.data.message) alert(res.data.message); return; }
			button.classList.toggle('is-active', !!res.data.active);
			button.setAttribute('aria-pressed', res.data.active ? 'true' : 'false');
			const label=button.querySelector('[data-label]'); if(label) label.textContent=res.data.label;
		}).catch(function(){ alert('Could not update your library. Please try again.'); }).finally(function(){button.disabled=false;});
});

// 0.58.8 Community post management.
(function(){
 const cfg=window.NfiniteCreatorEngagement||{};
 let activeButton=null;
 function closeMenus(except){document.querySelectorAll('[data-nfinite-post-menu-panel]').forEach(function(panel){if(panel!==except){panel.hidden=true;const toggle=panel.parentElement&&panel.parentElement.querySelector('[data-nfinite-post-menu]');if(toggle)toggle.setAttribute('aria-expanded','false');}});}
 function ensureModal(){
  let modal=document.querySelector('[data-nfinite-community-modal]'); if(modal)return modal;
  modal=document.createElement('div'); modal.className='nfinite-community-modal'; modal.hidden=true; modal.setAttribute('data-nfinite-community-modal','');
  modal.innerHTML='<div class="nfinite-community-modal__backdrop" data-community-modal-cancel></div><div class="nfinite-community-modal__card" role="dialog" aria-modal="true" aria-labelledby="nfinite-community-modal-title"><p class="nfinite-community-modal__eyebrow">Community post</p><h2 id="nfinite-community-modal-title">Delete this post?</h2><p data-community-modal-copy>This removes the post from the PairOfDice community feed. This action can only be restored from the WordPress trash.</p><div class="nfinite-community-modal__post" data-community-modal-post></div><div class="nfinite-community-modal__actions"><button type="button" class="nfinite-community-modal__cancel" data-community-modal-cancel>Cancel</button><button type="button" class="nfinite-community-modal__confirm" data-community-modal-confirm>Delete post</button></div></div>';
  document.body.appendChild(modal); return modal;
 }
 function closeModal(){const m=document.querySelector('[data-nfinite-community-modal]');if(m){m.hidden=true;activeButton=null;}}
 document.addEventListener('click',function(e){
  const toggle=e.target.closest('[data-nfinite-post-menu]');
  if(toggle){e.preventDefault();e.stopPropagation();const panel=toggle.parentElement.querySelector('[data-nfinite-post-menu-panel]');const opening=panel.hidden;closeMenus(panel);panel.hidden=!opening;toggle.setAttribute('aria-expanded',opening?'true':'false');return;}
  const del=e.target.closest('[data-nfinite-delete-post]');
  if(del){e.preventDefault();activeButton=del;closeMenus();const modal=ensureModal();const admin=del.dataset.adminRemoval==='1';modal.querySelector('h2').textContent=admin?'Remove this post?':'Delete this post?';modal.querySelector('[data-community-modal-copy]').textContent=admin?'This will remove the creator’s post from the PairOfDice community feed and record the moderation action.':'This removes your post from the PairOfDice community feed. It will be moved to the WordPress trash.';modal.querySelector('[data-community-modal-post]').textContent=del.dataset.postTitle||'Community post';modal.querySelector('[data-community-modal-confirm]').textContent=admin?'Remove post':'Delete post';modal.hidden=false;return;}
  if(e.target.closest('[data-community-modal-cancel]')){e.preventDefault();closeModal();return;}
  const confirm=e.target.closest('[data-community-modal-confirm]');
  if(confirm&&activeButton){e.preventDefault();const button=activeButton;const id=button.dataset.postId;if(!id||!cfg.ajaxUrl)return;confirm.disabled=true;confirm.textContent='Removing…';const body=new URLSearchParams({action:'nfinite_delete_creator_post',nonce:cfg.nonce,post_id:id});fetch(cfg.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()}).then(r=>r.json()).then(function(j){if(!j.success)throw new Error((j.data&&j.data.message)||'Could not remove post.');const card=document.querySelector('[data-creator-post="'+id+'"]');const single=card&&card.classList.contains('is-single');closeModal();if(single&&j.data.redirect){window.location.href=j.data.redirect;return;}if(card){card.style.transition='opacity .18s ease,transform .18s ease';card.style.opacity='0';card.style.transform='scale(.985)';setTimeout(()=>card.remove(),190);}}).catch(function(err){confirm.disabled=false;confirm.textContent=button.dataset.adminRemoval==='1'?'Remove post':'Delete post';const copy=ensureModal().querySelector('[data-community-modal-copy]');copy.textContent=err.message||'Could not remove post. Please try again.';});return;}
  closeMenus();
 });
 document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeMenus();closeModal();}});
})();
