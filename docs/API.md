# Public API contract — 1.0.0-rc1

Base URL: `/assets/components/modxcomments/connector.php`

All routes are same-origin and return the standard MODX processor envelope.

## Resource binding

The cached `[[ModxComments]]` snippet renders a `resource_token` HMAC bound to:

```text
resource_id | context_key
```

The browser sends that token on all comment list/count/create/update/delete/vote requests. Requests with a missing or invalid token are rejected.

The private signing key is stored in `modxcomments.resource_signing_key` and is never returned by the API.

The public connector will not initialize the `mgr` context.

## GET web/init

Returns:

- component CSRF token;
- current frontend user identity;
- public settings;
- CAPTCHA configuration;
- frontend lexicon map.

The signing secret is never included.

## GET web/comment/getlist

Parameters:

- `resource`
- `context`
- `resource_token`
- `page` — root-thread page, default 1
- `per_page` — optional override, maximum 100

Only published/deleted resources that the current visitor may view are accepted.

Returns:

- `total` — published comment count including replies;
- `totalThreads`;
- `comments`;
- `pagination.page/pages/perPage/totalThreads`.

Public comment objects do not expose author email addresses.

## GET web/comment/count

Requires `resource`, `context` and `resource_token`.

Returns the published comment count for that resource.

## POST web/comment/create

Requires:

- `X-ModxComments-CSRF`;
- valid `resource_token`;
- resource/context rendered by the snippet.

Guests require `author_name` and `author_email`. The hidden `website` field is a honeypot.

Guest comment ownership uses a long random HttpOnly, SameSite=Lax cookie. Only a derived hash is stored with the comment.

## POST web/comment/update

Requires CSRF, a valid signed resource token and comment ownership. Editing is limited by `modxcomments.edit_time`.

Authenticated owners are matched by MODX user ID. Guest owners are matched by the ownership-cookie hash.

## POST web/comment/delete

Same CSRF, resource-token, ownership and edit-window requirements as update. Deletion is soft and descendants remain.

## POST web/comment/vote

Requires CSRF and a valid signed resource token.

`value=1` is 👍 and `value=-1` is 👎. Repeating the current vote removes it; voting the opposite value switches it.

Guest votes are browser-identity based. Clearing the browser identity cookie can create a new voting identity; this is an abuse-control limitation rather than an authorization mechanism.

## Manager API

The manager connector is separate from the public connector.

It requires:

- an authenticated `mgr` session;
- sudo or Administrator-group membership;
- a valid MODX `HTTP_MODAUTH` token.

Comment status and removal mutations accept POST only.

## Content security

Comment input is treated as plaintext. Arbitrary user HTML is escaped.

Only:

- plain `http://` / `https://` URLs; and
- `[label](https://example.com)`

are converted to links.

Email notification Chunk placeholders neutralize MODX `[[...]]` delimiters from untrusted values before Chunk processing.

## Lifecycle events

- `ModxCommentsBeforeCommentCreate`
- `ModxCommentsOnCommentCreate`
- `ModxCommentsOnCommentUpdate`
- `ModxCommentsOnCommentDelete`
- `ModxCommentsOnCommentPublish`
- `ModxCommentsOnCommentVote`
