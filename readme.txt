=== Open Builder ===
Contributors: openbuilder
Tags: page builder, visual builder, drag and drop, editor, landing page
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An original, open-source drag-and-drop visual website builder for WordPress.

== Description ==

Open Builder is a lightweight, original visual builder for WordPress. It lets
you design pages and posts visually with a live, drag-and-drop editor and a set
of core widgets, then renders clean, fast HTML/CSS on the front end.

Open Builder is independent software. It does not contain, copy, or derive from
the code of Breakdance, Elementor, or any other commercial builder.

Features:

* Live drag-and-drop editor with a real-time, server-rendered canvas (true WYSIWYG).
* 29 widgets across Layout, Basic, Media, Marketing, Interactive, Dynamic and Advanced groups — including Video, Gallery, Accordion, Tabs, Counter, Progress Bar, Testimonial, Star Rating, Icon Box, Icon List, Social Icons and Google Map.
* Theme builder: design Headers, Footers, Single, Archive, Search and 404 templates and assign them with display conditions.
* Dynamic widgets: Post Title, Post Content, Site Logo, Nav Menu, and a Posts loop for archives/blogs.
* Per-breakpoint responsive style controls (desktop / tablet / mobile).
* Global brand settings (colors, fonts, sizes) exposed as CSS variables.
* Form builder with database storage, email notifications, spam honeypot, and an admin entries viewer.
* Custom CSS per element and custom CSS classes/IDs.
* Built for current WordPress and PHP 8+, with security-first input handling.

== Security ==

* Every editor REST route requires an authenticated user with `edit_post` and a valid nonce.
* The node tree is recursively sanitized on save against each widget's control schema.
* Style values are restricted to a whitelisted CSS property/value subset (no url(), expression(), or script vectors).
* Form submissions are CSRF-protected with per-form nonces and validated against the saved field schema.
* All front-end output is escaped at the point of rendering.

== Installation ==

1. Upload the `open-builder` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Edit any page or post and click "Edit with Open Builder".

== Frequently Asked Questions ==

= Does this require a build step? =

No. The editor is plain JavaScript and runs as soon as the plugin is installed.

= Where is page data stored? =

As a sanitized JSON tree in post meta (`_openb_tree`), with compiled CSS cached
in `_openb_compiled_css`.

== Changelog ==

= 1.6.0 =
* Layers tree: drag-to-reorder and re-nest elements (drop before/after/inside containers), plus expand/collapse toggles.
* Inline editing: double-click a Heading, Text, Button or Icon Box title on the canvas to edit its text in place; Enter or click-away commits, Esc cancels.
* Links no longer navigate inside the editor canvas.

= 1.5.0 =
* Performance: compiled CSS is now written to real, cacheable stylesheet files in uploads/open-builder/ (a site-wide global.css for brand variables + a per-page page-{id}.css) instead of an inline <style> on every request.
* Files generate on save, rebuild lazily if missing, and transparently fall back to inline CSS when the filesystem isn't writable.
* "Regenerate CSS Cache" tool on the Open Builder dashboard; cache files are removed on uninstall and when a post is deleted.

= 1.4.0 =
* Right-click context menu on canvas blocks and layer rows: Edit, Copy, Cut, Paste (inside/after), Duplicate, Move Up/Down, Delete.
* Clipboard with copy/cut/paste across pages (localStorage) and keyboard shortcuts (Cmd/Ctrl+C/X/V/D, Delete).
* Page Settings panel: title, layout (default/full/boxed), hide title, content max-width, page background, body classes, per-page custom CSS, and per-page custom JS (gated behind the unfiltered_html capability and only emitted for capable authors).

= 1.3.0 =
* Visual style controls replacing raw text inputs: icon button-groups, slider + unit pickers, linked 4-side padding/margin box (with link toggle), color popover (native picker + brand swatches), border builder, and box-shadow builder.
* Alignment controls — horizontal (Text Align) for all elements, plus flexbox Direction, Justify (main axis) and Align (cross axis) for containers (vertical + horizontal).
* Background builder: Color, Image (size/position/repeat + overlay color) and Gradient (from/to/angle), per breakpoint. Background image URLs are emitted through a controlled, escaped url().

= 1.2.0 =
* 12 new widgets: Video (privacy-friendly YouTube/Vimeo facade + self-hosted), Gallery, Icon Box, Icon List, Star Rating, Testimonial, Accordion, Tabs, Counter, Progress Bar, Social Icons, Google Map (keyless embed).
* New control type: multi-image Gallery picker (WP media library, multi-select).
* Front-end runtime for interactive widgets: accessible accordion + tabs (ARIA + keyboard), scroll-triggered counters and progress bars, click-to-load video facade.
* Front-end assets now also load inside theme-builder templates (e.g. an accordion in a footer).

= 1.1.0 =
* Theme builder: Header, Footer, Single, Archive, Search and 404 templates.
* Display-conditions engine (include/exclude rules with specificity-based resolution).
* Theme takeover via a minimal, theme-agnostic canvas template (wp_head/wp_footer preserved).
* New dynamic widgets: Post Title, Post Content, Site Logo, Nav Menu, Posts loop.
* Admin: Templates menu, template Type + Display Conditions metaboxes, dashboard Theme Builder section.

= 1.0.0 =
* Initial release.
