=== Open Builder ===
Contributors: openbuilder
Tags: page builder, visual builder, drag and drop, editor, landing page
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
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
* Core widgets: Section, Columns, Heading, Text, Button, Image, Icon, Spacer, Divider, Custom HTML, Form.
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

= 1.0.0 =
* Initial release.
