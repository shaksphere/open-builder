=== Open Builder ===
Contributors: openbuilder
Tags: page builder, visual builder, drag and drop, editor, landing page
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.18.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An original, open-source drag-and-drop visual website builder for WordPress.

== Description ==

Open Builder is a lightweight, original visual builder for WordPress. It lets
you design pages and posts visually with a live, drag-and-drop editor and a set
of core widgets, then renders clean, fast HTML/CSS on the front end.

Open Builder is independent software. It does not contain, copy, or derive from
the code of Breakdance, Elementor, or any other commercial builder.

**Beta:** Open Builder is in active development. Please try it on a fresh page or
a staging site first. By design it only affects pages you build with it and
leaves theme/Elementor pages untouched (site-wide theme templates are opt-in
under Open Builder &rarr; Settings). If anything looks off, deactivate the plugin
(your content is restored immediately) and report it on GitHub.

Features:

* Live drag-and-drop editor with a real-time, server-rendered canvas (true WYSIWYG).
* 32 widgets across Layout, Basic, Media, Marketing, Interactive, Dynamic and Advanced groups — including Video, Gallery, Accordion, Tabs, Counter, Progress Bar, Testimonial, Star Rating, Icon Box, Icon List, Social Icons, Google Map and a Shortcode embed.
* Theme builder: design Headers, Footers, Single, Archive, Search and 404 templates and assign them with display conditions.
* Popups: design popups visually with triggers (load, exit-intent, scroll, click, inactivity), display conditions, frequency capping and an accessible front-end dialog.
* Dynamic widgets: Post Title, Post Content, Site Logo, Nav Menu, and a Posts loop for archives/blogs.
* Dynamic data binding: bind any text/link/image control to post fields, custom fields (post meta), ACF, or site data, with a fallback.
* Query Loop: design one card and repeat it over a query (post type, count, columns, order, taxonomy/term filter), with each card bound to its own post.
* Per-breakpoint responsive style controls (desktop / tablet / mobile).
* Global brand settings (colors, fonts, sizes) exposed as CSS variables.
* Form builder with 10 field types, database storage, email notifications, a spam honeypot, an admin entries viewer, and post-submit actions (redirect, webhook, auto-reply).
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

= 1.18.0 =
* Form actions: after a submission you can now redirect to a thank-you URL, POST the entry as JSON to a webhook, and/or send an auto-reply email to the submitter (subject + message, sent to the first email field). All configured per form in the Form widget's settings; the existing notification email and database storage still apply.

= 1.17.0 =
* Form fields: the Form widget gains Dropdown, Radio buttons, Checkboxes, Number, Date and Hidden field types (alongside Text, Email, Phone and Textarea). Choice fields take a comma-separated option list; fields can have a placeholder and a default value. Choices and required groups are validated on the server against the saved schema.

= 1.16.0 =
* Shortcode widget: drop in any registered WordPress shortcode (e.g. [open_form id="2"], contact-form-7, gallery) and it renders in place on both the front end and the editor canvas.
* Widget search: a search box at the top of the Widgets panel filters the widget list by name as you type, hiding empty categories.

= 1.15.0 =
* Section Library: a "＋ Section Library" button in the Widgets panel inserts ready-made layouts (Hero, Call to action, Two columns, Three features, Testimonial) you can then edit.
* Import / Export: a new "I/O" button in the toolbar exports the current page (or block) as JSON to download or copy, and imports JSON to replace the content — handy for backups or moving a design between pages/sites.
* Hide on device: each element's Advanced tab now has "Hide on Desktop / Tablet / Mobile" toggles, using non-overlapping responsive ranges so each is independent.

= 1.14.1 =
* Saved / Global Blocks: "Save as Global Block" — right-click any element (section, column, widget) to turn it into a reusable block in one step; the element is replaced with a reference to the new block.
* Fix: blocks with custom styling now render correctly wherever they are used — the block's compiled CSS is included on the host page (previously a styled block could appear unstyled on a page that used it).

= 1.14.0 =
* Saved / Global Blocks: design a block once and reuse it across pages with the new "Global Block" widget. Create blocks under Open Builder → Blocks, then drop a Global Block on any page and pick which block to show. Editing the block updates it everywhere it appears — ideal for a shared CTA, footer band or banner. Reference cycles are guarded against.

= 1.13.1 =
* Query Loop: optional pagination. Turn on "Show pagination" to add accessible page links below the loop (uses an ?ob_page=N parameter so it works on any page). Use one paginated loop per page.

= 1.13.0 =
* Query Loop: a new widget that repeats a card template you design over a query. Drop a Query Loop, build one card inside it (e.g. Featured Image + Post Title + Excerpt + a "Read more" button bound to the permalink), and it renders across your chosen posts. Configure post type, number of items, columns, order, an optional taxonomy/term filter, and an offset. Each card resolves its dynamic bindings to its own post — turning Open Builder into a real CMS layout tool.

