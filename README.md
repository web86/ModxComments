# ModxComments 1.0.0-beta1

One transport package for **MODX Revolution 2.8.x and MODX 3.0–3.2.0–3.2**.

ModxComments is a cache-safe, AJAX-first threaded comments Extra with a shared frontend and separate compatibility layers for xPDO 2 and xPDO 3.

## Highlights

- threaded comments with materialized paths;
- guests and authenticated users;
- manager-session recognition for sudo/Administrator users on the frontend;
- frontend Admin badge with the current profile full name;
- guest ownership token for edit/delete;
- moderation statuses;
- votes;
- safe plaintext + HTTP(S) links;
- emoji toolbar;
- reply quotes;
- Cloudflare Turnstile abstraction;
- admin/reply email notifications via editable Chunks;
- root-thread pagination and comment count API;
- EN/RU frontend lexicon;
- relative timestamps;
- autoscroll/highlight after posting;
- lifecycle events;
- manager CMP.

## Supported MODX lines

```text
MODX Revolution 2.8.x
MODX Revolution 3.0–3.2
```

The runtime chooses the correct layer automatically:

```text
MODX 2 -> legacy flat processors + xPDO 2 model
MODX 3 -> FQCN processors + xPDO 3 namespaced model
```

The same database tables are used on both versions:

```text
modxcomments_comments
modxcomments_votes
```

## Universal package build

Build the universal transport on a **MODX 2.8.x installation**:

```bash
git checkout unified
git pull
php _build/build.transport.php
```

The resulting package is:

```text
modxcomments-1.0.0-beta1
```

Install that same transport ZIP on either MODX 2.8 or MODX 3.0–3.2.

The universal build intentionally uses a MODX 2-shaped transport vehicle while shipping both runtime layers. This avoids putting MODX 3.0–3.2-only class names into a package that must also install on MODX 2.

## Usage

Use the cached snippet call:

```modx
[[ModxComments]]
```

Comments themselves are loaded over the public AJAX connector, so the resource may remain cached.

## Admin identity on the frontend

Normal web-context authentication has priority.

If the browser is not logged into the frontend but has a valid `mgr` session, ModxComments also recognizes the manager user when that account is:

- `sudo`; or
- a member of the `Administrator` group.

Admin-authored comments expose the current MODX profile full name and render with a frontend `★ Admin` badge.

## Email Chunks

```text
ModxCommentsEmailAdminSubject
ModxCommentsEmailAdminBody
ModxCommentsEmailReplySubject
ModxCommentsEmailReplyBody
```

Existing edited Chunks are preserved during upgrades.

## Events

```text
ModxCommentsBeforeCommentCreate
ModxCommentsOnCommentCreate
ModxCommentsOnCommentUpdate
ModxCommentsOnCommentDelete
ModxCommentsOnCommentPublish
ModxCommentsOnCommentVote
```

## Development branches

- `main` — MODX 2 reference line.
- `modx3` — MODX 3.0–3.2 reference line.
- `unified` — combined package and the intended forward path after validation.


### MODX 3.3 note

This beta targets MODX 3.0–3.2. MODX currently loads deprecated global class aliases by default on that line, while their automatic loading is planned to stop in 3.3. The runtime itself already uses the MODX 3 namespaced model/processors; the universal transport vehicle should be revalidated before claiming MODX 3.3 support.
