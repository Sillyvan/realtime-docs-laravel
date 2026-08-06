---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## Return array<int, Shape>, not Collection, for Inertia list props

PHPStan 2.2 reports `return.type` ("Template type TValue ... is not covariant") whenever an array shape containing a union — e.g. `array{id: int|null}` — is the TValue of an invariant generic like `Illuminate\Support\Collection` or `LengthAwarePaginator`. It fires even when the declared and returned types are the identical alias; the two types print the same in the error. Not a version lag — 2.2.8 is current.

So: shape helpers that feed Inertia props return `array<int, Shape>` (end the chain with `->all()`), never `Collection<int, Shape>`. Arrays are covariant, so precision is kept and the false positive goes away. JSON output is unchanged.

Paginators have no covariant escape hatch — `DocumentController::paginateDocuments()` keeps its precise `@return LengthAwarePaginator<int, ListItem>` and phpstan.neon carries one narrowly-scoped `ignoreErrors` entry for it. Don't widen the shape to `array<string, mixed>` to dodge this.

Declare the shapes once as class-level `@phpstan-type` aliases (ListItem, MemberRow, FolderItem, InvitationRow) and reference them from each helper.
