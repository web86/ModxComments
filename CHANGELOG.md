# Changelog

## 0.2.0-pl
- First stable ModxComments release for MODX Revolution 2.8.x.
- Promoted directly from the live-tested 0.2.0-beta14 codebase with no runtime feature changes.
- Includes threaded comments, guest/user posting, edit/delete ownership, moderation, voting, emoji/link toolbar, reply quotes, notifications, pagination, count API, EN/RU frontend localization, utf8mb4 emoji support, lifecycle events, manager thread view, autoscroll and relative timestamps.

## 0.2.0-beta14
- Smooth-scroll to a newly submitted comment after the list refreshes.
- Briefly highlight the submitted comment after autoscroll.
- Render comment timestamps as localized relative time using Intl.RelativeTimeFormat.
- Keep the exact server timestamp in the time element tooltip/datetime attribute.

## 0.2.0-beta13
- Localize the entire frontend through the `modxcomments:frontend` lexicon (EN/RU).
- Add guest ownership via a long-lived HttpOnly browser token; only its hash is stored with new guest comments.
- Allow new guest comments to be edited/deleted within `modxcomments.edit_time`.
- Add root-thread pagination with configurable `modxcomments.threads_per_page`.
- Add live published-comment count to the frontend heading and `GET web/comment/count`.
- Add editable MODX Chunks for administrator and reply email subjects/bodies.
- Preserve user-edited email Chunks on package upgrades.
- Add `ModxCommentsBeforeCommentCreate`, `ModxCommentsOnCommentPublish` and `ModxCommentsOnCommentVote` events.
- Emit publish events only when a comment actually transitions to published.
- Preserve current-tab pending previews across frontend rerenders.

## 0.2.0-beta12
- Fix the transport file-resolver targets for clean installs.
- Core source now resolves into `MODX_CORE_PATH/components/`, avoiding an accidental nested `modxcomments/modxcomments/` directory.
- Assets source now resolves into `MODX_ASSETS_PATH/components/` for the same reason.
- Add installer diagnostics for missing controller/model/frontend paths after extraction.

## 0.2.0-beta11
- Add a manager controller under controllers/default/index.class.php, which MODX 2.8 checks before controllers/index.class.php.
- Add a legacy index.php compatibility fallback for installations that unexpectedly use modManagerControllerDeprecated.
- Keep namespace routing (namespace=modxcomments, action=index) as the primary manager route.

## 0.2.0-beta10
- Fix clean-install warning when the xPDO model directory is not yet available during the PHP resolver.
- Create and migrate ModxComments tables directly in the installer resolver.
- Keep xPDO model loading for normal runtime after package files are installed.

## 0.2.0-beta8
- Fix transport package metadata so Instructions/Readme, License and Changelog can never become boolean false.
- Add embedded fallback text when metadata files are missing or unreadable during build.
- Structure the manager comments grid as visible reply threads.
- Indent replies by nesting depth and show the parent author + excerpt.
- Visually separate root comments from replies.
- Detect administrator-authored comments using MODX sudo or Administrator group membership.
- Highlight administrator replies with a badge and row styling.

## 0.2.0-beta7
- Fill MODX transport-package Instructions/Readme, License and Changelog metadata.
- Add a dedicated installation/upgrade instruction document.
- Show a newly submitted pending comment as a translucent, current-tab-only preview.
- Add optional administrator email notifications for all new comments.
- Add optional reply notifications when a reply becomes published.
- Delay reply notification until moderation publishes a pending reply.
- Track reply notification delivery to avoid duplicate emails.
- Add localized system-setting names/descriptions for notification options.

## 0.2.0-beta6
- Add localized names and clear descriptions for every ModxComments system setting.
- Document and clarify `guest_status=pending` / `user_status=pending` moderation defaults.
- Add visible comment numbers and permalinks such as `#comment-123`.
- Highlight a comment when opened by its permalink.
- Migrate ModxComments tables and public API connection to `utf8mb4` for 4-byte emoji.
- Expand the link toolbar to separate link text and URL fields.
- Add safe labeled-link syntax `[text](https://url)` while keeping arbitrary HTML escaped.

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
