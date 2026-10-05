# Nfinite Creators 0.42.0 — Video Engagement + Creator Revenue Share

Nfinite Creators 0.42.0 extends the Nfinite Engagement and Creator Earnings architecture from audio into PairOfDice TV, Shows, Episodes, and creator-owned video programming.

## What is new

### Qualified video engagement
- New first-party video engagement table separate from simple video opens and legacy media analytics.
- Measures raw video sessions, validated watch time, qualified views, unique qualified viewers, completion, discovery surface, provider, and abuse flags.
- Initial qualification requires 60 seconds of validated watch time for normal long-form video, or 25% of shorter video with a 15-second floor.
- Repeat-pressure, oversized playback pulses, invalid positions, and other suspicious sessions can be excluded from payable engagement.
- PairOfDice TV and structured episode playback carry creator/content identity into the persistent video player so watch time survives WPNfinite in-place navigation.

### Video monetization eligibility
- Creator Studio episode submissions can request PairOfDice video revenue-share review.
- The creator must affirm that they control the necessary rights and authorize PairOfDice to monetize eligible on-platform engagement.
- Administrators receive a **PairOfDice Video Revenue Share** review box on Episodes and Video Library items.
- Statuses: `Not Enrolled`, `Pending Review`, `Monetized`, `Ineligible`, and `Suspended`.
- Only approved `Monetized` content can enter revenue-share accounting.

### Video Revenue Share V1
- New **Creators → Video Revenue Share** administration screen.
- Separate internal video reserve and configurable monthly allocation cap.
- The video reserve begins at $0; PairOfDice explicitly records funding before allocating creator revenue share.
- Monthly provisional allocations are weighted by eligible validated watch time, not raw page views or video opens.
- V1 payable engagement is limited to approved YouTube-hosted content watched through PairOfDice. YouTube revenue remains separate and continues to belong to the channel under its own YouTube arrangement.
- Finalization creates `video_engagement` entries in the same unified Nfinite Creator Earnings ledger used by commerce and streaming.
- Finalized video statements can be paid through the existing Stripe Connect transfer system.

### Creator Analytics V2 extension
- New **Qualified Video** analytics section with qualified views, qualified viewers, validated watch time, average completion, and flagged sessions.
- Creator Earnings now shows the current video accounting period alongside the current streaming period.
- Monthly Content Statements can include both `music_streaming` and `video_engagement` ledger entries.
- Video payouts automatically flow into the existing payout history.

## Revenue-share pipeline

```
Video playback on PairOfDice
  ↓
Raw watch event
  ↓
Validation + abuse checks
  ↓
Qualified video engagement
  ↓
Approved / Monetized video eligibility
  ↓
Eligible YouTube watch time
  ↓
Monthly video reserve allocation
  ↓
Provisional creator amount
  ↓
Finalized video statement
  ↓
Nfinite Creator Earnings ledger
  ↓
Stripe Connect payout
```

## Important V1 boundaries

- PairOfDice does not claim or replace YouTube monetization. PairOfDice revenue share is additional compensation funded by PairOfDice for eligible engagement occurring through PairOfDice.
- Merely embedding or opening a video does not create payable engagement.
- Vimeo engagement can be measured when player events are available, but only approved YouTube content enters Video Revenue Share V1.
- Legacy creator-video array items without a first-class Nfinite content ID are not payable in V1. Structured Episodes and first-class Video Library records are the authoritative revenue-share objects.
- Adding money to the internal video reserve is bookkeeping only. It does not charge a card or fund the Stripe platform balance.

## Testing path

1. Install 0.42.0 over 0.41.0.
2. In Creator Studio → Shows & Series, submit an episode with a YouTube URL and check the video revenue-share request/rights confirmation.
3. Publish the episode and confirm its revenue-share status is `Pending Review` in wp-admin.
4. In the episode editor, use **PairOfDice Video Revenue Share** to mark the episode `Monetized`.
5. Open **Creators → Video Revenue Share**, enable the program, add a small internal test reserve, and set a monthly cap.
6. Watch the episode from PairOfDice TV or its episode page long enough to create a qualified view.
7. Open Creator Studio → Analytics and confirm **Qualified Video** begins reporting watch data.
8. Return to **Creators → Video Revenue Share** and click **Sync provisional earnings**.
9. Confirm the creator receives a provisional allocation weighted by watch time.
10. In Stripe test mode, finalize the period and test the finalized statement/payout flow to a connected creator account.

