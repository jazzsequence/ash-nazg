# Pantheon API capability gap analysis: v0 vs v1 vs Terminus host

Date: 2026-10-23

## Sources and method

| Surface | Source | Version | Size |
|---|---|---|---|
| v0 | `https://api.pantheon.io/docs/swagger.json` | Swagger 2.0, info 0.12.4 | 79 paths, 95 operations |
| v1 | `https://api.pantheon.io/v1/openapi.json` | OpenAPI 3.0.1, info 0.1.0 | 87 paths, 95 operations, 250 schemas |
| Terminus host (`terminus.pantheon.io`) | No published spec. Derived from Terminus 4.3.3 source (`pantheon-systems/terminus`: `config/constants.yml`, `src/VcsApi/Client.php`, `src/SecretsApi/SecretsApi.php`, `src/Commands/Node*`) plus one live request | n/a | partial, see section 4 |

**Verification dates.** Items marked **live** were exercised against a real site, but only the retested items below carry today's date. Everything else marked **live** is carried over from this project's earlier testing (2026-10-01 and after) and was not re-run for this document. Retested 2026-10-23: backup restore (v0 and v1), `cloud-to-dev` merge, and the `terminus.pantheon.io` variables auth.

Operation lists were diffed by capability, not by path name. v1 renames and regroups many operations. "v1 field" means the data is returned as a property of a v1 object rather than by a dedicated endpoint.

---

## 1. Capabilities in v0 with no v1 equivalent

### 1a. Site and account administration

| v0 operation | v1 |
|---|---|
| `PUT /sites/{id}/label` (set site label) | None. `Site.label` is readable, no write operation exists. |
| `DELETE /sites/{id}` | None. |
| `GET/PUT /sites/{id}/plan`, `GET .../available-plans` | None. |
| `PUT/DELETE /sites/{id}/payment-method` | None. |
| `PUT /sites/{id}/upstream` (change site upstream) | None. `GET /upstreams/{id}` is read-only. |
| `POST /sites/{id}/upstream/cache/clear` | Not equivalent. v1 `POST /sites/{id}/code-cache/clear` is the closest and is documented separately. |
| `GET /sites/{id}/authorizations` | None. |
| `GET/POST/DELETE /sites/{id}/memberships/organizations` | None. v1 membership model is workspace-based (`SiteMembership`, `supportingWorkspaces`), with no add or remove. |
| `POST /sites/{id}/import/site` | None. v1 imports are environment-level only. |
| Site tag operations (`PUT /organizations/{id}/tags`, `DELETE .../tags/{tag}/sites`) | None. |

### 1b. Organization management (all of it)

v1 has `workspaces` in place of organizations. It covers: create workspace, create professional workspace, rename, logo, `GET` workspace, list members, per-user membership reads, `site-removals`, transfer destinations, VCS installations, export.

No v1 equivalent for:
- `GET/POST /organizations/{id}/memberships/users`, `DELETE .../users/{id}`, `PUT .../users/{id}/role` (add, remove, change role of org members)
- `GET/DELETE /organizations/{id}/memberships/sites[/{site}]` (list or remove sites from an org)
- `GET /organizations/{id}/upstreams` (v1 has `Workspace.upstreams` as a field)
- `POST /organizations/{id}/workflows`, `GET .../workflows/{id}` (v1 has `GET /workspaces/{id}/workflows/{id}` read only)

### 1c. Environment operations

| v0 operation | v1 |
|---|---|
| `GET .../environments/{env}/metrics` | None. `TimeseriesMetricsPoint` and `MetricsDuration` schemas exist; no path references them. **live: v0 works** |
| `GET .../environments/{env}/domains` (list) | None. `Domain` schema exists (`isPrimary`, `isVerified`, `type`); no list operation returns it. **live: v0 works** |
| `GET .../domains/dns` (DNS recommendations) | None. `Environment.dnsZone` field is the nearest data. |
| `DELETE .../domains/primary` (unset primary) | None. v1 only has `PUT .../primary-domain`. |
| `GET /sites/{id}/code-tips` (list git branches) | None. `Branch` schema exists; v1 only has `DELETE /sites/{id}/git-branches/{name}`. **live: v0 works** |
| `POST .../environments/{env}/workflows` (generic workflow trigger) | None. v1 exposes typed mutations only. Used here for Install Object Cache Pro. |
| `POST /sites/{id}/workflows`, `POST /users/{id}/workflows`, `POST /organizations/{id}/workflows` (generic) | None. |
| `GET /sites/{id}/workflows/{wf}/logs` (Quicksilver logs) | None. v1 has workflow status reads only. |
| `POST .../environments/{env}/random-seed/rotate` | None. |
| `GET .../environments/{env}/settings` | Partial. `PUT .../status-checks` is write-only; `Environment.statuses` and `developmentMode` are fields. |
| `PUT/DELETE .../backups/schedule` per-day (`/schedule/{day}`) and `GET .../backups/schedule` | Partial. v1 has `PUT` and `DELETE .../backup-schedule` (no day parameter). No read. |

