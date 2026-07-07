# Epiktetos 1.2.2 Release Notes

Epiktetos 1.2.2 is a focused CSS maintenance release for the homepage editorial section rhythm.

## What's Fixed in 1.2.2

- The divider between the Latest Articles module and the first Category Showcase section now has proper breathing room before the category heading.
- The fix is scoped to the homepage content column: `.ts-home__content .ts-cats__inner`.
- Mobile receives a slightly tighter matching rhythm through the existing `max-width: 600px` homepage rules.
- Content, post queries, category logic, Gutenberg-editable pages, Article Voiceover, thumbnail cropping, and Theme Settings behavior are unchanged.

## Highlights

- Editorial block theme architecture for essays, journals, and publication-style sites.
- Native WordPress templates, menus, taxonomies, and settings.
- Polished homepage, archive, search, author, topic, and static page experiences.
- Bundled local Sample Content with posts, pages, menus, taxonomies, comments, and featured images.
- Optional per-post Article Voiceover using audio selected from the WordPress Media Library.
- Lightweight frontend audio player with native browser fallback when JavaScript is unavailable.

## Accessibility

- Semantic document structure and landmarks.
- Keyboard-visible focus states.
- Accessible navigation, pagination, labels, and article structures.
- Screen-reader-conscious visual-only interface elements.

## Performance

- No unnecessary external JavaScript libraries.
- Conditional asset loading for theme features.
- Token-based styling and native WordPress rendering where possible.

## Theme Settings

- Consolidated admin settings under Appearance > Epiktetos.
- Controls for editorial sections, taxonomy behavior, SEO metadata, discussion display, and reader experience.

## Menus

- Native WordPress menu registration for primary and footer navigation.
- Fallback navigation remains available when menus are not assigned.

## Typography

- Editorial typography tuned for long-form reading.
- Responsive type scale and spacing using theme tokens.

## Dark Mode

- Dark mode styling for primary templates, archives, search, author pages, discussion areas, and reader components.
- Token-based colors to maintain consistency across the theme.

## Reader Features

- Single post reading progress.
- Table of contents support.
- Related articles.
- Newsletter call to action.
- Editorial discussion experience.
- Article Voiceover player on single posts when an audio attachment is selected.

## Article Voiceover

- Adds an Article Voiceover meta box to posts.
- Lets editors select an audio attachment from the native Media Library.
- Stores only the audio attachment ID in post meta.
- Renders the player only on single posts with a valid audio file.
- Uses progressive enhancement: native audio controls remain available if JavaScript does not run.

## Sample Content

- Sample Content is bundled inside the theme and does not require remote downloads or plugins.
- The importer creates local posts, pages, menus, taxonomies, comments, theme options, and featured images.
- Removal is scoped to theme-stamped sample content so user content is left untouched.

## WordPress.org Readiness

- GPL-compatible licensing.
- WordPress theme metadata.
- Localization-ready theme structure.
- Documentation, changelog, support, contribution, security, and code of conduct files for public repository use.
