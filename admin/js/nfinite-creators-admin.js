jQuery(function($){
	'use strict';

	const labels = window.NfiniteCreatorsAdmin || {};

	function escapeHtml(value){
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	/* ----------------------------------------------------------
	 * Legacy/profile audio editor
	 * ---------------------------------------------------------- */
	function renumberTracks(){
		$('.nfinite-admin-track-list .nfinite-admin-track').each(function(index){
			const $row = $(this);
			$row.attr('data-index', index);
			$row.find('[data-track-number]').text(index + 1);
			$row.find('[name]').each(function(){
				const name = $(this).attr('name');
				if (name) $(this).attr('name', name.replace(/tracks\[\d+\]/, 'tracks[' + index + ']'));
			});
		});
	}

	$('.nfinite-admin-track-list').sortable({
		handle: '.nfinite-admin-track__head',
		placeholder: 'nfinite-admin-track-placeholder',
		update: renumberTracks
	});

	$(document).on('click', '[data-remove-admin-track]', function(){
		$(this).closest('.nfinite-admin-track').remove();
		renumberTracks();
	});

	$('[data-add-admin-track]').on('click', function(){
		const index = $('.nfinite-admin-track-list .nfinite-admin-track').length;
		const html = `
			<div class="nfinite-admin-track" data-index="${index}">
				<div class="nfinite-admin-track__head">
					<strong><span class="dashicons dashicons-menu nfinite-admin-track__handle"></span>${escapeHtml(labels.trackLabel || 'Track')} <span data-track-number>${index + 1}</span></strong>
					<button type="button" class="button-link-delete" data-remove-admin-track>${escapeHtml(labels.remove || 'Remove')}</button>
				</div>
				<div class="nfinite-admin-track__body">
					<div class="nfinite-admin-grid">
						<div class="nfinite-admin-field"><label>Title</label><input class="widefat" type="text" name="tracks[${index}][title]"></div>
						<div class="nfinite-admin-field"><label>Track Type</label><select class="widefat" name="tracks[${index}][type]"><option>Single</option><option>Beat</option><option>Demo</option><option>Mix</option><option>Production Reel</option><option>Other</option></select></div>
						<div class="nfinite-admin-field nfinite-admin-field--full">
							<label>Audio</label>
							<input class="widefat" type="url" data-audio-url name="tracks[${index}][audio_url]">
							<div class="nfinite-admin-actions"><button type="button" class="button" data-select-audio>${escapeHtml(labels.selectAudio || 'Select audio')}</button></div>
						</div>
						<div class="nfinite-admin-field">
							<label>Cover Art</label>
							<input class="widefat" type="url" data-cover-url name="tracks[${index}][cover_url]">
							<div class="nfinite-admin-actions"><button type="button" class="button" data-select-cover>${escapeHtml(labels.selectImage || 'Select image')}</button></div>
							<div class="nfinite-admin-media-preview" data-cover-preview></div>
						</div>
						<div class="nfinite-admin-field"><label>Credits / Notes</label><textarea class="widefat" rows="4" name="tracks[${index}][credits]"></textarea></div>
						<div class="nfinite-admin-field nfinite-admin-field--full"><h4>Commerce</h4></div>
						<div class="nfinite-admin-field"><label>WooCommerce Product ID</label><input class="widefat" type="number" min="0" name="tracks[${index}][product_id]"></div>
						<div class="nfinite-admin-field"><label>External Buy URL</label><input class="widefat" type="url" name="tracks[${index}][buy_url]"></div>
						<div class="nfinite-admin-field"><label>Button Label</label><input class="widefat" type="text" name="tracks[${index}][buy_label]" placeholder="Buy Now"></div>
						<div class="nfinite-admin-field"><label><input type="checkbox" name="tracks[${index}][show_price]" value="1"> Show product price</label></div>
					</div>
				</div>
			</div>`;
		$('.nfinite-admin-track-list').append(html);
		renumberTracks();
	});

	function openMediaFrame(type, callback, multiple){
		const frame = wp.media({
			title: type === 'audio' ? (labels.selectAudio || 'Select audio') : (labels.selectImage || 'Select image'),
			button: { text: type === 'audio' ? (labels.useAudio || 'Use audio') : (labels.useImage || 'Use image') },
			library: { type: type },
			multiple: !!multiple
		});
		frame.on('select', function(){
			const selection = frame.state().get('selection').toJSON();
			callback(multiple ? selection : selection[0]);
		});
		frame.open();
	}

	$(document).on('click', '[data-select-audio]', function(){
		const $track = $(this).closest('.nfinite-admin-track');
		openMediaFrame('audio', function(item){
			if (item) $track.find('[data-audio-url]').val(item.url).trigger('change');
		}, false);
	});

	$(document).on('click', '[data-select-cover]', function(){
		const $track = $(this).closest('.nfinite-admin-track');
		openMediaFrame('image', function(item){
			if (!item) return;
			$track.find('[data-cover-url]').val(item.url).trigger('change');
			$track.find('[data-cover-preview]').html('<img src="' + escapeHtml(item.url) + '" alt="">');
		}, false);
	});

	$('[data-select-cover-image]').on('click', function(){
		openMediaFrame('image', function(item){
			if (!item) return;
			$('[name="_nfinite_creator_cover_id"]').val(item.id);
			$('[data-cover-image-preview]').html('<img src="' + escapeHtml(item.url) + '" alt="">');
		}, false);
	});

	$('[data-remove-cover-image]').on('click', function(){
		$('[name="_nfinite_creator_cover_id"]').val('');
		$('[data-cover-image-preview]').empty();
	});

	$('[data-select-gallery]').on('click', function(){
		const existingIds = ($('[data-gallery-ids]').val() || '').split(',').filter(Boolean).map(Number);
		const frame = wp.media({
			title: labels.selectImage || 'Select images',
			button: { text: labels.useImage || 'Use images' },
			library: { type: 'image' },
			multiple: true
		});

		frame.on('open', function(){
			const selection = frame.state().get('selection');
			existingIds.forEach(function(id){
				const attachment = wp.media.attachment(id);
				attachment.fetch();
				selection.add(attachment);
			});
		});

		frame.on('select', function(){
			const selection = frame.state().get('selection').toJSON();
			const ids = [];
			let preview = '';
			selection.forEach(function(item){
				ids.push(item.id);
				const src = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
				preview += '<img src="' + escapeHtml(src) + '" alt="">';
			});
			$('[data-gallery-ids]').val(ids.join(','));
			$('[data-gallery-preview]').html(preview);
		});
		frame.open();
	});

	$('[data-clear-gallery]').on('click', function(){
		$('[data-gallery-ids]').val('');
		$('[data-gallery-preview]').empty();
	});

	/* ----------------------------------------------------------
	 * Video editor
	 * ---------------------------------------------------------- */
	function renumberVideos(){
		$('[data-admin-video-list] [data-admin-video]').each(function(index){
			const $row = $(this);
			$row.find('[data-video-number]').text(index + 1);
			$row.find('[name]').each(function(){
				const name = $(this).attr('name');
				if (name) $(this).attr('name', name.replace(/videos\[\d+\]/, 'videos[' + index + ']'));
			});
		});
	}

	$('[data-admin-video-list]').sortable({
		handle: '.nfinite-admin-track__head',
		placeholder: 'nfinite-admin-track-placeholder',
		update: renumberVideos
	});

	$(document).on('change', '[data-featured-video]', function(){
		if (this.checked) $('[data-featured-video]').not(this).prop('checked', false);
	});

	$(document).on('click', '[data-remove-admin-video]', function(){
		$(this).closest('[data-admin-video]').remove();
		renumberVideos();
	});

	$('[data-add-admin-video]').on('click', function(){
		const index = $('[data-admin-video-list] [data-admin-video]').length;
		const html = `
			<div class="nfinite-admin-track nfinite-admin-video" data-admin-video>
				<div class="nfinite-admin-track__head">
					<strong><span class="dashicons dashicons-menu nfinite-admin-track__handle"></span>${escapeHtml(labels.videoLabel || 'Video')} <span data-video-number>${index + 1}</span></strong>
					<button type="button" class="button-link-delete" data-remove-admin-video>${escapeHtml(labels.remove || 'Remove')}</button>
				</div>
				<div class="nfinite-admin-track__body">
					<div class="nfinite-admin-grid">
						<div class="nfinite-admin-field"><label>Title</label><input class="widefat" type="text" name="videos[${index}][title]"></div>
						<div class="nfinite-admin-field"><label>Video Type</label><select class="widefat" name="videos[${index}][type]"><optgroup label="Music &amp; Performance"><option>Music Video</option><option>Performance</option><option>Live Performance</option><option>Freestyle</option></optgroup><optgroup label="Shows &amp; Conversations"><option>Podcast</option><option>Podcast Episode</option><option>Interview</option><option>Talk / Discussion</option><option>Live Stream</option><option>Vlog</option></optgroup><optgroup label="News &amp; Editorial"><option>News Clip</option><option>Breaking News</option><option>News Report</option><option>Commentary</option><option>Opinion</option><option>Analysis</option><option>Explainer</option></optgroup><optgroup label="Long Form"><option>Documentary</option><option>Mini Documentary</option><option>Movie / Film</option><option>Feature / Story</option></optgroup><optgroup label="Entertainment &amp; Culture"><option>Gaming</option><option>Sports</option><option>Fashion</option><option>Comedy</option><option>Trailer</option><option>Behind the Scenes</option><option>Short / Clip</option><option>Tutorial</option><option>Showreel</option><option>Other</option></optgroup></select></div>
						<div class="nfinite-admin-field"><label>PairOfDice Channel / Programming</label><select class="widefat" name="videos[${index}][channel]"><option value="">No PairOfDice channel</option><option>The Wire</option><option>The Rotation</option><option>Mic Check</option><option>757 Radar</option><option>PairOfDice Gaming</option><option>PairOfDice Originals</option></select></div>
						<div class="nfinite-admin-field nfinite-admin-field--full"><label>YouTube / Vimeo URL</label><input class="widefat" type="url" name="videos[${index}][url]" placeholder="https://www.youtube.com/watch?v=..."></div>
						<div class="nfinite-admin-field"><label>Source / Publisher</label><input class="widefat" type="text" name="videos[${index}][source_name]"></div>
						<div class="nfinite-admin-field"><label>Source / Publisher URL</label><input class="widefat" type="url" name="videos[${index}][source_url]"></div>
						<div class="nfinite-admin-field nfinite-admin-field--full"><label>Original Source / Article URL</label><input class="widefat" type="url" name="videos[${index}][original_url]"></div>
						<div class="nfinite-admin-field nfinite-admin-field--full"><label>Description</label><textarea class="widefat" rows="3" name="videos[${index}][description]"></textarea></div>
						<div class="nfinite-admin-field nfinite-admin-field--full"><label class="nfinite-admin-toggle"><input type="checkbox" data-featured-video name="videos[${index}][featured]" value="1"><span><strong>${escapeHtml(labels.featuredVideo || 'Featured Video')}</strong><br>Show this video first and give it the primary video treatment.</span></label></div>
					</div>
				</div>
			</div>`;
		$('[data-admin-video-list]').append(html);
		renumberVideos();
	});

	/* ----------------------------------------------------------
	 * Release workspace
	 * ---------------------------------------------------------- */
	function releaseManager(){
		return $('[data-release-track-manager]');
	}

	function renumberReleaseTracks(){
		const $manager = releaseManager();
		const rows = $manager.find('[data-release-track-row]');

		rows.each(function(index){
			const $row = $(this);
			$row.find('[data-release-order]').text(index + 1);

			$row.find('[name^="_nfinite_release_track_items["]').each(function(){
				const name = $(this).attr('name');
				$(this).attr('name', name.replace(/_nfinite_release_track_items\[\d+\]/, '_nfinite_release_track_items[' + index + ']'));
			});
		});

		$manager.find('[data-release-track-empty]').toggleClass('is-hidden', rows.length > 0);
	}

	$('[data-release-track-sortable]').sortable({
		handle: '.nfinite-release-track-admin-row__handle',
		placeholder: 'nfinite-release-track-placeholder',
		update: renumberReleaseTracks
	});

	$(document).on('click', '[data-toggle-release-track]', function(){
		const $row = $(this).closest('[data-release-track-row]');
		const $details = $row.find('[data-release-track-details]');
		const open = !$details.prop('hidden');
		$details.prop('hidden', open);
		$(this).text(open ? 'Edit' : 'Done');
	});

	$(document).on('click', '[data-remove-release-track]', function(){
		$(this).closest('[data-release-track-row]').remove();
		renumberReleaseTracks();
	});

	function newAttachmentRow(item){
		const $manager = releaseManager();
		const index = $manager.find('[data-release-track-row]').length;
		const meta = item.meta || {};
		const title = meta.title || item.title || (item.filename ? item.filename.replace(/\.[^.]+$/, '') : 'Untitled Track');
		const artist = meta.artist || '';
		const duration = item.fileLength || item.lengthFormatted || meta.length_formatted || '';
		const audioUrl = item.url || '';

		return `
			<div class="nfinite-release-track-admin-row is-new" data-release-track-row data-attachment-id="${Number(item.id) || 0}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][track_id]" value="0">
				<input type="hidden" name="_nfinite_release_track_items[${index}][attachment_id]" value="${Number(item.id) || 0}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][title]" value="${escapeHtml(title)}">

				<div class="nfinite-release-track-admin-row__summary">
					<span class="dashicons dashicons-menu nfinite-release-track-admin-row__handle"></span>
					<span class="nfinite-release-track-admin-row__number" data-release-order>${index + 1}</span>
					<div class="nfinite-release-track-admin-row__main">
						<strong data-release-track-title>${escapeHtml(title)}</strong>
						<span>${escapeHtml(artist)}${artist && duration ? ' · ' : ''}${escapeHtml(duration)}</span>
					</div>
					<span class="nfinite-release-track-admin-row__badge">New</span>
					<button type="button" class="button-link" data-toggle-release-track>Edit</button>
					<button type="button" class="button-link-delete" data-remove-release-track>Remove</button>
				</div>

				<div class="nfinite-release-track-admin-row__details" data-release-track-details hidden>
					<div class="nfinite-admin-grid">
						<div class="nfinite-admin-field">
							<label>Title</label>
							<input class="widefat" type="text" data-pending-track-title value="${escapeHtml(title)}">
						</div>
						<div class="nfinite-admin-field">
							<label>Audio</label>
							<input class="widefat" type="url" value="${escapeHtml(audioUrl)}" readonly>
						</div>
						<div class="nfinite-admin-field nfinite-admin-field--full">
							<p class="description">This Track record will be created when you save/update the Release. Available WordPress/ID3 metadata will be imported automatically.</p>
						</div>
					</div>
				</div>
			</div>`;
	}

	$(document).on('input', '[data-pending-track-title]', function(){
		const $row = $(this).closest('[data-release-track-row]');
		const value = $(this).val();
		$row.find('[name$="[title]"]').val(value);
		$row.find('[data-release-track-title]').text(value || 'Untitled Track');
	});


	function newStreamingRow(){
		const $manager = releaseManager();
		const index = $manager.find('[data-release-track-row]').length;
		return `
			<div class="nfinite-release-track-admin-row is-new is-streaming" data-release-track-row>
				<input type="hidden" name="_nfinite_release_track_items[${index}][track_id]" value="0">
				<input type="hidden" name="_nfinite_release_track_items[${index}][attachment_id]" value="0">
				<input type="hidden" name="_nfinite_release_track_items[${index}][title]" value="Untitled Streaming Track" data-streaming-title-hidden>
				<input type="hidden" name="_nfinite_release_track_items[${index}][external_source]" value="direct_audio" data-streaming-source-hidden>
				<input type="hidden" name="_nfinite_release_track_items[${index}][source_url]" value="" data-streaming-url-hidden>

				<div class="nfinite-release-track-admin-row__summary">
					<span class="dashicons dashicons-menu nfinite-release-track-admin-row__handle"></span>
					<span class="nfinite-release-track-admin-row__number" data-release-order>${index + 1}</span>
					<div class="nfinite-release-track-admin-row__main">
						<strong data-release-track-title>Untitled Streaming Track</strong>
						<span data-streaming-summary>Direct Audio · URL required</span>
					</div>
					<span class="nfinite-release-track-admin-row__badge">Streaming</span>
					<button type="button" class="button-link" data-toggle-release-track>Done</button>
					<button type="button" class="button-link-delete" data-remove-release-track>Remove</button>
				</div>

				<div class="nfinite-release-track-admin-row__details" data-release-track-details>
					<div class="nfinite-admin-grid">
						<div class="nfinite-admin-field">
							<label>Track title</label>
							<input class="widefat" type="text" value="" placeholder="Track title" data-streaming-track-title>
						</div>
						<div class="nfinite-admin-field">
							<label>Playback Source</label>
							<select class="widefat" data-streaming-track-source>
								<option value="direct_audio">Direct Audio / Archive.org</option>
								<option value="spotify">Spotify</option>
								<option value="apple_music">Apple Music</option>
								<option value="soundcloud">SoundCloud</option>
							</select>
						</div>
						<div class="nfinite-admin-field nfinite-admin-field--full">
							<label>Track URL</label>
							<input class="widefat" type="url" placeholder="Paste the normal track URL — no iframe/embed code needed" data-streaming-track-url>
							<p class="description">Paste a direct MP3/M4A/WAV/OGG/AAC URL (including archive.org) or a normal Spotify, Apple Music, or SoundCloud track URL. Nfinite creates the Track record when you save/update this Release.</p>
						</div>
					</div>
				</div>
			</div>`;
	}

	$(document).on('click', '[data-quick-add-track]', function(){
		const $manager = $(this).closest('[data-release-track-manager]');
		const title = ($manager.find('[data-quick-track-title]').val() || '').trim();
		const source = $manager.find('[data-quick-track-source]').val() || 'direct_audio';
		const url = ($manager.find('[data-quick-track-url]').val() || '').trim();
		if (!url) {
			$manager.find('[data-quick-track-url]').trigger('focus');
			return;
		}
		const $row = $(newStreamingRow());
		$manager.find('[data-release-track-sortable]').append($row);
		$row.find('[data-streaming-track-title]').val(title || 'Untitled Streaming Track');
		$row.find('[data-streaming-track-source]').val(source);
		$row.find('[data-streaming-track-url]').val(url).trigger('input');
		$manager.find('[data-quick-track-title], [data-quick-track-url]').val('');
		$manager.find('[data-release-track-empty]').addClass('is-hidden');
		renumberReleaseTracks();
	});

	$(document).on('click', '[data-add-streaming-track]', function(){
		const $manager = $(this).closest('[data-release-track-manager]');
		$manager.find('[data-release-track-sortable]').append(newStreamingRow());
		renumberReleaseTracks();
	});

	$(document).on('input change', '[data-streaming-track-title], [data-streaming-track-source], [data-streaming-track-url]', function(){
		const $row = $(this).closest('[data-release-track-row]');
		const title = ($row.find('[data-streaming-track-title]').val() || '').trim() || 'Untitled Streaming Track';
		const source = $row.find('[data-streaming-track-source]').val() || 'direct_audio';
		const url = ($row.find('[data-streaming-track-url]').val() || '').trim();
		const names = { direct_audio: 'Direct Audio', spotify: 'Spotify', apple_music: 'Apple Music', soundcloud: 'SoundCloud' };
		$row.find('[data-streaming-title-hidden]').val(title);
		$row.find('[data-streaming-source-hidden]').val(source);
		$row.find('[data-streaming-url-hidden]').val(url);
		$row.find('[data-release-track-title]').text(title);
		$row.find('[data-streaming-summary]').text((names[source] || 'Streaming') + (url ? ' · Ready' : ' · URL required'));
	});

	function newArchiveRow(track){
		const $manager = releaseManager();
		const index = $manager.find('[data-release-track-row]').length;
		const title = (track.title || '').trim() || 'Untitled Track';
		const url = (track.url || '').trim();
		const artist = (track.artist || '').trim();
		const duration = (track.duration || '').trim();
		const identifier = (track.identifier || '').trim();
		const filename = (track.filename || '').trim();
		const summary = [artist, duration].filter(Boolean).join(' · ') || 'Internet Archive';
		return `
			<div class="nfinite-release-track-admin-row is-new is-streaming is-archive" data-release-track-row data-source-url="${escapeHtml(url)}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][track_id]" value="0">
				<input type="hidden" name="_nfinite_release_track_items[${index}][attachment_id]" value="0">
				<input type="hidden" name="_nfinite_release_track_items[${index}][title]" value="${escapeHtml(title)}" data-streaming-title-hidden>
				<input type="hidden" name="_nfinite_release_track_items[${index}][external_source]" value="internet_archive" data-streaming-source-hidden>
				<input type="hidden" name="_nfinite_release_track_items[${index}][source_url]" value="${escapeHtml(url)}" data-streaming-url-hidden>
				<input type="hidden" name="_nfinite_release_track_items[${index}][artist]" value="${escapeHtml(artist)}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][duration]" value="${escapeHtml(duration)}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][archive_identifier]" value="${escapeHtml(identifier)}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][archive_filename]" value="${escapeHtml(filename)}">

				<div class="nfinite-release-track-admin-row__summary">
					<span class="dashicons dashicons-menu nfinite-release-track-admin-row__handle"></span>
					<span class="nfinite-release-track-admin-row__number" data-release-order>${index + 1}</span>
					<div class="nfinite-release-track-admin-row__main">
						<strong data-release-track-title>${escapeHtml(title)}</strong>
						<span>${escapeHtml(summary)}</span>
					</div>
					<span class="nfinite-release-track-admin-row__badge">Archive</span>
					<button type="button" class="button-link" data-toggle-release-track>Edit</button>
					<button type="button" class="button-link-delete" data-remove-release-track>Remove</button>
				</div>

				<div class="nfinite-release-track-admin-row__details" data-release-track-details hidden>
					<div class="nfinite-admin-grid">
						<div class="nfinite-admin-field">
							<label>Track title</label>
							<input class="widefat" type="text" value="${escapeHtml(title)}" data-pending-track-title>
						</div>
						<div class="nfinite-admin-field">
							<label>Archive metadata</label>
							<input class="widefat" type="text" value="${escapeHtml(summary)}" readonly>
						</div>
						<div class="nfinite-admin-field nfinite-admin-field--full">
							<label>Internet Archive Audio</label>
							<input class="widefat" type="url" value="${escapeHtml(url)}" readonly>
							<p class="description">The audio remains hosted by Internet Archive. Nfinite will create/reuse a native Track record when you save/update this Release.</p>
						</div>
					</div>
				</div>
			</div>`;
	}

	$(document).on('click', '[data-import-archive-item]', function(){
		const $button = $(this);
		const $source = $button.closest('[data-archive-release-source]');
		const $input = $source.find('[data-archive-item-url]');
		const $status = $source.find('[data-archive-import-status]');
		const item = ($input.val() || '').trim();
		if (!item) {
			$status.text('Paste an Internet Archive item URL or identifier first.').removeClass('is-success').addClass('is-error');
			return;
		}

		$button.prop('disabled', true).text(labels.archiveLoading || 'Reading…');
		$status.removeClass('is-error is-success').text(labels.archiveLoading || 'Reading Internet Archive metadata…');

		$.ajax({
			url: labels.ajaxUrl || window.ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'nfinite_archive_item',
				nonce: labels.archiveNonce || '',
				item: item
			}
		}).done(function(response){
			if (!response || !response.success || !response.data) {
				const message = response && response.data && response.data.message ? response.data.message : (labels.archiveError || 'Import failed.');
				$status.text(message).addClass('is-error');
				return;
			}

			const data = response.data;
			const $manager = releaseManager();
			const $list = $manager.find('[data-release-track-sortable]');
			if (!$manager.length || !$list.length) {
				$status.text('Archive metadata loaded, but the Tracklist manager was not found on this screen.').addClass('is-error');
				return;
			}

			// Remove accidental unsaved /details/ rows so an Archive item page can never masquerade as one track.
			$manager.find('[data-release-track-row].is-new').each(function(){
				const $row = $(this);
				const source = $row.find('[data-streaming-source-hidden]').val() || $row.find('[data-streaming-track-source]').val() || '';
				const url = ($row.find('[data-streaming-url-hidden]').val() || $row.find('[data-streaming-track-url]').val() || '').trim();
				if (source === 'internet_archive' && /archive\.org\/(?:details|metadata)\//i.test(url)) $row.remove();
			});

			const existing = {};
			$manager.find('[data-release-track-row]').each(function(){
				const rowUrl = ($(this).attr('data-source-url') || $(this).find('[data-streaming-url-hidden]').val() || $(this).find('input[name*="[archive]"]').val() || '').trim();
				if (rowUrl) existing[rowUrl] = true;
			});

			let added = 0;
			(data.tracks || []).forEach(function(track){
				if (!track.url || existing[track.url]) return;
				$list.append(newArchiveRow(track));
				existing[track.url] = true;
				added++;
			});
			renumberReleaseTracks();

			if (data.item_url) $input.val(data.item_url);
			if ($source.find('[data-archive-fill-meta]').is(':checked')) {
				const $title = $('#title');
				if (data.title && $title.length && !($title.val() || '').trim()) {
					$title.val(data.title).trigger('input');
				}
				const $date = $('input[name="_nfinite_release_date"]');
				if (data.date && $date.length && !$date.val()) {
					const match = String(data.date).match(/^\d{4}-\d{2}-\d{2}/);
					if (match) $date.val(match[0]).trigger('change');
				}
			}

			const label = data.title ? '“' + data.title + '”' : data.identifier;
			const skipped = Math.max(0, (data.tracks || []).length - added);
			$status.text('Loaded ' + (data.tracks || []).length + ' Archive MP3 track' + ((data.tracks || []).length === 1 ? '' : 's') + ' from ' + label + '. Added ' + added + (skipped ? '; ' + skipped + ' already present.' : '.') + ' Save / Update the Release to create the Track records.').addClass('is-success');
		}).fail(function(xhr){
			let message = labels.archiveError || 'Could not import that Internet Archive item.';
			if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) message = xhr.responseJSON.data.message;
			$status.text(message).addClass('is-error');
		}).always(function(){
			$button.prop('disabled', false).text(labels.archiveImport || 'Import / Refresh Archive Data');
		});
	});

	$(document).on('input change', '[data-streaming-track-source], [data-streaming-track-url]', function(){
		const $row = $(this).closest('[data-release-track-row]');
		if (!$row.length) return;
		const source = $row.find('[data-streaming-track-source]').val() || '';
		const url = ($row.find('[data-streaming-track-url]').val() || '').trim();
		let $warning = $row.find('[data-archive-track-warning]');
		if (!$warning.length) {
			$warning = $('<p class="description nfinite-archive-track-warning" data-archive-track-warning></p>');
			$row.find('[data-streaming-track-url]').closest('.nfinite-admin-field').append($warning);
		}
		if (source === 'internet_archive' && /archive\.org\/(?:details|metadata)\//i.test(url)) {
			$warning.text('This is an Archive item page, not a direct audio file. Put it in Release Details → Internet Archive Item and use Import / Refresh Archive Data.').addClass('is-error');
		} else {
			$warning.text('').removeClass('is-error');
		}
	});

	$(document).on('click', '[data-add-audio-files]', function(){
		const frame = wp.media({
			title: 'Add audio to release',
			button: { text: 'Add to Release' },
			library: { type: 'audio' },
			multiple: true
		});

		frame.on('select', function(){
			const selected = frame.state().get('selection').toJSON();
			const $manager = releaseManager();
			const $list = $manager.find('[data-release-track-sortable]');

			selected.forEach(function(item){
				const attachmentId = Number(item.id) || 0;
				if (!attachmentId) return;

				if ($manager.find('[data-release-track-row][data-attachment-id="' + attachmentId + '"]').length) return;
				$list.append(newAttachmentRow(item));
			});

			renumberReleaseTracks();
		});

		frame.open();
	});

	$(document).on('click', '[data-add-existing-track]', function(){
		const $manager = $(this).closest('[data-release-track-manager]');
		const $select = $manager.find('[data-existing-track-select]');
		const trackId = parseInt($select.val(), 10);
		if (!trackId) return;

		if ($manager.find('[data-release-track-row][data-track-id="' + trackId + '"]').length) {
			$select.val('');
			return;
		}

		const index = $manager.find('[data-release-track-row]').length;
		const title = $select.find('option:selected').data('title') || $select.find('option:selected').text();

		const row = `
			<div class="nfinite-release-track-admin-row" data-release-track-row data-track-id="${trackId}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][track_id]" value="${trackId}">
				<input type="hidden" name="_nfinite_release_track_items[${index}][attachment_id]" value="0">
				<div class="nfinite-release-track-admin-row__summary">
					<span class="dashicons dashicons-menu nfinite-release-track-admin-row__handle"></span>
					<span class="nfinite-release-track-admin-row__number" data-release-order>${index + 1}</span>
					<div class="nfinite-release-track-admin-row__main">
						<strong data-release-track-title>${escapeHtml(title)}</strong>
						<span>Existing library track</span>
					</div>
					<span></span>
					<button type="button" class="button-link-delete" data-remove-release-track>Remove</button>
				</div>
			</div>`;

		$manager.find('[data-release-track-sortable]').append(row);
		$select.val('');
		renumberReleaseTracks();
	});

	/* Advanced standalone Track editor Media Library selector. */
	$(document).on('click', '[data-nfinite-select-track-audio]', function(){
		const frame = wp.media({
			title: labels.selectAudio || 'Select audio',
			button: { text: labels.useAudio || 'Use audio' },
			library: { type: 'audio' },
			multiple: false
		});
		frame.on('select', function(){
			const item = frame.state().get('selection').first().toJSON();
			$('[data-nfinite-track-audio-url]').val(item.url).trigger('change');
		});
		frame.open();
	});

	renumberTracks();
	renumberVideos();
	renumberReleaseTracks();
});
