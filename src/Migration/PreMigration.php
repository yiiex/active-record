<?php

namespace Yii1x\ActiveRecord\Migration;

use Yii1x\ActiveRecord\Contracts\MigrationManagerInterface;
use Yii1x\ActiveRecord\Contracts\PreMigrationInterface;
use Yii1x\ActiveRecord\Db\DbMigration;
use Yii1x\ActiveRecord\Exceptions\MigrationException;

class PreMigration implements PreMigrationInterface
{
    public static array $migrationCache = [];
    protected array $debug = [];
    protected ?float $executionTime = null;

    public function __construct(
        protected MigrationManagerInterface $migrationManager,
        protected string                    $name,
        protected string                    $status,
        protected ?int                      $applyTime = null,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function whenApplied(): string
    {
        return $this->applyTime ? date('d.m.Y H:i:s', $this->applyTime) : 'not applied';
    }

    public function setApplyTime(?int $applyTime): static
    {
        $this->applyTime = $applyTime;
        return $this;
    }

    public function up(): bool
    {
        $migration = $this->getMigration();
        if ($migration === null) {
            throw new MigrationException($this->name, sprintf('Migration "%s" could not be loaded.', $this->name));
        }

        try {
            return !!$this->captureDebug(fn() => $migration->up());
        } catch (\Throwable $e) {
            throw MigrationException::forMigration($this->name, $e);
        }
    }

    public function down(): bool
    {
        $migration = $this->getMigration();
        if ($migration === null) {
            throw new MigrationException($this->name, sprintf('Migration "%s" could not be loaded.', $this->name));
        }

        try {
            return !!$this->captureDebug(fn() => $migration->down());
        } catch (\Throwable $e) {
            throw MigrationException::forMigration($this->name, $e);
        }
    }

    public function getDebug(): array
    {
        return $this->debug;
    }

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function getExecutionTimeHuman(): ?string
    {
        if ($this->executionTime === null) {
            return $this->executionTime;
        }

        if ($this->executionTime < 0.001) {
            return '< 0.001s';
        }

        return number_format($this->executionTime, 3) . 's';
    }

    private function captureDebug(callable $callback): bool
    {
        $start = microtime(true);
        ob_start();
        try {
            return $callback();
        } finally {
            $output = ob_get_clean();
            $this->executionTime = microtime(true) - $start;

            if (!empty($output)) {
                foreach (explode("\n", trim($output)) as $line) {
                    if ($line = trim($line)) {
                        $this->debug[] = $line;
                    }
                }
            }
        }
    }

    protected function getMigration(): ?DbMigration
    {
        if (isset(self::$migrationCache[$this->getName()])) {
            return self::$migrationCache[$this->getName()];
        }

        if (file_exists($file = $this->migrationManager->getMigrationPath() . DIRECTORY_SEPARATOR . $this->getName() . '.php')
            && ($migration = require_once $file) && $migration instanceof DbMigration) {
            self::$migrationCache[$this->getName()] = $migration;
            return $migration;
        }
        return null;
    }
}
