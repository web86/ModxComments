# ModxComments — installation / upgrade

## Requirements

- MODX Revolution 2.8.x
- PHP supported by your MODX installation
- MySQL/MariaDB with InnoDB
- JavaScript enabled in the browser

## Install

1. Install the transport package from **Extras → Installer / Package Management**.
2. Clear the MODX cache after installation.
3. Add the cached snippet call to the required resource/template:

```modx
[[ModxComments]]
```

Do not use the uncached `[[!ModxComments]]` call. Comment data is loaded over AJAX and is intentionally independent from the resource cache.

## Moderation

For guest comments to require approval:

```text
modxcomments.guest_status = pending
```

For authenticated web-user comments to require approval too:

```text
modxcomments.user_status = pending
```

Moderation is available under **Extras → Comments**.

## Email notifications

Notifications are disabled by default.

- `modxcomments.notify_admin = 1` — notify the administrator about each new comment.
- `modxcomments.notify_admin_email` — recipient address; when empty, MODX `emailsender` is used.
- `modxcomments.notify_replies = 1` — notify the author of the parent comment when a reply becomes published.

Mail delivery uses the normal MODX mail/SMTP configuration.

## Upgrade

Install a newer transport package over the previous beta. Comment data is preserved. The installer performs required ModxComments-only schema/charset migrations.

## Uninstall

Uninstall removes the component registration, settings and manager menu, but intentionally preserves comment tables/data.
