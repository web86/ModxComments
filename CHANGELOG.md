# Changelog

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
