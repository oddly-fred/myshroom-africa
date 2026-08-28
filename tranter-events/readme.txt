=== Tranter IT Events ===
Contributors: tranter-it
Requires at least: 6.0
Requires PHP: 7.4
Version: 1.0.0
License: GPLv2 or later

A lightweight WordPress events manager for Tranter IT. It does not require Elementor or create extra WordPress pages.

== Installation ==
1. Copy the `tranter-events` directory into `wp-content/plugins/`.
2. Activate Tranter IT Events from Plugins.
3. Open Tranter Events > Event Types and confirm the default terms.
4. Open Tranter Events > Partners to add logos and partner metadata.
5. Create events under Tranter Events > Add New Event.
6. Add `[tranter_events]` to any page.

== Shortcode attributes ==
`[tranter_events limit="6" show_filters="true" show_gallery="true" show_videos="true"]`

== Notes ==
Event status is calculated from the WordPress current time and the event timezone. Normal event cards open an accessible modal; events configured with a dedicated landing page expose that URL in the modal. Registration URLs open in a new tab. Gallery media uses comma-separated attachment IDs and videos accept one YouTube or Vimeo URL per line.
