# Open Builder

**A genuinely free, open-source visual website builder for WordPress.**

Open Builder is a drag-and-drop front-end builder for WordPress aimed at people
who just want to build a decent-looking site without hitting a paywall. It is
**free, not freemium** — there is no "Pro" version, no locked widgets, no
upgrade nag. Everything in the repo is everything you get.

It's an alternative to the popular commercial builders (Elementor, Breakdance,
Divi, and friends) for **smaller sites** that need solid basic front-end
functionality — sections, columns, headings, buttons, images, forms, a theme
header/footer, popups — without the weight, cost, or sprawling integration
surface of the big builders. If you're building a marketing site, a small
business site, or a landing page and you don't need a hundred third-party
integrations, this is for you.

> This is an open-source project I'm building in the open with
> [Claude Code](https://claude.com/claude-code). The commit history reflects
> that collaboration.

> [!WARNING]
> **Open Builder is in beta.** Please try it on a **fresh page or a staging
> site first** — not straight on your live homepage.
>
> By design, Open Builder only affects pages you actually build with it. It
> leaves pages built with your theme or another builder (e.g. Elementor)
> untouched. Site-wide theme templates (headers/footers across every page) are
> **opt-in** under **Open Builder → Settings** and off by default.
>
> If you hit any unintended effect, **deactivate the plugin** (your content is
> safe — deactivating immediately restores your previous output) and please
> **[open an issue on GitHub](https://github.com/shaksphere/open-builder/issues)**
> with what you saw. Bug reports are very welcome and genuinely helpful while the
> plugin matures.

## Project status

Open Builder is under active development. It's already usable, but I'm still
refining the core feature set. **Once I'm happy with the base functionality, I
plan to submit it to the official
[WordPress.org plugin directory](https://wordpress.org/plugins/) so it can be
installed for free, directly from the WordPress dashboard** — keeping with the
free, no-paywall goal of the project. Until then, install it manually with the
instructions below.

---

## Why it exists

Most "free" WordPress builders are really free *trials*: the genuinely useful
controls — spacing, typography, a theme builder, popups, dynamic data — sit
behind a yearly subscription. Open Builder's goal is to cover the **basic
front-end development needs of a small website** with no paywall:

- Visual, icon-based style controls (not raw CSS text boxes).
- A single, honest feature set — what's here is free and stays free.
- Lightweight and original: **no build step**, no proprietary code, and not
  derived from any commercial builder.
- Security-first: sanitize on input, escape on output, capability-gated custom
  code, nonce-guarded REST.

It is **not** trying to be an enterprise page builder. If you need deep
WooCommerce theming, hundreds of integrations, or a marketplace of add-ons, a
commercial builder will serve you better. Open Builder is deliberately focused
on the basics, done cleanly.

## Features

- **Live drag-and-drop editor** with a real-time, server-rendered canvas (true
  WYSIWYG — the same PHP renderer drives the editor and the front end).
- **32 widgets** across Layout, Basic, Media, Marketing, Interactive, Dynamic
  and Advanced groups — including Video, Gallery, Accordion, Tabs, Counter,
  Progress Bar, Testimonial, Star Rating, Icon Box, Icon List, Social Icons,
  a keyless Google Map, and a Shortcode widget for embedding any registered
  WordPress shortcode.
- **Widget search** in the editor panel — filter the widget list by name as you
  type.
- **Form builder** with Text, Email, Phone, Number, Textarea, Dropdown, Radio,
  Checkbox, Date, File upload and Hidden fields — database storage, email
  notifications (with file attachments), a spam honeypot, an admin entries
  viewer, and post-submit actions (redirect, webhook, auto-reply to the
  submitter).
- **Visual style controls**: icon button-groups, slider + unit pickers, linked
  padding/margin box, colour popover, border/shadow builders, background
  (colour/image/gradient), per-breakpoint responsive controls.
- **Layers tree** with drag-to-reorder/re-nest, inline text editing, right-click
  context menu, copy/paste and keyboard shortcuts.
- **Theme builder**: design Headers, Footers, Single, Archive, Search and 404
  templates and assign them with a display-conditions engine — and edit the
  header/footer **in context** inside the page builder.
- **Popups** with triggers (load, exit-intent, scroll depth, click, inactivity),
  display conditions, frequency capping, and an accessible front-end dialog.
- **Dynamic data binding**: bind any text/link/image control to post fields,
  custom fields (post meta), ACF, or site data, with a fallback.
- **Query Loop**: design one card and repeat it over a query (post type, count,
  columns, order, taxonomy/term filter); each card binds to its own post.
- **Saved / Global Blocks**: design a block once and reuse it across pages;
  editing the block updates it everywhere it's used.
- **Section library**: insert ready-made section layouts (hero, CTA, features,
  testimonial) and edit them.
- **Import / Export**: export a page or block as JSON to back up or move between
  pages and sites; import JSON to replace content.
- **Responsive visibility**: hide any element on desktop, tablet, or mobile.
- **Global brand settings** (colours, fonts, sizes) as CSS variables, plus a
  site-wide custom CSS box.
- **SEO helpers** (per-page meta title/description/OG image) that automatically
  **defer to Yoast / Rank Math / AIOSEO / SEOPress** when one is active.
- **Accessibility**: alt-text prompts, a heading-order linter, colour-contrast
  hints, a skip link, and focus-visible styles.
- **Form builder** with database storage, email notifications, a spam honeypot,
  and an admin entries viewer.
- **Performance**: compiled CSS is written to cacheable files (with an inline
  fallback), not re-inlined on every request.

## Requirements

- WordPress 6.2 or newer
- PHP 8.0 or newer

## Installation

Open Builder is a normal WordPress plugin. There is **no build step** — the
editor is plain JavaScript that runs the moment the plugin is active.

### Option A — Upload the ZIP (recommended for most users)

1. Download this repository as a ZIP:
   click the green **Code** button above → **Download ZIP** (or grab a release).
2. The ZIP will contain a folder like `open-builder-main`. Rename that inner
   folder to `open-builder` and re-zip it (WordPress expects the plugin folder
   to be named `open-builder`). *If you cloned with git, skip this — the folder
   is already named correctly.*
3. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
4. Choose the ZIP and click **Install Now**, then **Activate**.

### Option B — Install manually via FTP / file manager

1. Download or clone this repo.
2. Copy the plugin folder into your site at:
   `wp-content/plugins/open-builder/`
   (so that `wp-content/plugins/open-builder/open-builder.php` exists).
3. In WordPress admin go to **Plugins** and **Activate** "Open Builder".

### Option C — Clone with git

```bash
cd wp-content/plugins
git clone https://github.com/shaksphere/open-builder.git
```

Then activate it from the **Plugins** screen.

## Usage

1. After activating, edit any page or post and click **"Edit with Open
   Builder"** (also available from the Pages/Posts list and the Open Builder
   admin menu).
2. Drag widgets onto the canvas, style them with the visual controls, and save.
3. Use the **Open Builder** admin menu for global brand settings, the Theme
   Builder (headers/footers/templates), Popups, and form entries.

Your page data is stored as a sanitized JSON node tree in post meta, and the
front end is rendered by the same PHP renderer used in the editor, so what you
design is what visitors see.

## Roadmap & scope

Open Builder already covers the core of what most smaller-business sites need:
full-site design (pages, plus headers / footers / single / archive / 404
templates with display conditions), **forms**, **popups**, **dynamic content**
(data binding + query loops), **saved / global blocks**, a **section library**,
**SEO** and **accessibility** helpers, **import/export**, and responsive controls
including hide-on-device. The goal is a complete, no-paywall front-end builder for
**full site customisation on smaller sites** — not an enterprise everything-tool.

To stay lightweight, some larger or more niche capabilities are intentionally
**not part of the core** and may arrive later as optional extras (or not at all):

- **WooCommerce / e-commerce widgets** (product cards, cart, checkout, a Product
  template type) — only useful for online stores. Parked as a possible future
  add-on so the core stays lean for the majority who don't run a shop.
- Entrance / scroll animations and sticky elements.
- More form field types and actions (webhooks, redirects, autoresponders).
- Custom breakpoints; loading only the assets a page actually uses.

If one of these would be valuable to you, please open an issue — interest helps
prioritise what's worth adding without bloating the plugin.

## Contributing

Issues and pull requests are welcome. The guiding principles:

- **No build step** — keep the editor as plain JS that runs from `plugins/`.
- **Original code only** — no code copied from commercial builders.
- **Single source-of-truth renderer** for both the front end and the editor.
- **Security first** — sanitize on input, escape on output, gate anything that
  writes CSS/JS, and nonce every write.
- **Backwards-compatible data** — never break an existing saved page tree.

## License

[GPL-2.0-or-later](LICENSE). Open Builder is independent software and is not
affiliated with, derived from, or containing code from Breakdance, Elementor,
or any other commercial builder.