## 0.43.0 - Distribution-Ready Music Catalog

- Added provider-neutral Nfinite Distribution Catalog metadata and readiness validation for releases and tracks.
- Added release distribution workflow states: Draft, Needs Metadata, Distribution Ready, Submitted to Provider, Delivered, Takedown Requested, and Provider Error.
- Added genre, metadata language, country of origin, original-release date, pre-order date, future provider, provider release ID, and internal distribution notes.
- Added track version/mix, lyrics language, explicit designation, and preview-start metadata.
- Added automatic validation for cover artwork, artist, release date, rights lines, territories, track ISRCs, artist/master/songwriter credits, and explicit status.
- Added provider-neutral JSON catalog export from the Release editor plus authenticated REST export at /wp-json/nfinite/v1/distribution/releases/{id}.
- No DSP delivery is performed in 0.43.0. The catalog/export contract is intentionally provider-neutral for a future B2B/white-label distribution integration.


## 0.44.0 — Monetization Infrastructure V1
- Adds first-party Campaigns, Advertisers, and reusable named Placements.
- Adds campaign scheduling, creative/destination fields, booked revenue, and creator revenue-share pools.
- Adds placement shortcode/API: `[nfinite_placement key="rotation_featured"]`; WPNfinite remains the presentation layer.
- Adds first-party impression/click event storage with campaign, placement, content, creator, session hash, and device dimensions.
- Adds Monetization dashboard with active campaigns, booked revenue, creator pools, impressions, clicks, CTR, and campaign reporting.
- Adds explicit creator-pool finalization weighted by campaign impressions attributed to creators. Finalized allocations enter the unified Creator Earnings ledger as `sponsorship`.
- Does not add programmatic ad networks, automated billing, or self-service advertiser checkout. V1 is designed for directly sold PairOfDice campaigns.
- Campaign rendering listens for WPNfinite `wpnfinite:navigation-complete` so analytics continue across persistent same-origin navigation.


## 0.45.0 — Sponsorships + Promoted Content
- Connects campaigns to creators, releases, videos/episodes, shows, events, and editorial content.
- Adds promotion types, sponsor disclosure labels, CTA labels, and promoted-content associations.
- Creates default PairOfDice monetization placements for Home, The Rotation, Radio, TV, Creator Profiles, Editorial, and Events.
- Adds `[nfinite_promoted_content surface="home_featured" limit="4"]` for sponsored content shelves/cards.
- Promoted content uses the existing first-party campaign impression/click analytics and creator attribution.
- Adds automatic sponsored disclosure to associated singular content.
- Keeps campaign revenue and creator sponsorship allocations in the unified 0.44 monetization / Creator Earnings infrastructure.

## 0.46.0 — Advertising Inventory + Campaign Delivery
- Adds campaign delivery goals for impressions and clicks, daily caps, visitor frequency caps, even/as-available pacing, and 1–10 campaign priority.
- Adds device, logged-in/logged-out audience, public post-type, and creator targeting controls.
- Adds placement-level daily capacity and placement format metadata for responsive, display, native, audio, and video sponsorship inventory.
- Adds up to five weighted creative variants per campaign with independent image, destination, copy, and creative keys.
- Standard placements now select eligible campaigns using delivery rules and under-delivery/priority scoring instead of simple random selection.
- Adds viewability-aware impression measurement: an impression is sent after at least 50% of the placement remains in view for one second.
- Adds 30-minute duplicate-impression suppression per visitor/campaign/placement/creative while clicks remain independently measurable.
- Promoted-content shelves now honor the same campaign schedule, targeting, caps, goals, and pacing rules.
- Monetization reporting now includes campaign goal/pace status plus a placement inventory utilization report for the current day.
- Preserves WPNfinite persistent-navigation support through `wpnfinite:navigation-complete`.


