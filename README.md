# ModxComments 0.2.0-beta2

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

Build/install `0.2.0-beta2` over the previous beta. The installer rewrites the existing `Comments` menu entry to namespace routing:

```text
namespace = modxcomments
action    = index
```

No database migration is required for comments.

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
```

## Public API

```text
GET  web/init
GET  web/comment/getlist
POST web/comment/create
POST web/comment/update
POST web/comment/delete
```

All POST actions require `X-ModxComments-CSRF`. Update/delete permissions are verified server-side.

## Manager

After a transport-package install, open **Extras → Comments**. v0.2 supports search, status filtering, publish, pending, spam, and soft delete.

## Events

```text
ModxCommentsOnCommentCreate
ModxCommentsOnCommentUpdate
ModxCommentsOnCommentDelete
```

Frontend styling is still intentionally minimal; visual polish is a separate milestone.
