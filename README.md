# ModxComments v0.1 scaffold (MODX Revolution 2.x)

A cache-safe, AJAX-first comment component skeleton for MODX Revolution 2.x.

## Goals of v0.1

- Resource comments loaded independently from MODX page cache.
- Public JSON API with its own CSRF token; no `HTTP_MODAUTH` is exposed in cached HTML.
- Threaded replies via `parent_id`, `thread_id`, `depth`, and materialized `path`.
- Authenticated MODX users and guests.
- Plain text comments, Unicode emoji, safe automatic `http/https` links.
- Basic per-IP-hash rate limiting.
- Soft-delete-ready data model and moderation statuses.
- Thin MODX processors around a reusable service class.

Not yet included: manager CMP, captcha providers, edit/delete UI, subscriptions, Markdown, reactions.

## Install for development

1. Copy this repository tree into the root of a MODX 2.8.x development installation so `core/`, `assets/`, and `_build/` merge with the site tree.
2. Run from the MODX root:

   ```bash
   php _build/build.schema.php
   ```

   This parses the xPDO XML schema, generates model classes/maps, and creates the comment table.
3. Create a MODX Snippet named `ModxComments` with the content of:

   `core/components/modxcomments/elements/snippets/snippet.modxcomments.php`

4. Put this on a resource/template:

   ```modx
   [[ModxComments]]
   ```

   Keep the call cached. The rendered resource can be cached normally; live comments are fetched after page load via AJAX.

## Runtime settings

The service reads these MODX settings when present (otherwise the defaults below are used):

```text
modxcomments.allow_guests = 1
modxcomments.max_depth = 5
modxcomments.max_length = 5000
modxcomments.rate_limit_count = 5
modxcomments.rate_limit_window = 60
modxcomments.guest_status = published
modxcomments.user_status = published
modxcomments.core_path = {core_path}components/modxcomments/
modxcomments.assets_url = {assets_url}components/modxcomments/
```

## Public API

Endpoint:

`/assets/components/modxcomments/connector.php`

Actions:

- `GET ?action=web/init&context=web`
- `GET ?action=web/comment/getlist&resource=123&context=web`
- `POST ?action=web/comment/create` with CSRF token returned by `web/init`

For POST requests send the token in `X-ModxComments-CSRF` or as `_csrf`.

See `docs/API.md` and `docs/V0.1.md`.

## Cache model

The snippet outputs only a stable container with resource/context/API metadata. It does **not** put user/session/comment state into the page cache. The browser then calls the API. `web/init` is explicitly `no-store`, and the API uses the current MODX web session.

## Security choices in v0.1

- No user HTML is stored/rendered as HTML.
- Only `http://` and `https://` text URLs are linkified.
- Links use `rel="nofollow ugc noopener"`.
- CSRF token lives in the web session and is fetched dynamically.
- Raw IP addresses are not stored; a server-side salted SHA-256 hash is used.
- Forwarded-IP headers are deliberately ignored by default.
- Public actions are allow-listed by the connector.

## Suggested next milestone

v0.2 should add: soft delete/edit processors, Turnstile provider interface, root-thread pagination, manager moderation grid, events/hooks, and automated tests.