## 0.47.0 — Creator Opportunities + Brand Deals
- Adds first-class Brand Opportunities and Brand Applications.
- Brands/campaign managers can define compensation, deadlines, slots and deliverables.
- Public opportunity hub shortcode: `[nfinite_opportunities]`.
- Logged-in creator owners can apply with a pitch; duplicate applications are prevented.
- Admin workflow tracks applied, invited, accepted, in-progress, submitted, approved, declined and cancelled deals.
- Deliverable/proof URLs can be attached to applications.
- Approved paid brand-deal compensation is finalized into the unified Creator Earnings ledger as sponsorship revenue.
- Optional linkage to existing Nfinite monetization Campaign IDs keeps opportunities compatible with the 0.44–0.46 advertising stack.

## 0.48.0 — Creator Memberships V1
- Creator-owned membership tiers with monthly pricing and benefits.
- WooCommerce product mapping and WooCommerce Subscriptions lifecycle hooks when available.
- Member entitlement storage and automatic grant/revoke for subscription status changes.
- Public, any-member, and specific-tier access rules across tracks, releases, videos, episodes, shows, creator posts, posts, and events.
- Timed early access that automatically becomes public at the configured date/time.
- `[nfinite_memberships creator_id="123"]` tier cards and checkout CTAs.
- Locked-content replacement with creator membership CTA.


## 0.49.0 — Organizations + Rosters V1
Adds organization profiles for labels, management, agencies, podcast networks, media companies, collectives, studios and promoters; creator roster relationships; delegated organization team roles; Organization Studio; public organization rosters; organization switching; and reusable directory/roster shortcodes.


## 0.50.0 — Follow + Save + Personal Library
- Fans can follow creator and organization profiles.
- Fans can save releases, tracks, shows, episodes, videos, events, creator posts, and editorial posts.
- Added AJAX follow/save toggles with per-user persistence.
- Added `[nfinite_library]`, `[nfinite_follow_button]`, and `[nfinite_save_button]` shortcodes.
- Added a personal library view with Following and Saved sections.
- Singular creator/organization profiles receive Follow controls; supported content receives Save controls.
- Designed for WPNfinite persistent navigation through delegated front-end event handling.

## 0.51.0 — Discovery V2
- Adds first-party Trending, Popular, New, Following, and Recommended discovery feeds.
- Ranks global discovery using qualified audio/video engagement, unique audience, consumption time, saves, and recency while excluding high-fraud engagement from ranking inputs.
- Rolls track engagement up to releases and episode engagement up to shows so discovery can surface catalog-level objects as well as individual media.
- Uses 0.50 follow/save intent to personalize Following and Recommended feeds for logged-in fans, with sensible global fallbacks for new/logged-out users.
- Adds `[nfinite_discovery]` and `[nfinite_discovery_hub]` shortcodes plus `GET /wp-json/nfinite/v1/discovery` for theme/app integrations.
- Discovery ranking is deliberately separate from payable engagement and creator earnings.

## 0.52.0 — Unified Search
- Added one public search layer across creators, organizations, releases, tracks, shows, episodes, videos, events, creator stories, and editorial posts.
- Added grouped filters for Creators, Organizations, Music, TV & Video, Events, and Stories.
- Added relevance ranking with exact/prefix title boosts, keyword matching, searchable Nfinite metadata, creator/organization relationship matching, and a light recency signal.
- Added Relevance/Newest sorting, pagination, responsive search cards, and Personal Library save actions on supported content.
- Added `[nfinite_search]` and public REST endpoint `/wp-json/nfinite/v1/search`.
- Search is read-only and remains separate from engagement qualification, discovery ranking, and creator earnings.

## 0.53.0 — Creator + Fan Notifications

