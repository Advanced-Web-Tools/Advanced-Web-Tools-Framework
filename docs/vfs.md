# Virtual filesystem

The VFS keeps existing storage, resource, and transient APIs. Storage entries still
use `awt_storage`; no database migration is required.

## Storage operations

Uploads, moves, renames, and copies use random physical filenames while preserving
the original extension and display name. Existing stored paths remain valid.
Filesystem operations check their results and refuse to overwrite destinations.
Moves, renames, and deletes restore files if persistence fails. Failed copies are
removed, and failed registrations restore the source file. Repository creation
uses a database transaction for the insert and generated URL update. Filesystem
and database changes cannot form a single transaction across a process crash.

Copies preserve ownership and middleware. Saving an entry preserves both enum and
hydrated string owner types. Generated URLs encode the display filename; explicit
custom URLs are preserved. `StorageRepository` accepts an optional
`DatabaseManager`, and collection reads hydrate complete rows in one query.

Stored middleware identifiers are class names implementing
`middleware\IMiddleware`, with a constructor that requires no arguments.
Middleware runs before every HTTP storage response and `Storage::download()`, even
when database metadata is cached. Existing middleware can redirect, throw, or
return void; returning `false` denies access. Unresolvable middleware fails closed.

Intentional package asset replacements remain supported by the installer, which
stages replacement files and backs up existing assets before replacing them.

## Resources

Internal `Resource::get()` continues to resolve source files for templates and
runtime code. It accepts filenames, relative paths, and `Package:relative/path`
aliases, including `Package:/relative/path`. Filename-only searches recurse within
the selected package. Missing or invalid aliases return `null`; passing
`must: true` throws `ResourceException`. Resource cache keys include the root path.

HTTP `/awt_packages/...` requests resolve the exact requested path, including
nested folders and query strings. Files must be inside their package and one of
these public asset directories:

- `assets`, `public`, `views/assets`, or `data`
- `css`, `js`, `images`, `fonts`, `videos`, or `audio`

`PublicResource` contains the allowed extension list for static assets, including
JSON, HTML, text, XML, images, scripts, stylesheets, fonts, media, and PDF files.
Treat allowed files placed in these directories as public. PHP source, hidden
paths, package-root configuration, traversal, and links outside the package are
blocked. Existing `views/assets` URLs remain unchanged. Unknown storage download
extensions use binary responses rather than interpreting the request Accept
header as a file type.

## Blade resource URLs

`@resource()` resolves through `Resource` at render time and prints an escaped,
URL-encoded package URL, preserving the full path of the resolved file:

```blade
@resource('logo.png')
@resource('views/assets/images/logo.png')
@resource('OtherPackage:logo.png')
@resource('OtherPackage:views/assets/images/logo.png')
```

Unqualified aliases use the package name supplied by the view. If the Blade
instance has no package name, `Resource` requests the active event context.
Explicit package aliases also work without an active context.

Variables and PHP expressions are supported, for example `@resource($alias)`.
Missing resources print an empty string; `@resource($alias, true)` throws
`ResourceException` instead. This directive replaces the previous hardcoded vendor
URL behavior. `@asset()` and `@data()` keep their existing behavior. Generated
resource URLs remain subject to the public HTTP access rules above.

In automatic compilation mode, templates are recompiled when the template or
Blade compiler changes, so previously compiled directives pick up the new behavior.

## Cache and transient files

Cache validation belongs to each entry, so reconfiguring a pool does not change
validation for previously written entries. Directory snapshots recurse and track
names as well as file metadata. Watching regular files or missing paths is valid;
creating a previously missing path invalidates its cache. `HASH` hashes files,
while `MODIFIED` compares modification time, change time, and size. Use `HASH` when
same-size writes within filesystem timestamp resolution must be detected.

Cache payloads use version 2. Old or corrupt payloads are misses and are rebuilt;
existing caches do not require a manual purge. Cache and configuration writes use
an exclusive temporary file and an atomic replacement. Write failures throw, and
PHP opcode caches are invalidated after replacement.

Transient subpool selection is relative to the selected base pool, so switching
from `a` to `b` selects `b`, not `a/b`. Recursive enumeration retains files from
all levels. Names cannot contain traversal segments. JSON files receiving array
content are serialized as JSON; PHP/cache arrays retain their PHP return format.
Directory link cycles are excluded from traversal; linked package roots remain
supported.

## Database settings and checks

`DB_HOSTNAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`, and `DB_TYPE` can be supplied
through environment variables. The existing values in `awt_db.php` remain the
fallbacks, including an explicitly empty environment value.

Run:

```sh
php tests/vfs.php
php tests/orm.php
php tests/runtime_refactor.php
php tests/bootstrap_refactor.php
```

The VFS tests use temporary files and an in-memory SQLite database, covering data
loss, failed I/O and persistence, authorization, caching, resource HTTP access,
package asset replacement, and transient storage. Runtime and bootstrap tests
also verify the existing sample package.
