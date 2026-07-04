# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to semantic versioning.

## [1.2.0] - 2026-07-03

### Fixed

- Latest Articles thumbnail cropping for portrait images — the post-card media wrapper stretched to the (taller) text column, leaving a blank area under the correctly-cropped image; it now hugs its aspect-ratio frame (`align-self: start`) so portrait, landscape, and square images fill consistently with `object-fit: cover`.
- Applied the same post-card thumbnail consistency fix to the archive/category/tag/search row cards.

### Added

- Theme Settings controls for the visible labels/headings used by shortcode-rendered modules — homepage Latest "Read more", Category Showcase "View all"/CTAs, sidebar Publication Stats/Editor Picks/Continue Reading, the Topics index headings and empty states, and the About pillars/Start Reading/Author/Newsletter copy (Homepage + Editorial tabs; defaults reproduce the shipped text).
- Homepage, Topics, About, and Contact copy now lives in real WordPress Page content, edited as blocks in the Gutenberg editor.
- Static front Page ("Home") that drives the homepage: hero, latest articles, category showcase, and sidebar are shortcode blocks; the Editor's Note is an editable block.
- Module shortcodes so dynamic sections stay live inside editable page content: `[epiktetos_topics_index]`, `[epiktetos_about_modules]`, `[epiktetos_contact_email]`, `[epiktetos_social_links]`, `[epiktetos_front_page]`.
- Optional footer copyright text override (with `%year%` and `%site%` placeholders).

### Changed

- About/Topics/Contact renderers are now thin wrappers that output the page's block content inside the existing PHP layout wrappers, preserving the design while making all copy editable in the editor.
- Theme Settings now hold only global/config values (contact email, small homepage module labels, footer, header); page-specific copy was moved out of Settings into the page editor.
- Bundled Sample Content updated so fresh installs create editable Home/About/Topics/Contact Pages and configure the static front page.

## [1.1.2] - 2026-07-03

### Fixed

- Favicon/site icon synchronization from Epiktetos Theme Settings to the WordPress native Site Icon option.
- Duplicate/conflicting fallback favicon output when a custom site icon is selected.
- Post thumbnail card image scaling and cropping for portrait images.

### Changed

- Improved archive, category, tag, and homepage listing thumbnail consistency.
- Minor UX stability improvements.

## [1.1.1] - 2026-07-02

### Fixed

- Admin Settings toolbar buttons (Save, Reset, Export, Import, and tab navigation) that could stop responding.
- A fatal error in the Theme health check (undefined `demo_categories()` helper) that aborted the Settings page render and prevented the admin scripts from loading.

### Changed

- Improved admin UI reliability and interaction handling.
- Minor bug fixes and stability improvements.

## [1.1.0] - 2026-07-02

### Added

- Article Voiceover support for single posts.
- Native WordPress Media Library audio selection in the post editor.
- Lightweight, accessible frontend audio player with play/pause, timeline, mute, and speed controls.
- Progressive fallback to native browser audio controls when JavaScript is unavailable.

## [1.0.2] - 2026-06-28

### Added

- Bundled Sample Content export based on the local Epiktetos demo site.
- Local Sample Content media package for featured images.

### Changed

- Improved Sample Content creation for posts, pages, menus, taxonomies, comments, theme options, and featured images.
- Kept the setup wizard media picker fix from 1.0.1.

## [1.0.0] - 2026-06-27

### Added

- Initial public release of the Epiktetos WordPress block theme.
- Full site editing templates for home, archive, category, tag, author, search, single post, static pages, and error states.
- Editorial homepage sections, latest articles, category showcase, and publication-style footer.
- Single post reading experience with reading progress, table of contents, related articles, newsletter call to action, and discussion styling.
- Dark mode support using theme tokens.
- Native WordPress menu support for primary and footer navigation.
- Theme settings for editorial, layout, taxonomy, SEO, discussion, and admin experience controls.
- Topic discovery, archive rows, category ordering, and tag-related topic modules.
- Accessibility, performance, and WordPress.org readiness refinements for the initial release.
