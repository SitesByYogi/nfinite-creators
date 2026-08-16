# Nfinite Creators

## Version 0.3.0 — Video & Media

Adds first-class YouTube/Vimeo video management to Creator profiles and Creator Kits / EPKs.

### Admin video manager

Creators → Add/Edit Creator now includes a dedicated **Videos & Media** editor.

Admins can:

- Add multiple videos
- Drag and drop to reorder videos
- Remove videos
- Add a video title
- Choose a video type
- Add a description
- Mark one video as the Featured Video
- Paste YouTube or Vimeo URLs
- Keep videos hosted externally instead of uploading large video files to WordPress

Supported video types include:

- Music Video
- Performance
- Interview
- Reel
- Behind the Scenes
- Tutorial
- Showreel
- Other

### Creator Studio

The frontend `[nfinite_creator_dashboard]` now includes the same Video section so profile owners can manage their own YouTube/Vimeo content without wp-admin.

Only one Featured Video is allowed at a time.

### Public Creator profile

Published videos automatically render on the Creator profile.

- Responsive 16:9 embeds
- Featured Video receives primary full-width treatment
- Remaining videos display in an adaptive video grid
- Title, type, and description are displayed with each video
- Videos use WordPress oEmbed for YouTube/Vimeo
- No video file hosting is required on the WordPress server

### Creator Kit / EPK

Videos are part of the public creator presentation and sit alongside audio, portfolio imagery, biography, credits, press highlights, and booking information.

### Shortcode

A creator's videos can also be embedded elsewhere:

`[nfinite_creator_videos creator="123"]`

### Administration

The Creators list now displays both Track and Video counts.


## Previous release notes

## Version 0.2.0 — Admin Creator Profile Builder

This release turns the WordPress Creator editor into the full administrative profile-building experience.

### Admin profile builder

Administrators can now build a complete creator profile from **Creators → Add Creator / Edit Creator**.

- Assign the profile to any WordPress user
- Mark creators as Featured for WPNfinite Spotlight priority
- Manage tagline and location
- Use the native Featured Image as the profile photo
- Select a dedicated cover image from the WordPress Media Library
- Build a portfolio/photo gallery from the Media Library
- Manage website, social, streaming, booking, and contact links
- Use the normal WordPress editor for the full creator biography
- Use the Creator Types taxonomy for Artist, Producer, Photographer, Developer, and other roles

### Full admin audio manager

The old read-only audio JSON box has been replaced.

Admins can now:

- Add unlimited tracks
- Drag and drop to reorder tracks
- Remove tracks
- Set track title
- Choose Song, Beat, Demo, Mix, Production Reel, or Other
- Select audio directly from the WordPress Media Library
- Select track cover art from the Media Library
- Add credits / track notes
- Open existing audio files for review

Tracks use the same `_nfinite_creator_tracks` data consumed by the frontend Creator Studio and public audio player.

### Creator Kit / EPK

Expanded the Creator Kit with:

- Short press bio
- Notable credits / clients
- Achievements / press highlights
- Booking contact name
- Booking phone
- Booking/contact email
- Press-kit/download URL
- Public Creator Kit rendering on creator profiles

### Gallery

Added creator portfolio/gallery storage and public rendering on the single Creator profile.

### Administration

The Creators list now shows:

- Creator Type
- Owner
- Featured status
- Track count
- Creator Kit status

### Existing functionality retained

- Frontend Creator Studio
- Pending-review workflow for new frontend profiles
- Creator directory
- Creator Type archives
- Audio playlist player
- WPNfinite template override support


## Previous release notes

Nfinite Creators adds public creator profiles, frontend self-service profile management, audio playlists, and Creator Kit / EPK support to WordPress.

## Version 0.1.0

Initial creator platform release.

### Creator profiles

- Public `nfinite_creator` custom post type
- Creator Type taxonomy
- Default types for Artist, Producer, Designer, Developer, Audio Engineer, Photographer, Videographer, DJ, and Songwriter
- Profile photo and cover image
- Tagline, location, bio, booking/contact email
- Website, Instagram, TikTok, YouTube, Spotify, and SoundCloud links
- Public creator archive and Creator Type archives
- Theme override support:
  - `nfinite-creators/single-creator.php`
  - `nfinite-creators/archive-creators.php`

### Frontend creator studio

Use:

`[nfinite_creator_dashboard]`

Logged-in users can create and manage their own creator profile from the frontend. New profiles are submitted as Pending Review.

### Creator directory

Use:

`[nfinite_creator_directory]`

Optional:

`[nfinite_creator_directory type="artist" posts="12"]`

### Audio player

Use:

`[nfinite_creator_audio creator="123"]`

Creators can add songs, beats, demos, mixes, and production reels. Audio may be uploaded through the frontend studio or linked by URL.

### Creator Kit / EPK

Creators can enable a Creator Kit / EPK presentation using their profile bio, media, links, audio, and booking contact information.

### WPNfinite compatibility

The plugin ships neutral, dark media-oriented defaults and supports theme template overrides so WPNfinite can provide first-class presentation without owning creator data.

## Security model

- Frontend management requires authentication.
- Users may only edit creator profiles they own.
- New profiles default to Pending Review.
- Forms use WordPress nonces.
- Uploaded media is processed through the WordPress Media Library APIs.