= 1.12.0 =
* Display conditions can now target a specific page or post, not just a whole post type. The condition dropdown lists your individual pages (and recent posts/CPT entries) as "Page: About", "Post: Hello world", etc., so a header/footer/template or popup can be assigned to exactly one page. A specific-page rule is treated as the most specific match.

= 1.11.0 =
* Important fix: Open Builder no longer alters pages it didn't build. Previously a site-wide header/footer template would take over the whole site — including pages built with your theme or another builder (e.g. Elementor) — and could change their layout. Now the theme-template takeover only applies to pages built with Open Builder, plus archive/search/404 pages that have a matching template.
* New: Open Builder → Settings, with a "Theme Templates" option to apply your header/footer/templates across the entire site. It is OFF by default; enable it only once your whole site is built with Open Builder.
* This makes Open Builder safe to run alongside Elementor and other builders, and to migrate to it page by page without breaking anything.

= 1.10.0 =
* Dynamic data binding: bind any text, link or image control to live data — Post Title, Excerpt, Content, Date, URL, Featured Image, Author, Site title/tagline/URL, the current year, a custom field (post meta) or an ACF field (when ACF is active).
* Each bindable control gets a "Dynamic" toggle in the editor: pick a source, an optional field/meta key, and a fallback for when the value is empty. Bound fields show a readable placeholder (e.g. [Post Title]) on the canvas and resolve to real data on the front end.
* Works anywhere the builder renders — pages, posts, and theme-builder templates (header/footer/single/archive) — so you can design one template that adapts per post.

= 1.9.0 =
* Popups: design a popup visually with Open Builder, then choose a trigger — on load (after a delay), exit-intent, scroll depth %, click a CSS selector, or after inactivity.
* Assign popups with the same display-conditions engine as the theme builder (include/exclude rules), and cap how often they show (every view / once per session / once every N days).
* Accessible front end: popups render as ARIA dialogs with a focus trap, ESC to close, overlay-click and close button, background scroll lock, and reduced-motion support.
* Admin: a Popups list under the Open Builder menu and a Popups section on the dashboard; trigger, conditions, appearance and frequency are set on the popup edit screen.

= 1.8.0 =
* Globals: site-wide custom CSS box (Globals panel) — sanitized on save and printed on every front-end page, with a live preview in the editor canvas.
* SEO: per-page meta title, meta description and social/OG image fields in Page Settings, output in the document head. Open Builder automatically defers to Yoast, Rank Math, All in One SEO or SEOPress when one is active, so there are no duplicate tags.
* Accessibility: alt-text prompt on the Image widget; a heading-order linter in the Layers panel (flags a missing/duplicate H1 and skipped levels); a WCAG color-contrast hint on every color control; a "Skip to content" link and visible keyboard focus (:focus-visible) styles on the front end.

= 1.7.1 =
* Fix: builder text now renders with the same fonts and heading sizes in the editor and on the front end (true WYSIWYG). Builder content (.ob-root) gets an explicit typographic baseline using the brand fonts, instead of inheriting the browser default in the editor's bare canvas and the theme's fonts on the front end. Heading sizes use em so the per-element Font Size control still scales them, and no direct color is set so the Text Color control keeps working.

= 1.7.0 =
* Theme header & footer in the builder: when editing a page or post, the resolved site header now renders above and the footer below the live page content, so you design in real context (true WYSIWYG). They render once, server-side, outside the editable region, so your page edits never disturb them.
* Header/footer are shown as read-only "template regions": click one (or its "Edit Header/Footer" badge) to open that template in the builder.
* "Add Header" / "Add Footer" affordances appear in the canvas when no template applies; they create a site-wide template and open it for editing.
* New topbar "Chrome" toggle to show/hide the header & footer chrome for a clean canvas.

= 1.6.3 =
* Fix: sections set to Full Width now actually span the viewport on the front end, matching the editor. Builder content breaks out of the theme's (narrow, centered) content column; Boxed sections still re-center, and the page-level Boxed layout keeps everything inside the theme column.

= 1.6.2 =
* Fix: background colour (and other newly-set style maps) silently failed to save. PHP encodes empty maps as JSON arrays; setting a breakpoint key on a JS array was dropped by JSON.stringify. The tree is now normalised to objects on load.
* Color picker now has a "Done" button to close it.
* Switching background Type (or picking a content icon) no longer scrolls the Style panel back to the top.

= 1.6.1 =
* Fix: the color popover (Background, Text Color, Border, Shadow, Page Background) was clipped by the inspector's scroll container and could be unreachable, so a background color couldn't be picked or applied. The picker now expands inline as a full-width panel.

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
