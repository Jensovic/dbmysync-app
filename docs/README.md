# DbMySync documentation

Developer documentation for DbMySync: how it is built, how the pieces talk to
each other, and how to run, change and release it. For a feature overview and
install steps see the [main README](../README.md).

## Start here

| If you want to... | Read |
|---|---|
| understand the system in five minutes | [architecture/overview.md](architecture/overview.md) |
| know why there are two git repositories | [architecture/repositories.md](architecture/repositories.md) |
| call or extend the endpoint (addin) | [endpoint/api.md](endpoint/api.md) |
| add the endpoint to one of your projects | [endpoint/integration.md](endpoint/integration.md) |
| find the code behind a page or AJAX call | [main-application/routing.md](main-application/routing.md) |
| look up a SQLite table or column | [main-application/data-model.md](main-application/data-model.md) |
| understand why something counts as a difference | [main-application/schema-comparison.md](main-application/schema-comparison.md) |
| understand or fix generated SQL | [main-application/sql-generation.md](main-application/sql-generation.md) |
| understand the dashboard badges and lights | [main-application/dashboard.md](main-application/dashboard.md) |
| set up the local development machine | [operations/local-setup.md](operations/local-setup.md) |
| publish a new endpoint version on Packagist | [operations/releasing-endpoint.md](operations/releasing-endpoint.md) |
| update dependencies or answer a security alert | [operations/dependencies.md](operations/dependencies.md) |
| know what does not work (yet) | [known-limitations.md](known-limitations.md) |

## Structure

```
docs/
├── README.md                      this index + glossary
├── known-limitations.md           gaps, surprises, deliberate trade-offs
├── architecture/
│   ├── overview.md                components, data flow, request lifecycle
│   └── repositories.md            dbmysync-app vs. dbmysync-addin
├── endpoint/
│   ├── api.md                     HTTP contract and schema JSON format
│   └── integration.md             embedding the addin in a project
├── main-application/
│   ├── routing.md                 pages, POST actions, AJAX actions
│   ├── data-model.md              SQLite schema and migrations
│   ├── schema-comparison.md       SchemaComparator rules
│   ├── sql-generation.md          SqlGenerator rules
│   └── dashboard.md               cached counts, refresh queue, groups
└── operations/
    ├── local-setup.md             XAMPP, PHP, Composer, tests
    ├── releasing-endpoint.md      tagging and Packagist
    └── dependencies.md            composer audit, lock files, Dependabot
```

## Glossary

| Term | Meaning |
|---|---|
| **Main application** | The central tool (`main-application/`). Runs locally, stores tool pairs in SQLite, fetches and compares schemas, generates SQL. |
| **Endpoint / addin** | The small PHP package (`endpoint/`, Composer name `jensovic/dbmysync-addin`) installed into each project. Exposes that project's MySQL structure over HTTP. |
| **Tool pair** | One project with two environments, e.g. "ANALYTICS" with Dev on localhost and Prod at the web host. One row in `tool_pairs`. |
| **env1 / env2** | The two environments of a pair. Names are free (default "Dev" / "Prod"). In code, older names survive: env1 = *offline*, env2 = *online*. |
| **Secret** | Shared token per environment, sent as `X-DbSync-Secret`. Must match the endpoint's `config.php`. |
| **Difference count** | Number of structural differences, cached on the pair and shown as the dashboard badge. |
| **Direction** | `env1_to_env2` = make env2 look like env1; `env2_to_env1` = the reverse. |
