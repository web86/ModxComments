# Public API contract — v0.2

Base URL: `/assets/components/modxcomments/connector.php`

All responses use the standard MODX processor envelope.

## GET web/init

Returns CSRF, current user, public settings, edit window, and CAPTCHA public configuration.

## GET web/comment/getlist

Returns published/deleted tree rows. Every item contains server-computed `canReply`, `canEdit`, and `canDelete`.

## POST web/comment/create

Requires `X-ModxComments-CSRF`. Body fields: `resource`, `context`, `parent`, `content`, guest author fields, and optional `captcha_token`.

## POST web/comment/update

Authenticated author only and only within `modxcomments.edit_time`.

```json
{"id":10,"content":"Updated text"}
```

## POST web/comment/delete

Authenticated author only and only within `modxcomments.edit_time`.

```json
{"id":10}
```

Deletion is soft; descendants remain in the thread.
