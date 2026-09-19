<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Exceptions;

use Throwable;

/**
 * Thrown when a migration cannot be loaded, applied or reverted.
 */
class MigrationException extends DbException
{
    public function __construct(
        public readonly string $migrationName,
        string $message,
        ?Throwable $previous = null,
    )
    {
        parent::__construct($message, (int)($previous?->getCode() ?? 0), null, $previous);
    }

    public static function forMigration(string $name, Throwable $previous): self
    {
        return new self(
            $name,
            sprintf('Migration "%s" failed: %s', $name, $previous->getMessage()),
            $previous,
        );
    }
}
