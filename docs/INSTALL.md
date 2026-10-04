# ModxComments 1.0.1-pl — installation

## Compatibility

The same transport package supports:

- MODX Revolution 2.8.x
- MODX Revolution 3.0–3.2

## Building the universal transport

Build on MODX 2.8.x:

```bash
git checkout unified
git pull
php _build/build.transport.php
```

Output:

```text
modxcomments-1.0.1-pl
```

Use the **same ZIP** on MODX 2 or MODX 3.0–3.2.

## Install / upgrade

1. Install the transport through Package Management.
2. Clear the MODX cache.
3. Add the cached snippet call:

```modx
[[ModxComments]]
```

Existing `modxcomments_comments` and `modxcomments_votes` data are preserved during upgrades.

### Important for local upgrade testing

Do not replace a transport ZIP with another build that has the same MODX package signature. MODX 2 caches the unpacked transport under `core/packages/<signature>/` and may continue installing the previously unpacked build even when the ZIP contents changed.

Every distributable build must use a new release/signature. The current stable release is `modxcomments-1.0.1-pl`.

## Runtime compatibility

The package ships both models:

```text
MODX 2:
core/components/modxcomments/model/modxcomments/

MODX 3:
core/components/modxcomments/src/Model/
```

And both processor styles:

```text
MODX 2:
core/components/modxcomments/processors/

MODX 3:
core/components/modxcomments/src/Processors/
```

The public and manager connectors choose the correct implementation automatically.

## Manager login on the frontend

When no normal web-context login exists, a valid manager session is accepted for frontend commenting only when the manager user is sudo or belongs to Administrator.

The comment is saved with that MODX user ID and current profile information.

## Frontend Admin badge

Comments belonging to a current sudo/Administrator user are rendered with:

```text
★ Admin  Full Name
```

The displayed name comes from the user's current profile fullname, falling back to username.

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
modxcomments.threads_per_page = 20
modxcomments.captcha_enabled = 0
modxcomments.captcha_provider = turnstile
modxcomments.captcha_guests_only = 1
modxcomments.notify_admin = 0
modxcomments.notify_replies = 0
```

## CAPTCHA providers

The comment form supports four providers without changing the `[[ModxComments]]` call:

- `none` — no CAPTCHA
- `turnstile` — Cloudflare Turnstile
- `hcaptcha` — hCaptcha
- `recaptcha` — Google reCAPTCHA v2 or v3
- `yandex` — Yandex SmartCaptcha

Enable CAPTCHA with `modxcomments.captcha_enabled=1`, choose the provider in `modxcomments.captcha_provider`, and configure that provider's keys.

For Google reCAPTCHA, set `modxcomments.recaptcha_version` to `v2` or `v3`. With v3, `modxcomments.recaptcha_min_score` controls the minimum accepted score and defaults to `0.5`.

`modxcomments.captcha_guests_only=1` requires CAPTCHA only for unauthenticated visitors.

Existing installations that used the old Turnstile enable/guests-only switches are migrated automatically to the generic CAPTCHA settings during upgrade.

## Email templates

Editable Chunks:

- `ModxCommentsEmailAdminSubject`
- `ModxCommentsEmailAdminBody`
- `ModxCommentsEmailReplySubject`
- `ModxCommentsEmailReplyBody`

User changes to these Chunks are preserved during upgrades.

## Uninstall

Component registration, settings and menu are removed. Comment tables/data are intentionally preserved.


## Security notes

`modxcomments.resource_signing_key` is generated automatically and is used only to sign the resource/context rendered by the snippet. Do not publish or routinely rotate it; changing it invalidates cached comment widgets until those pages are regenerated.

The manager comments CMP is intentionally limited to sudo users and members of the Administrator group in this release.

After upgrading from an earlier beta, clear the MODX resource cache so pages contain the new signed resource token.
