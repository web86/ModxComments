# Changelog

## 0.2.0-beta5
- Require guest email on both frontend and server.
- Add a hidden honeypot anti-spam field.
- Add reversible 👍 / 👎 voting with per-user/browser identity.
- Add editor toolbar with URL insertion and emoji picker.
- Add reply quote previews in the composer and rendered reply cards.
- Add a vote table created automatically during package upgrade.
- Clarify that edit_time applies only to authenticated comment owners.

## 0.2.0-beta4
- Replace browser prompt/confirm flows with inline edit and delete states.
- Add loading, busy, empty, success/error, reply and character-count states on the frontend.
- Add a neutral responsive frontend skin based on CSS variables.
- Improve manager grid readability with status badges, email links, clear search and status feedback.
- Improve EN/RU manager copy and action discoverability.

## 0.2.0-beta3
- Load manager lexicon through `getLanguageTopics()`, so ExtJS `_()` labels are populated.
- Add Email column to the manager comments grid.
- Add explicit English and Russian labels for toolbar filters and row actions.
- Explicitly strip PHP opening/closing tags from snippet source during transport build.
- Keep the beta2 routing/category fixes.

## 0.2.0-beta2
- Fix transport category key: `modCategory.category` instead of invalid `category_name`.
- Replace deprecated manager `modAction` routing with namespace + action-name routing.
- Repair the existing Comments menu entry when installing over the first beta.
- Initialize manager JS namespaces before defining grid/panel classes.
- Generate xPDO model classes automatically while building the transport package.

## 0.2.0-beta
- Authenticated author edit within a configurable time window.
- Soft delete without breaking reply trees.
- Optional Cloudflare Turnstile integration.
- Basic manager moderation CMP.
- Transport-package build and installer resolver.
- MODX system settings and custom create/update/delete events.
- Preserves the AJAX/cache-independent architecture from v0.1.

## 0.1.0
- Initial AJAX-first MODX 2.x scaffold.
- Threaded comments, guest/user posting, CSRF, plain-text rendering and rate limiting.
