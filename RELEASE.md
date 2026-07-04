# Epiktetos 1.2.0 Release Notes

Epiktetos 1.2.0 is a content-editability release. The Homepage, Topics, About, and Contact copy now lives in real WordPress Page content and is edited as blocks in the Gutenberg editor, while the existing PHP layout wrappers preserve the design. Dynamic sections (latest articles, topics index, category showcase, contact email, social links) stay live via module shortcodes placed as blocks inside the page content.

## What's New in 1.2.0

- Fixed Latest Articles (and archive/category/tag/search) post-card thumbnails so portrait/landscape/square featured images crop consistently to the card frame with no blank area under the image.
- Homepage/Topics/About/Contact copy is edited in the block editor as real Page content, not in Theme Settings.
- Theme Settings expose the small visible labels of the shortcode-rendered modules (homepage Latest/Showcase/sidebar, Topics index, About modules) so reusable headings/buttons/captions stay editable without touching code; page-specific prose remains in the page editor.
- The homepage is a static front Page ("Home") assembled from shortcode blocks plus an editable Editor's Note block.
- Module shortcodes keep dynamic sections live inside editable content: `[epiktetos_topics_index]`, `[epiktetos_about_modules]`, `[epiktetos_contact_email]`, `[epiktetos_social_links]`, `[epiktetos_front_page]`.
- Theme Settings now hold only global/config values (contact email, small homepage module labels, footer, header).
- Optional footer copyright text override, with `%year%` and `%site%` placeholders.
- Bundled Sample Content updated so fresh installs create editable Home/About/Topics/Contact Pages and set the static front page.
- Design, layout, typography, colors, and behavior are preserved.

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