### 1d. Users

| v0 operation | v1 |
|---|---|
| `GET /users/{id}/machine-tokens` | None, and no PAT or machine-token management at all. |
| `GET /users/{id}/keys` (list SSH keys) | Partial. `User.sshKeys` field; `POST/DELETE /users/ssh-keys` exist. |
| `GET /users/{id}`, `GET .../memberships/{organizations,sites}`, `GET .../upstreams` | Replaced by `GET /current-user` (only the current user), `SiteMembership` reads, `GET /users/{id}/upstreams`. Arbitrary user lookup by id is not available. |
| `POST /users/{id}/workflows` (generic) | None. |

---

## 2. Operations present in both, with a known behavioral difference

| Capability | Difference |
|---|---|
| Create backup | v0 `POST .../backups` takes an `element` (code, database, files). v1 `POST /sites/{id}/backups` creates a full backup only. |
| Restore backup | Both exist. **Retested 2026-10-23 on a fresh multidev: still fails on both.** v1 (`type=RESTORE_DATABASE_BACKUP`, `key`=Backup id) and v0 (catalog id, `element=database`) were each accepted with HTTP 200, then the workflow ended `FAILED`. First reproduced 2026-10-01. |
| Merge multidev into dev | Both exist. **Retested 2026-10-23 on a fresh multidev: v1 `cloud-to-dev` still fails, but the error changed.** It now returns HTTP 403 with `MAS response status: 400 Bad Request` / `UNAUTHORIZED`, where it returned a 500 on 2026-10-01. Only the empty-body shape was retried. The reverse (`dev-to-cloud`) works (last verified before 2026-10-23). |
| Upstream updates | v0 `GET /sites/{id}/upstream-updates` returns a commit list. v1 has no commit-level diff, only `SiteMembership.upstreamUpdateStatus` plus `POST .../upstream-updates/check` and `/apply`. |
| Environment variables | v0 `GET .../variables`. v1 `Environment.envVariables` field. |
| Commits and diffstat | v0 `GET .../commits`, `.../diffstat`. v1 `Environment.commitLog`, `Environment.diffstat` fields. |
| Composer updates | v0 `GET .../build/updates`. v1 `Environment.composerUpdates` field. |
| Backup download URL | v0 path-based id and element. v1 body-based. **live: v1 needs the Backup's top-level `date`, not the per-element timestamp.** |
| Auth | v0 exchanges a machine token or PAT for a session (`POST /v0/authorize/machine-token`). v1 takes the PAT directly as Bearer. **live: an old-style machine token is not accepted by v1; a PAT is accepted by v0 exchange.** |
| Multidev create | **live: v1 requires an `annotation` query param** (undocumented as required). |
| Merge dev into multidev | **live: v1 requires `params` as a JSON-encoded string in the query string.** |
| Connection mode | **live: sending an empty JSON `{}` body to v1 `development-mode/*` returns 500;** no body works. |
| Addons | v0 has separate PUT/DELETE for `redis` and `solr` only. v1 has one `PUT .../addons/{addonType}` with an enable flag, and supports Elasticsearch. |
| Pagination | v1 uses Relay-style `xConnection_first=N` (e.g. `currentUser_workspacesConnection_first`). `pager[first]` returns 400. |

---

## 3. Capabilities in v1 with no v0 equivalent

