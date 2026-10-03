# ModxComments 0.3.0-beta8 — MODX 3

This branch is the MODX Revolution 3 version of ModxComments.

It keeps the same frontend behavior and feature set as the MODX 2 `main` branch, including:

- threaded AJAX comments;
- guest/authenticated posting;
- guest ownership edit/delete token;
- moderation and manager CMP;
- voting;
- reply quotes;
- honeypot + optional Turnstile;
- EN/RU frontend lexicon;
- root-thread pagination and live comment count;
- email notifications through editable MODX Chunks;
- lifecycle events;
- autoscroll to newly submitted comments;
- localized relative timestamps.

## Requirements

- MODX Revolution 3.0+
- PHP 7.4+
- MySQL/MariaDB with InnoDB
- JavaScript enabled

## MODX 3 architecture

The MODX 3 branch differs internally from the MODX 2 branch:

- bootstraps through `core/vendor/autoload.php`;
- uses `MODX\Revolution\modX`;
- uses namespaced MODX 3 processors/controllers;
- uses an xPDO 3 model package `ModxComments\Model`;
- generated model classes live under `core/components/modxcomments/src/`;
- schema extends `xPDO\Om\xPDOObject` / `xPDOSimpleObject`;
- transport package declares `modx >= 3.0.0`.

Database table names remain compatible with the MODX 2 version:

```text
modxcomments_comments
modxcomments_votes
```

## Build

Run from the MODX 3 site root:

```bash
git checkout modx3
git pull
php _build/build.transport.php
```

The package signature is:

```text
modxcomments-0.3.0-beta8
```

For development model generation only:

```bash
php _build/build.schema.php
```

## Usage

Use the cached snippet call:

```modx
[[ModxComments]]
```

Live comments are loaded separately over AJAX, so the resource itself can remain cached.

## Settings

The MODX 3 version uses the same `modxcomments.*` system settings as the MODX 2 branch, including:

```text
modxcomments.allow_guests = 1
modxcomments.max_depth = 5
modxcomments.max_length = 5000
modxcomments.edit_time = 900
modxcomments.guest_status = published
modxcomments.user_status = published
modxcomments.threads_per_page = 20
modxcomments.notify_admin = 0
modxcomments.notify_replies = 0
```

## Email Chunks

```text
ModxCommentsEmailAdminSubject
ModxCommentsEmailAdminBody
ModxCommentsEmailReplySubject
ModxCommentsEmailReplyBody
```

## Events

```text
ModxCommentsBeforeCommentCreate
ModxCommentsOnCommentCreate
ModxCommentsOnCommentUpdate
ModxCommentsOnCommentDelete
ModxCommentsOnCommentPublish
ModxCommentsOnCommentVote
```

The MODX 2 line continues separately on `main`.
