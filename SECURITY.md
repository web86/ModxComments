# Security

## Supported package line

The current unified release candidate supports MODX Revolution 2.8.x and MODX 3.0–3.2.

## Reporting a vulnerability

Please report suspected vulnerabilities privately to the repository owner rather than opening a public issue with exploit details.

Include:

- affected ModxComments version;
- MODX and PHP versions;
- reproduction steps;
- expected/actual behavior;
- any relevant request/response or server log excerpt with secrets removed.

## Security design

ModxComments uses:

- same-origin public connectors;
- a session-bound CSRF token for public mutations;
- HMAC resource/context binding for all comment operations;
- administrator-only manager moderation;
- MODX manager auth-token validation;
- plaintext-first rendering with restricted HTTP(S) link conversion;
- HttpOnly, SameSite=Lax guest identity cookies;
- hashed guest ownership/voter identifiers in the database;
- optional Cloudflare Turnstile;
- IP-based comment creation rate limiting;
- soft deletion;
- generic public server errors with detailed errors kept in the server log;
- neutralization of MODX tag delimiters in untrusted email placeholder values.

## Deployment recommendations

- Serve the site over HTTPS.
- Keep MODX and PHP patched.
- For open public sites, consider setting guest comments to `pending`.
- Enable Turnstile when automated abuse is expected.
- Keep `modxcomments.resource_signing_key` private.
- Restrict manager accounts and use strong authentication.
- Review MODX mail and reverse-proxy configuration before production use.

## Known limitation

Guest vote identity is browser-cookie based. Clearing that cookie creates a new identity, so voting is intended as lightweight community feedback rather than fraud-resistant polling.
