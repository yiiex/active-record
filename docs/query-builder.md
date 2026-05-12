# QueryBuilder

Fluent query builder for ActiveRecord. Provides a chainable interface for building query criteria including conditions, ordering, joins, and eager loading.

## Table of Contents

- [Basic Usage](#basic-usage)
- [SELECT](#select)
- [WHERE Conditions](#where-conditions)
- [JOINs](#joins)
- [Relations](#relations)
- [Scopes](#scopes)
- [Ordering, Grouping & Pagination](#ordering-grouping--pagination)
- [Eager Loading](#eager-loading)
- [Executing Queries](#executing-queries)
- [Conditional Methods](#conditional-methods)

---

## Basic Usage

```php
use Yii1x\ActiveRecord\QueryBuilder;

$posts = Post::query()
    ->where('status', 'published')
    ->orderBy('created_at DESC')
    ->limit(10)
    ->findAll();
```

**Constructor parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$model` | `ActiveRecord` | The model to build query for |
| `$criteria` | `?DbCriteria` | Initial criteria (default: `alias = 't'`) |

---

## SELECT

```php
$qb->select('id, title, created_at');
$qb->select(['id', 'title', 'created_at']);
$qb->distinct();          // SELECT DISTINCT
$qb->distinct(false);     // turn off
```

---

## WHERE Conditions

All where methods accept an `$operator` parameter (`'AND'` by default, or `'OR'`).

### where()

```php
// Simple equality
$qb->where('status', 'published');

// With comparison operator
$qb->where('age', '>=', 18);

// Closure for grouped conditions
$qb->where(function (ConditionBuilder $cb) {
    $cb->where('status', 'published')
       ->where('is_featured', 1, operator: 'OR');
});
```

### whereIn / whereNotIn

```php
$qb->whereIn('id', [1, 2, 3]);
$qb->whereNotIn('status', ['deleted', 'spam']);
```

### whereNull / whereNotNull

```php
$qb->whereNull('deleted_at');
$qb->whereNotNull('email_verified_at');
```

### whereBetween

```php
$qb->whereBetween('created_at', '2024-01-01', '2024-12-31');
```

### whereRaw

Raw SQL with bound parameters:

```php
$qb->whereRaw('created_at >= :date', [':date' => '2024-01-01']);
```

### like

```php
$qb->like('title', '%laravel%');
```

### whereHas

Check for existence of related records in an arbitrary table:

```php
$qb->whereHas('comments', function (ConditionBuilder $cb) {
    $cb->where('status', 'approved');
});
```

### whereRelation

Check for existence through a declared model relation:

```php
// HAS_MANY
$qb->whereRelation('comments', function (ConditionBuilder $cb) {
    $cb->where('status', 'approved');
});

// MANY_MANY
$qb->whereRelation('tags', function (ConditionBuilder $cb) {
    $cb->whereIn('name', ['laravel', 'php']);
});

// Nested relations via dot notation
$qb->whereRelation('comments.author', function (ConditionBuilder $cb) {
    $cb->where('role', 'admin');
});

// Without callback — plain EXISTS
$qb->whereRelation('comments');
```

All relation types are supported:

- `HAS_ONE` / `HAS_MANY` / `BELONGS_TO` — direct foreign key comparison
- `MANY_MANY` — INNER JOIN with junction table
- `through` — nested EXISTS

> **Note:** `MANY_MANY` combined with `through` is not supported and will throw a `DbException`.

---

## JOINs

| Method | SQL |
|---|---|
| `innerJoin($table, $condition, $alias)` | `INNER JOIN` |
| `leftJoin($table, $condition, $alias)` | `LEFT JOIN` |
| `rightJoin($table, $condition, $alias)` | `RIGHT JOIN` |

```php
$qb->innerJoin('profile', 'profile.user_id = t.id', 'p');
$qb->leftJoin('avatar', 'avatar.profile_id = p.id', 'a');
```

The `$condition` is raw SQL. Table names are quoted via `quoteTableName`.

---

## Scopes

```php
// Deferred scopes — applied when the query is executed
$qb->scopes('published');
$qb->scopes(['published', 'recent']);

// Immediate scopes — applied right away, condition is merged into criteria
$qb->applyScopes('active');
```

The difference:
- `scopes()` — stores scope names in `$criteria->scopes`, processed at execution time
- `applyScopes()` — evaluates the scope immediately and merges its conditions into the current criteria

---

## Ordering, Grouping & Pagination

```php
$qb->orderBy('created_at DESC');
$qb->orderBy(['created_at DESC', 'title ASC']);
$qb->groupBy('category_id');
$qb->groupBy(['category_id', 'status']);
$qb->having('cnt > 10');
$qb->limit(10);
$qb->offset(20);
$qb->indexBy('id');   // index result array by column value
```

---

## Eager Loading

```php
$qb->with('comments');
$qb->with(['comments', 'tags']);
```

Control join strategy:

```php
$qb->with('comments')->together();       // single query with JOINs
$qb->with('comments')->together(false);  // separate queries for each relation
```

---

## Executing Queries

| Method | Returns |
|---|---|
| `count()` | `int` |
| `exists()` | `bool` |
| `find()` | `?ActiveRecord` |
| `findAll()` | `array` |
| `deleteAll()` | `int` |

All execution methods clone `$criteria`, so the original `QueryBuilder` remains unmodified and can be reused:
---

## Conditional Methods

### when()

```php
$qb->when(
    $request->has('status'),
    fn(QueryBuilder $qb, $value) => $qb->where('status', $value),
    fn(QueryBuilder $qb) => $qb->whereNull('status')  // fallback (optional)
);
```

The truthy value is passed as the second argument to the callback.

### fork()

Creates an independent copy of the builder with cloned criteria. Useful for branching queries:

```php
$baseQb = Post::query()->where('type', 'article');

$published = $baseQb->fork()->where('status', 'published')->count();
$drafts    = $baseQb->fork()->where('status', 'draft')->count();
```

---

## Chaining

All methods except execution methods (`count`, `exists`, `find`, `findAll`, `deleteAll`) return `$this`:

```php
$posts = Post::query()
    ->select('id, title')
    ->distinct()
    ->where('status', 'published')
    ->whereRelation('tags', fn($cb) => $cb->whereIn('name', ['php', 'laravel']))
    ->orderBy('created_at DESC')
    ->limit(10)
    ->findAll();
```
