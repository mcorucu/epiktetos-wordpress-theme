# Contributing

Thank you for your interest in contributing to Epiktetos. This project aims to remain a clean, native WordPress block theme with strong accessibility, performance, and editorial reading defaults.

## How to Contribute

1. Open an issue for bugs, compatibility problems, accessibility issues, or focused enhancement proposals.
2. Keep pull requests small and scoped to one concern.
3. Follow existing theme architecture and WordPress coding conventions.
4. Avoid adding external dependencies unless there is a clear maintenance and performance benefit.
5. Include testing notes with every pull request.

## Development Guidelines

- Preserve frontend, admin, and editor behavior unless the change is explicitly about that area.
- Use WordPress APIs for theme settings, menus, templates, queries, escaping, sanitization, and localization.
- Keep accessibility visible in implementation decisions: landmarks, headings, focus states, contrast, labels, and keyboard behavior all matter.
- Do not commit local WordPress uploads, database dumps, build archives, logs, screenshots from QA, or dependency folders.

## Local Testing

Before opening a pull request, test the affected area in a local WordPress installation. For user-facing changes, check desktop and mobile layouts, dark mode, keyboard navigation, and browser console errors.

## Coding Standards

Use the standards already present in the theme. PHP should be escaped, sanitized, and internationalized where appropriate. CSS should use the theme's design tokens and avoid hardcoded one-off colors unless required by WordPress core compatibility.

## Pull Request Checklist

- The change is limited to the stated purpose.
- No unrelated refactors are included.
- No local or generated artifacts are committed.
- Documentation is updated when behavior changes.
- Testing notes are included in the pull request description.