- Added first-party in-app notification storage with unread/read state and duplicate suppression.
- Added a Notifications page plus `[nfinite_notifications]` and `[nfinite_notification_bell]` shortcodes.
- Fans who follow creators can receive notifications for new releases, tracks, videos, shows, episodes, events, creator posts, stories and members-only exclusives.
- Creators receive new-follower and membership activity notifications.
- Brand-deal applicants receive in-app notifications when application/deal status changes.
- Added per-user notification preferences for music, video, posts, events, memberships, opportunities and follows.
- Added AJAX mark-read / mark-all-read interactions and authenticated REST endpoints at `/wp-json/nfinite/v1/notifications`.
- Notification generation remains separate from engagement, Discovery V2 and payable Creator Earnings metrics.

## 0.54.0 — Playlists + Collections V2
- Adds first-class `nfinite_playlist` objects for fan playlists, creator-curated playlists, organization collections, and PairOfDice editorial collections.
- Supports music-only playlists and mixed-media collections with ordered references to tracks, releases, videos, shows, episodes, events, and stories.
- Adds Public, Unlisted, and Private visibility with owner-aware access controls.
- Adds `[nfinite_playlists]`, `[nfinite_playlist]`, and `[nfinite_playlist_builder]` front-end experiences plus an authenticated playlist management AJAX layer.
- Music playlists hand playable track queues directly to the existing persistent Nfinite music player.
- Playlists can be saved into Personal Library and participate in Discovery V2 and Unified Search.
- Adds a public read-only `/wp-json/nfinite/v1/playlists` endpoint.

## 0.55.0 — Financial Admin + Creator Statements V2

- Adds **Creators → Financial Admin**, a consolidated operations view over the unified Creator Earnings ledger.
- Period summaries show gross revenue, PairOfDice platform retention, finalized creator earnings, provisional earnings, paid/transferred amounts, currently payable balances, and reversals/refunds.
- Adds monthly creator statement snapshots without creating a second financial ledger.
- Accounting periods can be closed/refreshed or reopened; reopening removes statement snapshots only and never deletes underlying ledger entries.
- Adds creator-level payout holds and a configurable minimum payout threshold.
- Adds auditable manual creator credit adjustments.
- Adds partial/full ledger reversals; already-transferred entries use the existing Stripe transfer reversal path.
- Adds CSV exports for an entire accounting period and individual creator statements.
- Adds `[nfinite_creator_statements]` for authenticated creators to access finalized statement history.
- Commerce, streaming, video revenue share, sponsorships/brand deals, memberships, events, distribution, editorial, affiliates, and adjustments continue to use the same unified ledger.
- This release does not automatically batch-transfer all payable balances. Existing source-specific Stripe transfer controls remain authoritative to avoid duplicate payouts.


## 0.56.0 — Security, Permissions + Data Integrity Hardening
- Adds Creators → System Health with read-only launch-readiness checks for required Nfinite tables, creator ownership, orphaned earnings references, and duplicate ledger keys.
- Adds configurable rate limiting to public analytics, qualified audio/video engagement, and monetization event endpoints.
- Adds conservative X-Content-Type-Options, Referrer-Policy, and Permissions-Policy response headers.
- Keeps existing financial/payout capability and nonce protections intact and does not mutate financial data during audits.

## 0.56.1 - PairOfDice TV Full-Width Layout Fix
- Makes the PairOfDice TV hub full-bleed even when rendered inside a constrained WordPress page/content wrapper.
- Removes the visible native horizontal scrollbar from TV rails while preserving mouse-wheel, trackpad, keyboard, and touch scrolling.
- Prevents the TV root and common page wrappers from clipping rail content.
- Keeps all fixes scoped to the TV hub so other site layouts are unchanged.

## 0.56.2 - Editable TV Page + Full-Width Ownership Fix
- Stops continuously recreating/reserving the `/tv/` page on every WordPress request.
- Keeps the existing PairOfDice TV page as a normal editable WordPress page using `[nfinite_tv_hub]`.
- Automatically moves the untouched generated TV page to WPNfinite's Full Width template when available.
- Preserves the canonical `/tv/` URL instead of forcing editors into `/tv-2/`.
- If the stored TV page reference becomes stale, Nfinite safely re-adopts an existing `/tv/` page without overwriting its content.
- Activation can still create the TV page on a fresh install, but normal runtime no longer fights manual page management.


