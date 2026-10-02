# Public API contract — v0.2 beta5

Base URL: `/assets/components/modxcomments/connector.php`

All responses use the standard MODX processor envelope.

## GET web/init

Returns CSRF, current user, public settings, edit window and CAPTCHA public configuration.

## GET web/comment/getlist

Returns published/deleted tree rows. Every published item can include:

- `canReply`, `canEdit`, `canDelete`;
- `replyTo` with parent author + excerpt;
- `votes.up`, `votes.down`, `votes.score`, `votes.mine`.

## POST web/comment/create

Requires `X-ModxComments-CSRF`.

Guest body fields include required `author_name` and `author_email`.

The hidden `website` field is a honeypot and must remain empty.

## POST web/comment/update

Authenticated owner only, within `modxcomments.edit_time`.

```json
{"id":10,"content":"Updated text"}
```

## POST web/comment/delete

Authenticated owner only, within `modxcomments.edit_time`.

```json
{"id":10}
```

Deletion is soft; descendants remain in the thread.

## POST web/comment/vote

Requires CSRF.

```json
{"id":10,"value":1}
```

`value` is `1` for 👍 and `-1` for 👎. Repeating the same vote removes it; voting the opposite direction switches it.
