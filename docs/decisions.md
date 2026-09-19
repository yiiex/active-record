# Design Decisions

Running log of deliberate design choices, the alternatives that were rejected, and
the reasons behind them. Newest entries go on top.

---

## Optional PSR services and cache identity

**Date:** 2026-09-19

### Context

The package only requires PSR-11 (`ContainerInterface`). `Psr\Log`, PSR-16 and PSR-14
are declared optional in the README, but the implementation treated them as required:
`ORMContext::cache()`, `ORMContext::log()` and `ORMContext::dispatch()` called
`ContainerInterface::get()` unconditionally, so a container without those services made
any code path that logs, caches or profiles throw a "service not found" exception.

The cache configuration also carried Yii 1 leftovers:

- `$queryCacheID` / `$schemaCacheID` were declared as `string` but were meant to be
  service ids resolved from the application service locator (`Yii::app()->getComponent()`),
  with `false` disabling caching. The port resolved a single, fixed
  `CacheInterface::class` service instead, which made the `!== false` check dead code
  and left the documented `false` switch unusable.
- `$queryCachingDependency` was kept, but PSR-16 `CacheInterface::set()` has no
  dependency argument, so it could never be used for invalidation.

### Decision

1. Logger, cache and event dispatcher are truly optional:
   - `ORMContext::log()` returns `null` when no logger is registered.
   - `ORMContext::dispatch()` is a no-op when no event dispatcher is registered.
   - `ORMContext::cache(?string $id = null)` returns `null` when the requested service
     is not registered, and throws a `RuntimeException` when the service exists but does
     not implement `CacheInterface` (configuration error, should not be silent).
2. Cache identity is a PSR-11 service id:
   - `$queryCacheID` and `$schemaCacheID` are `?string`, defaulting to
     `CacheInterface::class`.
   - `null` disables the corresponding cache; the check lives in the caller
     (`DbCommand`, `DbSchema`), not inside `ORMContext::cache()`.
   - `ORMContext::cache(null)` means "use the default `CacheInterface::class` id".
3. Removed `string|false` semantics: disabling is expressed by `null` or by
   `queryCachingDuration = 0` / `schemaCachingDuration = 0`.

### Removed / rejected

- **`$queryCachingDependency`** — removed together with the `$dependency` parameter of
  `DbConnection::cache()` and `ActiveRecord::cache()`. PSR-16 cannot express cache
  dependencies, so keeping the property only promised behaviour that did not exist.
  *(Breaking change: `cache()` no longer accepts a dependency argument.)*
- **`queryCacheID = false` / `schemaCacheID = false`** — replaced by `null`.
- **Resolving a cache by arbitrary container id with silent fallback on a type
  mismatch** — rejected in favour of an explicit exception, so typos in service ids do
  not silently disable caching.
- **Making `log()`/`dispatch()` throw when the optional service is missing** — rejected,
  contradicts the documented optional dependencies.