## 0.56.3 - Mobile Lock-Screen Playback Hardening
- Keeps the persistent native audio element background-playback friendly with `preload="auto"` and `playsinline`.
- Reinforces active Media Session playback state when the page is backgrounded or the phone is locked.
- Synchronizes Media Session duration and position state for more reliable lock-screen controls on supported mobile browsers.
- Does not auto-resume stopped audio or bypass browser autoplay policies; playback still begins from a user gesture.


## 0.56.4 — Videos Hub + Mobile Background Playback
- Gives the `/videos/` route an explicit full-width shell and improved desktop/mobile video-grid proportions.
- Polishes the featured video, Trending Now rail, creator row, category sections, spacing, and responsive layout.
- Moves iPhone/iPad lock-screen playback back to a native-audio-first path by avoiding Media Session position/background mutations while Safari is backgrounding.
- Keeps Android/desktop Media Session metadata and transport support intact.


## 0.56.5 — Videos Hub Lead Layout Polish
- Removed desktop grid stretching that created excess empty space below the featured video.
- Restored a natural 16:9 featured-video frame.
- Widened the Trending Now rail and gave compact thumbnails fixed 16:9 proportions so they no longer appear squeezed or vertically stretched.
- Kept the existing responsive/mobile behavior intact.

### 0.56.6 — Videos Hub Trending Metadata Polish
- Removes category pills from compact Trending Now cards to reduce unnecessary vertical height.
- Moves the creator/source name directly beneath each Trending thumbnail.
- Keeps the video title in the adjacent text column and clamps long titles to prevent oversized rows.
- Tightens Trending Now row spacing while preserving 16:9 thumbnails and responsive behavior.


## 0.56.7 — Discovery + Video Hub UX Polish
- Corrected the Videos hub News heading so it no longer renders as ‘Newss’ and added intentional plural labels for video categories.
- Homepage Recommended/Trending discovery becomes a horizontal touch carousel on mobile.
- Discovery cards without dedicated artwork now fall back to the owning creator’s profile image.
- Save/Saved controls on discovery cards now use a high-contrast treatment that stands out clearly on white card surfaces.


## 0.56.8 — Mobile Discovery Single-Row Carousel
- Forces discovery feeds into one non-wrapping horizontal row on mobile, including homepage Recommended and Trending sections.
- Keeps swipe/trackpad scrolling, scroll snapping, and hidden scrollbars.
- Prevents theme or earlier grid rules from falling back to a two-column mobile layout.


## 0.56.9 — Homepage Discovery Carousel Cascade Fix
- Moves the mobile single-row carousel enforcement into a late-rendered, scoped style block so theme CSS loaded after the plugin cannot restore the two-column grid.
- Uses one non-wrapping swipe row for homepage/platform discovery cards through 760px.
- Preserves desktop discovery grids and existing card artwork/save-button polish.


## 0.56.10 — Creators Directory Artist Default
- `/creators/` now opens with the Artist creator type selected and filtered by default.
- The Artist tab keeps the clean `/creators/` URL.
- The All tab remains available at `/creators/?type=all`.
- Other creator-type taxonomy tabs continue to work normally.


## 0.56.12 — Signup Journey Routing
- Improves the logged-in state of the Join PairOfDice page.
- Existing creators are sent to Creator Studio instead of seeing a signup form.
- Signed-in users without a creator profile now get a clear Create Your Profile path into Creator Studio, where the existing profile form creates their pending creator profile.

## 0.56.11 — Creator Directory 12 Per Page
- Updates the public `/creators/` archive and creator-type views to display 12 creator profiles per page instead of the WordPress/default 10.
- Preserves Artist as the default `/creators/` tab and the existing pagination/filter behavior.


## 0.58.2
- Adds an unobtrusive “Is this your profile?” Creator Intelligence claim CTA to unclaimed public creator profiles.
- Passes the PairOfDice creator ID, creator name, and canonical profile URL to Creator Intelligence for a direct claim flow.
- Hides the claim CTA automatically for claimed creators and publisher accounts.
