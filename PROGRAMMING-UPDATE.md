# Nfinite Creators 0.57.0: PairOfDice Programming

This update adds **WordPress Admin → Programming**. Install alongside PairOfDice Player 0.2.0 to share Music and Radio selections with the app. PairOfDice TV controls apply to the existing website TV hub; this does not add TV playback to the music app.

## Install

1. Back up your WordPress database and current plugin folders.
2. In Plugins → Add New → Upload Plugin, upload `nfinite-creators-0.57.0-programming.zip`. Choose **Replace current with uploaded**. Keep Nfinite Creators active.
3. Upload `pairofdice-player-0.2.0.zip` the same way, replacing PairOfDice Player.
4. Clear your WordPress/page/CDN cache and refresh the app. Existing playback queues keep their current tracks until you start a new selection.
5. Open **Programming → Latest Tracks**. The automatic defaults allow one track per release and two per artist. Save any changes you make.

These packages have not been installed on your server by this update workflow. Test the replacement on staging before applying it to your live site. If rollback is needed, replace the two plugin folders with their previous versions; the new programming option is additive and does not rewrite published content.

## Controls

| Section | What it controls |
| --- | --- |
| Featured release | Music Hub hero and app featured release. Automatic retains the existing Music Hub selection/featured flags. |
| New Releases | Ordered release picks, exclusions and optional artist limit. |
| Latest Tracks | Ordered track picks, exclusions and artist/release limits. |
| Music creator spotlights | Featured creator selections for the Music Hub and app. |
| PairOfDice Radio | Independent track picks/exclusions, shared by website Radio and the app. |
| PairOfDice TV | TV hero and eligible TV content, selectable by video, episode, show or creator. |

**Automatic** uses eligible recent content and ignores picks. **Handpicked only** uses the selected list and may intentionally be empty. **Mixed** places eligible picks first, then fills automatically. Exclusions win in every mode. Up/Down changes pick priority. A diversity override lets explicit Music picks exceed numerical caps, but never bypass publication, parent visibility, exclusions or the existing Latest Tracks visibility checkbox.

Search by title, add individual picks/exclusions, or filter tracks by their release ID and select all loaded results. Load additional result pages before using a bulk action when selecting a large release. Each section saves independently. The form warns before leaving with unsaved changes.

For the album flooding Latest Tracks: keep **Automatic** with the new caps for a balanced selection, or use **Mixed** and choose the specific lead song you want. To hide something only from that promotional section, add it to that section's exclusions. Its release, profile and the player's full catalog remain available.

Existing **Show in Latest / Featured Tracks** checkboxes still apply to Latest Tracks, including explicit picks. Radio ignores that checkbox and uses its own rules. Radio requires direct audio and continues to exclude beats/instrumentals. Picks enter ahead of automatic tracks, subject to artist spacing; adjacent repeats can occur when no different artist remains. Explicit listener shuffle can change the supplied order. The app continues through handpicked Radio lists larger than 50 tracks using paged batches. Automatic portions are refreshed between requests, so Radio is not a guaranteed no-repeat playlist.

For TV, items must still be marked for TV in their existing editors. Picking a show/creator selects their eligible episodes/videos. Excluding one removes their content from the TV hub. The first eligible item is the hero. Legacy embedded creator videos can be controlled by creator; their per-video settings remain in the existing editor. Music creator spotlights and TV creator selections are independent. TV content is filtered before applying the display limit, so older picks remain selectable.

## Validation and limits

- 20 executable PHP fixture checks cover filling past 250 hidden uploads, selection modes, ordering, exclusions, caps, protected/draft parents, Radio independence/deduplication/artist spacing, TV groups and administrator/nonce enforcement.
- All 80 core PHP files parse successfully; the companion plugin passes PHP syntax validation.
- Player tests and JavaScript syntax checks passed. The mobile SVG controls and iOS Media Session policy are retained.
- PHP checks ran under PHP WebAssembly with WordPress function fixtures. A full WordPress/database session, rendered admin UI and real-phone playback were not exercised here.
- Public Radio/TV still build their eligible pools server-side. Large catalogs should be checked for response time on staging.
- No changes to analytics/earnings recording, creator ownership, login requirements or publication status. No automatic update service, native application or installable PWA is added.

Run the focused core fixture checks with `php tests/programming.php`. No test touches the live database.
