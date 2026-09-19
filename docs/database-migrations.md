# Database Migration Guide

## Installation

For console commands, ensure your application has `symfony/console` dependency:

```bash
composer require symfony/console
```

## Built-in Symfony Command

The package includes a ready-to-use `migrate` command for Symfony Console applications.

### Usage

```bash
# Apply all new migrations
php console migrate up

# Apply 3 new migrations
php console migrate up -l 3

# Revert last migration
php console migrate down -l 1

# Revert 3 last migrations
php console migrate down -l 3

# Redo migrations (revert + apply again)
php console migrate redo -l 2

# Create new migration
php console migrate make --name=create_user_table

# Show new migrations
php console migrate new

# Show migration history (last 20)
php console migrate history -l 20

# Debug mode (shows SQL output)
php console migrate up -d
```

### Available Actions

| Action | Description |
|--------|-------------|
| `up` | Apply new migrations |
| `down` | Revert applied migrations |
| `redo` | Reapply migrations (down + up) |
| `make` | Create new migration file |
| `new` | Show list of new migrations |
| `history` | Show applied migrations history |

### Options

| Option | Short | Description |
|--------|-------|-------------|
| `--limit` | `-l` | Number of migrations (for up/down/redo/new/history) |
| `--name` | | Migration name (for make) |
| `--debug` | `-d` | Show detailed SQL output |

> **Note:** the migration name passed to `make --name=...` (and to `MigrationManager::create()`)
> must contain only letters, digits and underscore characters (`[A-Za-z0-9_]+`).

## MigrationManager Interface

```php
namespace Yii1x\ActiveRecord\Contracts;

use Generator;

interface MigrationManagerInterface
{
    /**
     * Create a new migration file
     */
    public function create(string $name): ?PreMigrationInterface;

    /**
     * Apply new migrations
     * @return PreMigrationInterface[]
     */
    public function up(?int $step = null): array;

    /**
     * Revert applied migrations
     * @return PreMigrationInterface[]
     */
    public function down(?int $step = null): array;

    /**
     * Redo migrations (down + up)
     * @return PreMigrationInterface[]
     */
    public function redo(?int $step = null): array;

    /**
     * Stream generator for up migrations
     * @return Generator<PreMigrationInterface>
     */
    public function yieldUp(?int $step = null): Generator;

    /**
     * Stream generator for down migrations
     * @return Generator<PreMigrationInterface>
     */
    public function yieldDown(?int $step = null): Generator;

    /**
     * Stream generator for redo migrations
     * @return Generator<PreMigrationInterface>
     */
    public function yieldRedo(?int $step = null): Generator;

    /**
     * Get list of new (not applied) migrations
     * @return PreMigrationInterface[]
     */
    public function newMigrations(?int $step = null): array;

    /**
     * Get migration history
     * @return PreMigrationInterface[]
     */
    public function migrationHistory(?int $limit = null): array;

    /**
     * Get path to migration files
     */
    public function getMigrationPath(): string;
}
```

## Configuration Examples

### Yii3 DI Container

```php
<?php

use Yii1x\ActiveRecord\Contracts\MigrationManagerInterface;
use Yii1x\ActiveRecord\Migration\MigrationManager;

return [
    MigrationManagerInterface::class => [
        'class' => MigrationManager::class,
        '__construct()' => [
            'tableName' => 'tbl_migration',
            'connectionName' => 'db_main',
            'migrationPath' => dirname(__DIR__, 3) . '/migrations',
        ],
    ],
];
```

### Register Command in Yii3

```php
<?php

declare(strict_types=1);

use Yii1x\ActiveRecord\Console\MigrateCommand;

return [
    'migrate' => MigrateCommand::class,
    // other commands...
];
```

## Creating Migrations

Migrations are PHP files with the following structure:

```php
<?php

use Yii1x\ActiveRecord\Attributes\Database;
use Yii1x\ActiveRecord\Db\DbMigration;

return new
#[Database('db_main')]
class extends DbMigration {
    public function safeUp()
    {
        // Migration logic here
    }

    public function safeDown()
    {
        // Rollback logic here
    }
};
```
