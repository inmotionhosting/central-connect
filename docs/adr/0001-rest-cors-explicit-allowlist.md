# 0001. Restrict REST CORS to an explicit Central portal allowlist

- **Date:** 2026-08-17
- **Status:** accepted

## Context

Central Connect registers Remote Site Management routes under `bgc/v1` on the connected site. The Central dashboard calls those routes from the browser using an `X-BGC-Auth` access token. To make that cross-origin call work, `Rest\Server` removed WordPress core `rest_send_cors_headers` and replaced it with `Access-Control-Allow-Origin: *` plus `Authorization` / `X-WP-Nonce` / `X-BGC-Auth` on both REST responses and HEAD discovery.

Red-team finding ENG3-217 (bead Red_Team_3-8794b4) confirmed that live responses also reflect arbitrary `Origin` values (including `null`) and send `Access-Control-Allow-Credentials: true`. That combination lets an attacker page read authenticated WordPress REST responses when a site administrator visits the attacker page. WordPress core's `rest_send_cors_headers` itself reflects any origin and enables credentials, so restoring core CORS is not a fix.

The plugin is installed on customer WordPress sites. Central portal hosts differ by Central Provider and environment. The Central frontend already falls back to a same-origin API proxy when a connected site returns a CORS error.

## Decision

We will emit CORS headers only when the request `Origin` exactly matches a validated HTTPS allowlist derived from plugin Central URLs, Central Provider branding URLs, the production v2 Central portal host, and an optional WordPress filter. Unknown, missing, `http`, IP-literal, and `null` origins get no CORS headers. We will scrub already-sent CORS headers at `PHP_INT_MAX` and apply that policy only for the REST index and `bgc/v1` (exact `/bgc/v1` or prefix `/bgc/v1/`). The HEAD-before-`rest_api_init` discovery hook emits allowlist CORS for an allowlisted Origin only and does not scrub unrelated pages. Other REST namespaces keep whatever CORS core or other plugins already set. We will not emit `Access-Control-Allow-Credentials`. We will not globally remove WordPress core `rest_send_cors_headers`.

## Consequences

### Positive

- Attacker, localhost, private-IP, and `null` origins can no longer read `bgc/v1` responses in a browser.
- Cookie-based cross-origin exfiltration is blocked even if an extra origin is later allowlisted.
- Production Central portals and configured Central URLs keep working without a frontend change.
- Local and unpublished portals keep working through the existing Central proxy.

### Negative

- A new Central portal host that is not in config, branding, or the built-in list will use the proxy until it is added.
- Other plugins on the same WordPress install can still set their own CORS headers after `PHP_INT_MAX` on in-scope routes.
- Central Connect does not secure unrelated REST namespaces. WordPress core may still reflect any Origin with credentials on those routes. That residual is accepted so this ticket does not silently disable other plugins' CORS; it is outside Remote Site Management.

## Alternatives considered

### Keep `Access-Control-Allow-Origin: *` and only drop credentials

- **Description:** Leave the wildcard; do not send `Access-Control-Allow-Credentials`.
- **Why rejected:** The security baseline forbids a `*` fallback on internet-facing APIs. Browsers would still allow non-credentialed cross-origin reads of any response the server returns, and live origin reflection would remain if core CORS is not fully replaced.

### Restore WordPress core `rest_send_cors_headers` for `bgc/v1`

- **Description:** Stop overriding CORS on the Remote Site Management routes and use core.
- **Why rejected:** Core reflects any `Origin` and sets `Access-Control-Allow-Credentials: true`, which is the live behavior the finding exploited. Core may remain on unrelated namespaces.

### Allow `*.central.imhdev.com` and localhost for developer portals

- **Description:** Pattern-match per-engineer and local Central hosts.
- **Why rejected:** That re-opens the finding on every connected site. The Central frontend already proxies when CORS fails.

### Remove browser CORS and force the Central proxy

- **Description:** Emit no CORS headers at all so every Central dashboard call uses the existing same-origin API proxy.
- **Why rejected:** Production Central already talks to connected sites directly. Forcing the proxy would add Central API load and would break any caller that is not using that proxy. An HTTPS allowlist keeps the working path without reopening arbitrary-origin reads.
