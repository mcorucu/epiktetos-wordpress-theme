=== Epiktetos ===
Contributors: mcorucu
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.0
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, one-column, editor-style, block-styles, full-site-editing, custom-colors, custom-menu

A contemplative editorial block theme with generous spacing, a warm neutral palette, and typographic clarity for personal blogs and essays.

== Description ==

Epiktetos is a full site editing (block) theme for personal blogs, essays, and newsletters. It pairs Libre Baskerville headings with Inter body text, a warm stone palette, sharp edges, and a single-column reading column for long-form text.

Fonts are bundled with the theme (self-hosted WOFF2) and loaded with font-display: swap, so the theme makes no external font or tracking requests.

Features:

* Full site editing with block templates and template parts (header, footer).
* Self-hosted Libre Baskerville and Inter fonts (no Google Fonts requests).
* Light and dark color modes with a header toggle.
* Primary and Footer navigation menu locations (Appearance > Menus).
* Editorial homepage: hero, latest articles, and a category showcase.
* Reading aids: estimated reading time, reading progress, saved articles, and editor picks.
* SEO output: JSON-LD structured data, Open Graph, and canonical URLs.
* Accessibility: a skip link and a single main-content landmark in every template.
* Admin tools: a setup wizard, theme settings, optional Sample Content, and a release validator.

== Installation ==

1. In your WordPress dashboard go to Appearance > Themes > Add New > Upload Theme.
2. Upload the theme zip file and click Install Now.
3. Click Activate.
4. Follow the Setup Wizard under Appearance > Epiktetos, or configure settings manually.
5. Optionally create menus under Appearance > Menus; starter Primary and Footer menus are created on activation and can be edited freely.

== Frequently Asked Questions ==

= Does this theme load Google Fonts or any external resources? =

No. Libre Baskerville and Inter are bundled with the theme as WOFF2 files and served from your own site. The theme makes no external font or tracking requests.

= How do I edit the navigation? =

Use Appearance > Menus. The theme registers two locations, Primary Navigation and Footer Navigation. Starter menus are created on activation; edit or replace them at any time. If a location has no menu assigned, the theme falls back to a small default list.

= How do I switch between light and dark mode? =

Use the theme toggle in the header. The choice is remembered per visitor in the browser.

= How do I add the Sample Content? =

Go to Appearance > Epiktetos > Sample Content. Creating Sample Content is idempotent and adds bundled local posts, pages, menus, taxonomies, and images. It does not download anything or install plugins.

= Is the theme translation ready? =

Yes. All strings use the "epiktetos" text domain and the theme loads translations from its languages directory.

== Changelog ==

= 1.1.0 =
* Added Article Voiceover support for single posts.
* Added native media-library audio selection in the post editor.
* Added a lightweight, accessible frontend audio player.
* Added progressive fallback to native browser audio controls.

= 1.0.2 =
* Added bundled Sample Content export based on the local Epiktetos demo site.
* Improved Sample Content creation for posts, pages, menus, taxonomies and featured images.
* Kept the setup wizard media picker fix from 1.0.1.

= 1.0.0 =
* Initial public release.
* Full site editing block theme with editorial homepage, single, archive, search, author, and page templates.
* Self-hosted Libre Baskerville and Inter fonts (no external requests).
* Light/dark mode, reading aids, SEO metadata, and accessibility landmarks.
* Setup wizard, theme settings, Sample Content tools, and a release validator.

== Upgrade Notice ==

= 1.1.0 =
Adds optional per-post Article Voiceover: choose an audio file in the editor and readers get a calm audio player on single posts.

= 1.0.2 =
Adds a complete local Sample Content package and keeps the setup wizard media picker fix.

= 1.0.0 =
Initial public release.

== Resources ==

This theme bundles the following third-party resources. Everything else in the
theme is the original work of the theme author.

Fonts (in assets/fonts/):

* Inter — Copyright (c) 2016-2020 The Inter Project Authors, SIL Open Font License 1.1.
  Source: https://fonts.google.com/specimen/Inter
* Libre Baskerville — Copyright (c) 2012 Pablo Impallari, Rodrigo Fuenzalida, SIL Open Font License 1.1.
  Source: https://fonts.google.com/specimen/Libre+Baskerville
* Full font license text: assets/fonts/LICENSE.md

Images and icons:

* All SVG and PNG assets in assets/svg/, assets/icons/, and assets/brand/
  (the Epiktetos logo marks and the interface/social link icons) are original
  works created for this theme by the theme author, licensed under GPLv2 or
  later. The social link icons depict third-party brands solely to label
  outbound links and imply no affiliation or endorsement.

Unless otherwise noted, all theme code and assets are the original work of the
theme author and are licensed under the GNU General Public License v2 or later
(https://www.gnu.org/licenses/gpl-2.0.html).

== Credits ==

Built by Mehmet Can Orucu — https://blog.mcorucu.com/
