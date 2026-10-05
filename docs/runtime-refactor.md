# Package runtime and compatibility

The web and CLI bootstrap now use `runtime\RuntimeLoader`, `RuntimeCreator`,
`RuntimeOrchestrator`, and `RuntimeExecutor`. The package repository/service hydrates
installed metadata; the runtime holds execution state; the orchestrator validates
and orders packages; the executor runs their APIs and optional linked components.

## Existing packages

Existing `packages\runtime\api\RuntimeAPI`, controller, router, and linker classes
remain supported. `LegacyRuntimeAdapter` translates their metadata and flags into
the new runtime. Their PHP files do not need to migrate. The old `Loader` and
`RuntimeHandler` entry points also delegate to the new execution engine.

Lifecycle order remains `environmentSetup()` → `setup()` → `main()`, once per
component. Linked files run in registration order. Linkers are optional: a package
can implement its entire startup in a single `RuntimeAPI` subclass. Controllers,
factory configuration, route actions, templates, asset directives, redirects,
and response envelopes retain their APIs. Shared objects and passable instances
survive package boundaries for the duration of a request.

For new packages, use `runtime\api\RuntimeAPI` and the corresponding controller,
router, or optional linker API. Native flags are in `runtime\enums\ERuntimeFlags`;
`CreatePassable` replaces the legacy name `CreatePassableObject`. The executor
accepts either flag generation. Native `getPassable(className)` accesses the
current package; the legacy two-argument method continues accessing a named
package. Native shared services are published with `setShared(name, value)`.

## Installation manifest and dependencies

The manifest is the flat format implemented under `package/manifest`, not the old
nested plugin/theme format. Example:

```json
{
  "name": "Example",
  "version": "1.0.0",
  "minimum_awt_version": "27.0.0",
  "maximum_awt_version": null,
  "author": "Developer",
  "type": 1,
  "system_package": false,
  "dependencies": [
    {"name": "SharedServices", "version": ">=1.0.0 <2.0.0", "url": ""}
  ]
}
```

`type` is 0 for metadata/library packages, 1 for plugins, and 2 for themes.
`dependencies` must be a list, including `[]` for packages without dependencies.
Each entry requires `name`; `version` defaults to `*` and `url` defaults to an
empty string. Supported constraints are `*`, an exact version, or whitespace-
separated comparisons (`>=`, `>`, `<=`, `<`, `=`, `==`, `!=`) combined with AND.
Caret, tilde, wildcard ranges, and OR expressions are rejected explicitly.
The URL is descriptive; dependencies are not downloaded automatically.

Installation validates the manifest, framework compatibility, and installed
versions of declared dependencies, then persists dependencies as JSON in
`awt_package.dependencies`. Both installation APIs read the flat manifest. The
new installer retains legacy `postInstall(packageId, packageName): bool` hooks
and supports native `postInstall(packageId): void` hooks. Installed packages
retain the schema's default disabled status until explicitly enabled.

The normal runtime reads dependency metadata from the database, not from
manifest files. Before any package lifecycle executes, startup checks declared
requirements for missing/disabled packages, incompatible versions, and dependency
cycles. Required executable packages finish before the dependent package begins
its lifecycle. Type-0 dependencies are checked for availability/version but do
not execute `main.php`.

Legacy `waitForRuntime(name)` / `waitForPackage(name)` and native
`waitForPackage(name)` remain execution-order requirements. Declare them in
`environmentSetup()` or `setup()`. Environment waits resolve before setup;
setup waits resolve before main without repeating setup. Missing/disabled runtime
targets and cycles fail with a diagnostic instead of retrying indefinitely. A
runtime wait does not install a package or declare a version constraint.

## Existing databases

Apply `migrations/20261005_package_dependencies.sql` once to an existing MySQL
schema, and `migrations/20261005_package_storage.sql` if `awt_storage` is absent, before installing or updating packages with the new dependency field.
Fresh imports of `awt_data/config/awt_db.sql` contain both additions.

Existing rows without dependency metadata are treated as `[]` and continue to
load. Their runtime wait declarations still work. Updating/reinstalling with the
new manifest records declarative dependencies. The migration does not infer
requirements from old package PHP or reread installed manifests.

No application database is changed by the source refactor or its test scripts.

## Defect corrections

- Runtime flags/waits belong to individual components; linker flags do not leak.
- Shared/passable registries are request-wide and survive package boundaries.
- CLI handlers exist before package initialization, so packages can add commands.
- File-based class discovery selects classes declared by the requested file and
  works when the file was already included. Controller factories are reusable.
- The homepage dispatches once and `/` matches only a `/` route.
- Query cache keys include SQL, binding values/types, and pagination bindings.
  Old SQL-only cache entries cannot be reused. Writes invalidate the entire
  affected table; JOIN queries bypass caching because they depend on other tables.
- Database connections are opened on execution rather than metadata construction.

## Verification

Requires PHP 8.1+ with DOM, PDO SQLite, and ZIP extensions:

```sh
php tests/runtime_refactor.php
php tests/runtime_refactor.php --native
php tests/bootstrap_refactor.php < /dev/null
```

The integration checks use `tests/fixtures/Branislav10` when supplied, otherwise
`awt_packages/Branislav10`. The sample package is required for these checks and
is not bundled with the tests. Each runtime test mode uses a temporary copy,
with legacy or native entry-point imports. Both modes verify all nine pages,
mixed APIs, lifecycle order, registries, dependency scheduling/failures, real
SQLite queries and disk caching, ZIP installation, hooks, and database-backed
package bootstrap.
All generated packages, compiled views, files, and databases are temporary.

The bootstrap check executes the actual boot sequence, database-backed settings,
the CLI route command, and product rendering with temporary SQLite storage.

The installer also registers data files in canonical storage and preserves legacy
`awt_data` records and `/awt_data/media/packages/...` locations for `@data` URLs.
