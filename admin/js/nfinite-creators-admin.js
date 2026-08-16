jQuery(function($){
	'use strict';

	function renumberTracks(){
		$('.nfinite-admin-track-list .nfinite-admin-track').each(function(index){
			const $row = $(this);
			$row.attr('data-index', index);
			$row.find('[data-track-number]').text(index + 1);

			$row.find('[name]').each(function(){
				const name = $(this).attr('name');
				if (!name) return;
				$(this).attr('name', name.replace(/tracks\[\d+\]/, 'tracks[' + index + ']'));
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
				<strong><span class="dashicons dashicons-menu nfinite-admin-track__handle"></span>${NfiniteCreatorsAdmin.trackLabel} <span data-track-number>${index+1}</span></strong>
				<button type="button" class="button-link-delete" data-remove-admin-track>${NfiniteCreatorsAdmin.remove}</button>
			</div>
			<div class="nfinite-admin-track__body">
				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field"><label>Title</label><input class="widefat" type="text" name="tracks[${index}][title]"></div>
					<div class="nfinite-admin-field"><label>Track Type</label><select class="widefat" name="tracks[${index}][type]"><option>Song</option><option>Beat</option><option>Demo</option><option>Mix</option><option>Production Reel</option><option>Other</option></select></div>
					<div class="nfinite-admin-field nfinite-admin-field--full">
						<label>Audio</label>
						<input class="widefat" type="url" data-audio-url name="tracks[${index}][audio_url]">
						<div class="nfinite-admin-actions"><button type="button" class="button" data-select-audio>${NfiniteCreatorsAdmin.selectAudio}</button></div>
					</div>
					<div class="nfinite-admin-field">
						<label>Cover Art</label>
						<input class="widefat" type="url" data-cover-url name="tracks[${index}][cover_url]">
						<div class="nfinite-admin-actions"><button type="button" class="button" data-select-cover>${NfiniteCreatorsAdmin.selectImage}</button></div>
						<div class="nfinite-admin-media-preview" data-cover-preview></div>
					</div>
					<div class="nfinite-admin-field"><label>Credits / Notes</label><textarea class="widefat" rows="4" name="tracks[${index}][credits]"></textarea></div>
				</div>
			</div>
		</div>`;
		$('.nfinite-admin-track-list').append(html);
		renumberTracks();
	});

	function openMediaFrame(type, callback){
		const frame = wp.media({
			title: type === 'audio' ? NfiniteCreatorsAdmin.selectAudio : NfiniteCreatorsAdmin.selectImage,
			button: { text: type === 'audio' ? NfiniteCreatorsAdmin.useAudio : NfiniteCreatorsAdmin.useImage },
			library: { type: type },
			multiple: false
		});
		frame.on('select', function(){
			callback(frame.state().get('selection').first().toJSON());
		});
		frame.open();
	}

	$(document).on('click', '[data-select-audio]', function(){
		const $track = $(this).closest('.nfinite-admin-track');
		openMediaFrame('audio', function(item){
			$track.find('[data-audio-url]').val(item.url).trigger('change');
		});
	});

	$(document).on('click', '[data-select-cover]', function(){
		const $track = $(this).closest('.nfinite-admin-track');
		openMediaFrame('image', function(item){
			$track.find('[data-cover-url]').val(item.url).trigger('change');
			$track.find('[data-cover-preview]').html('<img src="'+item.url+'" alt="">');
		});
	});

	$('[data-select-cover-image]').on('click', function(){
		openMediaFrame('image', function(item){
			$('[name="_nfinite_creator_cover_id"]').val(item.id);
			$('[data-cover-image-preview]').html('<img src="'+item.url+'" alt="">');
		});
	});


	$('[data-select-gallery]').on('click', function(){
		const existingIds = ($('[data-gallery-ids]').val() || '').split(',').filter(Boolean).map(Number);
		const frame = wp.media({
			title: NfiniteCreatorsAdmin.selectImage,
			button: { text: NfiniteCreatorsAdmin.useImage },
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
				preview += '<img src="' + src.replace(/"/g,'&quot;') + '" alt="">';
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

	$('[data-remove-cover-image]').on('click', function(){
		$('[name="_nfinite_creator_cover_id"]').val('');
		$('[data-cover-image-preview]').empty();
	});

	renumberTracks();

	function renumberVideos(){
		$('[data-admin-video-list] [data-admin-video]').each(function(index){
			const $row = $(this);
			$row.find('[data-video-number]').text(index + 1);
			$row.find('[name]').each(function(){
				const name = $(this).attr('name');
				if (!name) return;
				$(this).attr('name', name.replace(/videos\[\d+\]/, 'videos[' + index + ']'));
			});
		});
	}

	$('[data-admin-video-list]').sortable({
		handle: '.nfinite-admin-track__head',
		placeholder: 'nfinite-admin-track-placeholder',
		update: renumberVideos
	});

	$(document).on('change', '[data-featured-video]', function(){
		if (this.checked) {
			$('[data-featured-video]').not(this).prop('checked', false);
		}
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
				<strong><span class="dashicons dashicons-menu nfinite-admin-track__handle"></span>${NfiniteCreatorsAdmin.videoLabel} <span data-video-number>${index + 1}</span></strong>
				<button type="button" class="button-link-delete" data-remove-admin-video>${NfiniteCreatorsAdmin.remove}</button>
			</div>
			<div class="nfinite-admin-track__body">
				<div class="nfinite-admin-grid">
					<div class="nfinite-admin-field"><label>Title</label><input class="widefat" type="text" name="videos[${index}][title]"></div>
					<div class="nfinite-admin-field"><label>Video Type</label><select class="widefat" name="videos[${index}][type]"><option>Music Video</option><option>Performance</option><option>Interview</option><option>Reel</option><option>Behind the Scenes</option><option>Tutorial</option><option>Showreel</option><option>Other</option></select></div>
					<div class="nfinite-admin-field nfinite-admin-field--full"><label>YouTube / Vimeo URL</label><input class="widefat" type="url" name="videos[${index}][url]" placeholder="https://www.youtube.com/watch?v=..."></div>
					<div class="nfinite-admin-field nfinite-admin-field--full"><label>Description</label><textarea class="widefat" rows="3" name="videos[${index}][description]"></textarea></div>
					<div class="nfinite-admin-field nfinite-admin-field--full"><label class="nfinite-admin-toggle"><input type="checkbox" data-featured-video name="videos[${index}][featured]" value="1"><span><strong>${NfiniteCreatorsAdmin.featuredVideo}</strong><br>Show this video first and give it the primary video treatment.</span></label></div>
				</div>
			</div>
		</div>`;
		$('[data-admin-video-list]').append(html);
		renumberVideos();
	});

	renumberVideos();

});