| v1 operation | Notes |
|---|---|
| `GET/POST /secrets`, `PUT /secrets/{name}`, `PUT/DELETE /secrets/{name}/overrides/{env}`, `POST /sites/{id}/secrets/bulk`, `GET .../secrets/with-environments`, `DELETE /sites/{id}/secrets/{name}` | Secrets management. Not used by this plugin. |
| `GET /sites/{id}/builds/...`, `.../environments/{env}/builds`, `.../logs`, `logs-v2`, `runtime-logs`, `tenant-runtime-log` | Build and runtime logs (Node/decoupled builds). Not used by this plugin. |
| `POST .../rollback`, `POST .../rebuild`, `POST .../code/sync`, `GET .../deployment-summary`, `POST .../deployments` | Rollback and deployment summary have no v0 form. |
| `POST .../exports`, `.../export-download-urls`, `.../import-upload-urls`, `.../imports/by-file`, `.../imports/by-url` | Export, plus import by upload or URL. v0 has import only. |
| `POST /sites/{id}/unfreeze`, `POST /sites/{id}/leave` | Site lifecycle. |
| `GET /sites/by-name/{name}/status`, `GET .../multidev-names/{name}/validation` | Name availability and validation. |
| `PUT .../domains/{domain}`, `POST .../domains/{domain}/ownership-verification` | Domain update and ownership verification. |
| `POST .../upstream-updates/check` | Explicit check operation. |
| `POST /sites/{id}/deploy` (deploy an upstream to a new site), `POST /sites/{id}/environments` (create test and live) | Site provisioning steps. |
| `POST /workspaces/professional`, `PUT /workspaces/{id}/{name,logo}`, `POST .../exports`, `GET .../evcs-installations`, `GET /workspaces/transfer-destinations` | Workspace management. |
| `POST /sites/{id}/users/{id}/promote-to-owner`, `POST /workspaces/{id}/users/{id}/site-removals` | Ownership transfer and bulk site removal. |
| `PUT .../environments/{env}/status-checks` | Environment status checks. |

---

## 4. Terminus host (`terminus.pantheon.io`)

No spec is published. These are the endpoints Terminus 4.3.3 itself calls on this host, from source.

| Endpoint | Purpose | Equivalent on `api.pantheon.io` |
|---|---|---|
| `GET /api/sites/{id}/environments/{env}/variables` | Full env var set, including `PANTHEON_INDEX_*` for Solr. **live: raw PAT Bearer → 401 (`Invalid or expired session header: X-Pantheon-Session`); v0 session token → 200.** | v0 `GET .../variables`, v1 `Environment.envVariables` |
| `GET /api/sites/{id}/environment/{env}/build/list` | List builds | v1 `GET .../environments/{env}/builds` |
| `GET /api/sites/{id}/environment/{env}/run/log` | Runtime log | v1 `GET .../runtime-logs`, `.../tenant-runtime-log` |
| `GET /api/sites/{id}/build/{build}/log` | Build log | v1 `GET /sites/{id}/builds/{id}/logs` |
| `POST /api/sites/{id}/environment/{env}/build/{build}/rollback` | Rollback | v1 `POST .../rollback` |
| `/customer-secrets/v1/{workspaceType}/{workspaceId}/secrets[/{name}]` (POST, PATCH, DELETE, GET) | Secrets | v1 `/secrets`, `/sites/{id}/secrets/...` |
| VCS API: `installwithtoken`, `site-details/{id}`, `site-details/{id}/environments/{env}/rebuild`, `site-details/{id}/build-path` (PATCH), pause/resume build, `installation/user/{u}/org/{o}`, `authorize/organization/{id}`, repository search and name validation, `provision/manifest`, provision | Git provider linking, build path and build pause/resume, site provisioning from a repo | Partial: v1 `GET /workspaces/{id}/evcs-installations`, `POST .../rebuild`. No v1 equivalent for build pause/resume, build-path, repository search/validation, or provision-manifest. |

Auth schemes differ by endpoint: some Terminus-host calls use `X-Pantheon-Session` (a v0 session token), others `Authorization: Bearer`. Which scheme each endpoint accepts is only confirmed for `/variables` (session token only).

---

## 5. Summary counts

- v0 capabilities with no v1 equivalent at all: site label write, site delete, plan and payment, change upstream, authorizations, site-org membership, import site, org member and site management, tags, random-seed rotate, generic workflow triggers (site, env, user, org), Quicksilver workflow logs, metrics, domain list and DNS recommendations, unset primary domain, code-tips/branch list, backup schedule read and per-day delete, machine-token list, arbitrary user lookup.
- Capabilities present in both but not working or restricted today: backup restore (platform bug, both versions), merge multidev to dev (v1 403 `MAS ... 400 Bad Request`, was a 500 on 2026-10-01), single-element backup creation (v1 full only), upstream commit diff (status label only).
- v1-only capabilities: secrets, build and runtime logs, rollback, deployment summary, export, import by upload and URL, unfreeze, leave site, name validation, domain update and ownership verification, workspace management, ownership promotion, status checks.
- Terminus-host-only (no public-API equivalent): build pause/resume, build-path, repository search and validation, provision manifest.
