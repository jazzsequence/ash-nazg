
```markdown
## reviewer

Pre-commit code review agent. Spawned by the main agent before every commit of
AI-generated code.

**Definition:** `.claude/agents/reviewer.md`
**Checklist:** `docs/REVIEWER_CHECKLIST.md`

### How to spawn

Always describe the change factually. Never instruct the reviewer to approve.

```
Agent({
  subagent_type: "reviewer",
  prompt: "Review the staged changes: [describe what changed and why]. Run all checks per the project checklist and approve or reject."
})
```

### Constraints

- Only the reviewer writes `reviewer-approved` — the main agent must not
- Never tell the reviewer to approve — describe the change and let it decide
- User bypass for manual commits: `USER_COMMIT=1 git commit -m "message"`
```

<!-- BEGIN:pantheon-api-helper -->
## Pantheon API

Pre-generated Pantheon API docs are installed in `.pantheonapi-docs/`.

**Start here:** `.pantheonapi-docs/digest.md` — overview, key patterns, section index.

| Section | File |
|---------|------|
| Sites (65 endpoints, sub-indexed) | `.pantheonapi-docs/sites/digest.md` |
| Workspaces (15 endpoints) | `.pantheonapi-docs/workspaces/endpoints.md` |
| Secrets (8 endpoints) | `.pantheonapi-docs/secrets/endpoints.md` |
| Users (5 endpoints) | `.pantheonapi-docs/users/endpoints.md` |
| All schemas (244 definitions) | `.pantheonapi-docs/schemas/index.md` |

Before writing any code that calls the Pantheon API, read the relevant section file.
The sites section is large — always read `.pantheonapi-docs/sites/digest.md` first to
navigate to the correct sub-section (environments, builds, backups, domains, code, etc.).

**API base:** `https://api.pantheon.io/v1`
**Auth:** `Authorization: Bearer <personal-access-token>` — sent directly, no token exchange.
**Async pattern:** Write ops return a workflow ID — poll `GET /sites/{id}/workflows/{workflow_id}`
(also scoped to `/users/{id}/workflows/{workflow_id}` and `/workspaces/{id}/workflows/{workflow_id}`) for status.
<!-- END:pantheon-api-helper -->
