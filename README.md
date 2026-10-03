# ModxComments 0.2.0-beta13

A cache-safe, AJAX-first comments component for MODX Revolution 2.x.

## v0.2

This branch adds the first installable Extra milestone:

- cached MODX resources remain independent from live comment state;
- threaded comments and guest/authenticated posting;
- server-authorized edit + soft delete for authenticated authors;
- configurable edit window (default 15 minutes);
- optional Cloudflare Turnstile, including guests-only mode;
- manager moderation grid: publish, pending, spam, soft delete;
- MODX events for create/update/delete;
- transport package build script;
- system settings and manager menu created during package install.

## Development install

Copy the repository into a MODX 2.8.x site and run:

```bash
php _build/build.schema.php
```

Create the `ModxComments` snippet from:

`core/components/modxcomments/elements/snippets/snippet.modxcomments.php`

Then use the **cached** call:

```modx
[[ModxComments]]
```

## Build a transport package

From the MODX root:

```bash
php _build/build.transport.php
```

The generated package is written to MODX's `core/packages/` directory.

The installer creates the namespace, snippet, system settings, manager menu, custom events, and the comment table if missing. The manager page uses MODX 2.3+ namespace routing and does not create deprecated `modAction` records.

Uninstall intentionally does **not** drop comment data.

## Updating from 0.2.0-beta

Build/install `0.2.0-beta13` over the previous beta. The installer rewrites the existing `Comments` menu entry to namespace routing:

```text
namespace = modxcomments
action    = index
```

beta6 converts the ModxComments tables to `utf8mb4` so 4-byte emoji are preserved. Existing comment rows remain in place; characters that were already stored as `?` cannot be reconstructed automatically.

## Settings

```text
modxcomments.allow_guests = 1
modxcomments.max_depth = 5
modxcomments.max_length = 5000
modxcomments.edit_time = 900
modxcomments.rate_limit_count = 5
modxcomments.rate_limit_window = 60
modxcomments.guest_status = published
modxcomments.user_status = published
modxcomments.turnstile_enabled = 0
modxcomments.turnstile_site_key =
modxcomments.turnstile_secret_key =
modxcomments.turnstile_guests_only = 1
modxcomments.notify_admin = 0
modxcomments.notify_admin_email =
modxcomments.notify_replies = 0
modxcomments.threads_per_page = 20
```

## Public API

```text
GET  web/init
GET  web/comment/getlist
GET  web/comment/count
POST web/comment/create
POST web/comment/update
POST web/comment/delete
```

All POST actions require `X-ModxComments-CSRF`. Update/delete permissions are verified server-side.

## Manager

After a transport-package install, open **Extras → Comments**. v0.2 supports search, status filtering, publish, pending, spam, and soft delete.

## Events

```text
ModxCommentsBeforeCommentCreate
ModxCommentsOnCommentCreate
ModxCommentsOnCommentUpdate
ModxCommentsOnCommentDelete
ModxCommentsOnCommentPublish
ModxCommentsOnCommentVote
```

Frontend and manager UI received the first full visual/interaction pass in beta4.

## beta5 interaction additions

- guest email is required and validated server-side;
- hidden honeypot field rejects simple form bots;
- 👍 / 👎 voting with one reversible vote per authenticated user or anonymous browser token;
- compact editor toolbar with safe URL insertion;
- five common emoji plus an expandable emoji panel;
- replies display a short quote from the parent comment.

`modxcomments.edit_time` is measured in seconds. The default `900` means an authenticated author can edit/delete their own comment for 15 minutes after posting. Guest comments are intentionally not editable because v0.2 does not issue a guest ownership credential.


## Moderation defaults

To send guest comments to moderation by default:

```text
modxcomments.guest_status = pending
```

To moderate comments from authenticated MODX web users too:

```text
modxcomments.user_status = pending
```

Use `published` for immediate publication.

## Comment permalinks

Every rendered comment has an anchor such as:

```text
#comment-123
```

The visible `#123` link points to that anchor and can be copied/shared.

## Safe labeled links

The editor's link tool inserts:

```text
[Link text](https://example.com)
```

Only HTTP(S) links in this limited syntax are rendered as anchors. Arbitrary HTML remains escaped.


## beta7 moderation preview and notifications

When a newly submitted comment receives `pending`, the frontend temporarily keeps the returned comment in the current tab only. It is rendered semi-transparent with an “Awaiting moderation” notice. Reloading the page removes that local preview until a moderator publishes the comment.

Email notifications are optional and disabled by default:

```text
modxcomments.notify_admin = 0
modxcomments.notify_admin_email =
modxcomments.notify_replies = 0
```

- `notify_admin=1`: send an email for every new comment, including pending comments.
- `notify_admin_email`: recipient address; if blank, MODX `emailsender` is used.
- `notify_replies=1`: notify the author of the parent comment when a reply is actually published. A pending reply triggers the notification only after moderation publishes it.

Mail delivery uses the normal MODX mail/SMTP settings.


## beta8 manager thread view

The manager comments grid now renders comment relationships visually:

- newest threads are grouped together;
- replies are indented according to `depth`;
- reply rows show the parent author and a short parent excerpt;
- root rows are visually separated as the start of a thread;
- comments written by a MODX user with `sudo=1` or membership in the `Administrator` group receive an Admin badge and highlighted row.

Transport package metadata is now read through a safe helper with embedded fallbacks, so Instructions/Readme, License and Changelog are always strings rather than boolean `false`.


## beta13

- Frontend EN/RU lexicon via dynamic `web/init`.
- Guest edit/delete ownership with HttpOnly cookie + stored hash.
- Root-thread pagination and live comment count.
- `GET web/comment/count`.
- Editable email notification Chunks.
- Extended events: BeforeCreate, Publish and Vote.
