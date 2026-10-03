# Public API contract — v0.2 beta13

Base URL: `/assets/components/modxcomments/connector.php`

All responses use the standard MODX processor envelope.

## GET web/init

Returns CSRF, current user, public settings, CAPTCHA configuration and the frontend lexicon map (`i18n`).

## GET web/comment/getlist

Parameters:

- `resource`
- `context`
- `page` (root-thread page, default 1)
- `per_page` (optional override, maximum 100)

Returns complete reply trees for the selected root-thread page:

- `total` — number of published comments (replies included);
- `totalThreads`;
- `comments`;
- `pagination.page/pages/perPage/totalThreads`.

Every comment can include `canReply`, `canEdit`, `canDelete`, `replyTo` and vote data.

## GET web/comment/count

Returns the current number of published comments for a resource:

```json
{"total":34}
```

## POST web/comment/create

Requires `X-ModxComments-CSRF`.

Guests require `author_name` and `author_email`. The hidden `website` field is a honeypot and must remain empty.

New guest comments receive an HttpOnly ownership cookie. Only a one-way ownership hash is stored in the comment row.

## POST web/comment/update

The comment owner only, within `modxcomments.edit_time`. Authenticated owners are matched by MODX user ID; new guest comments are matched by the ownership cookie/hash.

```json
{"id":10,"content":"Updated text"}
```

## POST web/comment/delete

Same ownership/edit-window rules as update. Deletion is soft and descendants remain.

## POST web/comment/vote

Requires CSRF.

```json
{"id":10,"value":1}
```

`1` is 👍 and `-1` is 👎. Repeating the same vote removes it; the opposite vote switches it.

## Lifecycle events

- `ModxCommentsBeforeCommentCreate` — receives mutable `data` by reference; returning boolean `false` cancels creation.
- `ModxCommentsOnCommentCreate`
- `ModxCommentsOnCommentUpdate`
- `ModxCommentsOnCommentDelete`
- `ModxCommentsOnCommentPublish`
- `ModxCommentsOnCommentVote`
