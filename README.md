# Yii1x Active Record

> Yii 1.1 Active Record, extracted and modernized for PHP 8.4+.

Familiar API. Zero framework lock-in.

[![Packagist](https://img.shields.io/packagist/v/yii1x/active-record)](https://packagist.org/packages/yii1x/active-record)
[![License](https://img.shields.io/packagist/l/yii1x/active-record)](https://packagist.org/packages/yii1x/active-record)
[![CI](https://github.com/yiiex/active-record/actions/workflows/ci.yaml/badge.svg?branch=master)](https://github.com/yiiex/active-record/actions/workflows/ci.yaml)

---

## What is it?

This package is the Active Record component from **Yii 1.1**, extracted and refactored to run on **PHP 8.4+** without requiring the full Yii framework.

- ✅ Same API you know from Yii 1
- ✅ PHP 8 types and attributes support
- ✅ No `Yii::app()` dependency
- ✅ Works with any PSR-11 compatible container

---

## Requirements

- PHP ≥ 8.4
- PSR-11 `ContainerInterface` (required)
- Optional PSR dependencies (if used in your app):
    - `Psr\Log\LoggerInterface`
    - `Psr\SimpleCache\CacheInterface`
    - `Psr\EventDispatcher\EventDispatcherInterface`

---

## Installation

```bash
composer require yii1x/active-record
```

---

## Bootstrap

Before using any model, initialize the ORM context once per application lifecycle:

```php
use Yii1x\ActiveRecord\ORMContext;

ORMContext::bootstrap($container, debug: false);
```

Where `$container` is your PSR-11 container.

The container must be able to return a **configured database connection instance** by name (e.g., `'db'` or `'yourDbName'`).

This can be:
- An instance of `Yii1x\ActiveRecord\Db\DbConnection`
- Any class extending `DbConnection`

---

### Example configuration (from Yii 3):

```php
<?php
return [
    'db_name' => [
        'class' => \Yii1x\ActiveRecord\Db\DbConnection::class,
        '__construct()' => [
            'dsn' => 'mysql:host=db;port=3306;dbname=yii3',
            'username' => 'root',
            'password' => 'root',
            'connectionName' => 'db_name',
        ],
    ],
];
```

Then in your models, specify which connection to use:

```php
#[Database(name: 'db_name')]
class User extends ActiveRecord
{
    // ...
}
```

---

## Define a Model

Use PHP 8 attributes instead of class properties:

```php
use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Attributes\Table;
use Yii1x\ActiveRecord\Attributes\Database;

#[Table(name: 'user')]
#[Database(name: 'db_name')]
class User extends ActiveRecord
{
    public function relations(): array
    {
        return [
            'posts' => [self::HAS_MANY, Post::class, 'user_id'],
        ];
    }
}
```

---

## Querying (familiar Yii 1 style)

```php
// Find one
$user = User::model()->findByAttributes(['email' => 'test@example.com']);

// new fluent query builder
$users = User::queryBuilder()
    ->where('status', 1)
    ->orderBy('created_at DESC')
    ->limit(10)
    ->with('posts')
    ->findAll();
```

## Testing

The package includes tests for **MySQL**, **PostgreSQL**, and **SQLite** to ensure cross-database compatibility.
Requires Docker Compose to launch database containers (SQLite runs in-memory).

```bash
# Start database containers
docker-compose up -d

# Run all tests
docker-compose run --rm php vendor/bin/phpunit

# Run specific driver only (faster during development)
docker-compose run --rm php vendor/bin/phpunit --filter "Mysql" # or "Pgsql" / "Sqlite"
```
Tests run automatically on GitHub Actions for every push and pull request. See full setup details in [docker-compose.yaml](docker-compose.yaml).

## Framework Agnostic

Works with any **PSR-11 container** (as shown in the Yii 3 example above).

No Yii framework required. No global state. Just Active Record.

## License

Released under the [BSD-3-Clause License](LICENSE).

Based on [Yii 1.1](https://github.com/yiisoft/yii) by Yii Software LLC
(BSD-3-Clause); portions copyright (c) 2025 Galtsev Timofey.
