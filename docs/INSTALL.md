# ModxComments — MODX 3 installation / upgrade

## Requirements

- MODX Revolution 3.0+
- PHP 7.4+
- MySQL/MariaDB with InnoDB

## Install

1. Build or download the MODX 3 transport package `modxcomments-0.3.0-beta6`.
2. Install it through Package Management.
3. Clear the MODX cache.
4. Add the cached snippet call:

```modx
[[ModxComments]]
```

## Model

The MODX 3 branch uses xPDO 3 namespaced classes under:

```text
ModxComments\Model
```

The package builder generates them into:

```text
core/components/modxcomments/src/
```

## Database compatibility

The same table names are used as in MODX 2:

```text
modxcomments_comments
modxcomments_votes
```

Existing comment data can therefore be retained when migrating the site itself from MODX 2 to MODX 3.

## Moderation

```text
modxcomments.guest_status = pending
modxcomments.user_status = pending
```

Manager moderation is available under **Extras → Comments**.

## Guest editing

New guest comments use an HttpOnly ownership cookie with only its hash stored in the database. The same browser can edit/delete its comment during `modxcomments.edit_time`.

## Frontend pagination

`modxcomments.threads_per_page` controls root threads per page.

## Email notifications

Optional settings:

```text
modxcomments.notify_admin = 1
modxcomments.notify_admin_email =
modxcomments.notify_replies = 1
```

Mail uses the normal MODX mail configuration.

Editable Chunks:

- `ModxCommentsEmailAdminSubject`
- `ModxCommentsEmailAdminBody`
- `ModxCommentsEmailReplySubject`
- `ModxCommentsEmailReplyBody`

## Upgrade / uninstall

Upgrades preserve comments and user-edited notification Chunks. Uninstall removes component registration/settings/menu but intentionally leaves comment tables/data.
