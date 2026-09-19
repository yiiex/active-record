<?php

namespace Yii1x\ActiveRecord\Migration;

use Generator;
use Yii1x\ActiveRecord\Contracts\MigrationManagerInterface;
use Yii1x\ActiveRecord\Contracts\PreMigrationInterface;
use Yii1x\ActiveRecord\Db\DbConnection;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Exceptions\MigrationException;
use Yii1x\ActiveRecord\ORMContext;

class MigrationManager implements MigrationManagerInterface
{
    const string BASE_MIGRATION = 'm000000_000000_base';

    public function __construct(
        protected string $tableName,
        protected string $connectionName,
        protected string $migrationPath,
    )
    {

    }

    public function create(string $name): ?PreMigrationInterface
    {
        if (!preg_match('/^\w+$/', $name)) {
            throw new \InvalidArgumentException('Migration name must contain only letters, digits and underscore characters.');
        }
        if (!is_dir($this->migrationPath)) {
            throw new \InvalidArgumentException(sprintf('Migration directory "%s" does not exist.', $this->migrationPath));
        }

        $name = 'm' . gmdate('ymd_His') . '_' . $name;
        if (!$content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'migration.stub')) {
            throw new \Exception('Unable to locate migration stub file');
        }
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $name . '.php';
        if (file_exists($file)) {
            throw new \Exception('Migration file already exists');
        }
        return !!file_put_contents($file, $content) ? new PreMigration($this, $name, PreMigrationInterface::STATUS_NEW) : null;
    }

    /**
     * @return PreMigrationInterface[]
     * @throws DbException
     */
    public function down(?int $step = null): array
    {
        $migrations = array_filter($this->migrationHistory($step ?: 1), fn($m) => $m->getName() != self::BASE_MIGRATION);
        foreach ($migrations as $preMigration) {
            $this->downMigration($preMigration);
        }
        return $migrations;
    }

    protected function downMigration($preMigration): void
    {
        try {
            $preMigration->down();
        } catch (\Throwable $e) {
            $preMigration->setStatus(PreMigrationInterface::STATUS_FAILED);
            throw $e instanceof MigrationException ? $e : MigrationException::forMigration($preMigration->getName(), $e);
        }

        $db = $this->getDbConnection();
        $db->createCommand()
            ->delete($this->tableName, $db->quoteColumnName('version') . '=:version', [
                ':version' => $preMigration->getName(),
            ]);
        $preMigration->setStatus(PreMigrationInterface::STATUS_REVERTED);
    }

    public function up(?int $step = null): array
    {
        $migrations = $this->newMigrations($step);
        foreach ($migrations as $preMigration) {
            $this->upMigration($preMigration);
        }
        return $migrations;
    }

    protected function upMigration($preMigration): void
    {
        try {
            $preMigration->up();
        } catch (\Throwable $e) {
            $preMigration->setStatus(PreMigrationInterface::STATUS_FAILED);
            throw $e instanceof MigrationException ? $e : MigrationException::forMigration($preMigration->getName(), $e);
        }

        $this->getDbConnection()->createCommand()->insert($this->tableName, [
            'version' => $preMigration->getName(),
            'apply_time' => time(),
        ]);
        $preMigration->setStatus(PreMigrationInterface::STATUS_APPLIED);
    }

    public function redo(?int $step = null): array
    {
        $downMigrations = $this->down($step);
        $migrations = $downMigrations;
        foreach (array_reverse($downMigrations) as $migration) {
            $this->upMigration($upMigration = clone $migration);
            $migrations[] = $upMigration;
        }
        return $migrations;
    }

    public function yieldDown(?int $step = null): Generator
    {
        $migrations = array_filter($this->migrationHistory($step ?: 1), fn($m) => $m->getName() != self::BASE_MIGRATION);
        foreach ($migrations as $preMigration) {
            $this->downMigration($preMigration);
            yield $preMigration;
        }
    }

    public function yieldUp(?int $step = null): Generator
    {
        foreach ($this->newMigrations($step) as $preMigration) {
            $this->upMigration($preMigration);
            yield $preMigration;
        }
    }

    public function yieldRedo(?int $step = null): Generator
    {
        $downMigrations = [];
        foreach ($this->yieldDown($step) as $preMigration) {
            $downMigrations[] = $preMigration;
            yield $preMigration;
        }
        foreach (array_reverse($downMigrations) as $preMigration) {
            $this->upMigration($upMigration = clone $preMigration);
            yield $upMigration;
        }
    }

    /**
     * @return PreMigrationInterface[]
     * @throws DbException
     */
    public function newMigrations(?int $step = null): array
    {
        $applied = [];
        foreach ($this->migrationHistory(-1) as $pre)
            $applied[substr($pre->getName(), 1, 13)] = true;

        if (!is_dir($this->migrationPath)) {
            throw new \InvalidArgumentException(sprintf('Migration directory "%s" does not exist.', $this->migrationPath));
        }

        $migrations = [];
        $handle = opendir($this->migrationPath);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..')
                continue;
            $path = $this->migrationPath . DIRECTORY_SEPARATOR . $file;
            if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file($path) && !isset($applied[$matches[2]]))
                $migrations[] = new PreMigration(
                    $this,
                    $matches[1],
                    PreMigrationInterface::STATUS_NEW,
                );
        }
        closedir($handle);
        usort($migrations, static fn(PreMigrationInterface $a, PreMigrationInterface $b): int => strcmp($a->getName(), $b->getName()));
        if ($step !== null && $step > 0) {
            $migrations = array_slice($migrations, 0, $step);
        }
        return $migrations;
    }

    public function migrationHistory(?int $limit = null): array
    {
        $db = $this->getDbConnection();
        if ($db->schema->getTable($this->tableName, true) === null) {
            $this->createMigrationHistoryTable();
        }
        return array_map(fn($m) => new PreMigration(
            $this,
            $m['version'],
            PreMigrationInterface::STATUS_APPLIED,
            $m['apply_time'],
        ), $db->createCommand()
            ->select('version, apply_time')
            ->from($this->tableName)
            ->order('version DESC')
            ->limit($limit ?: 500)
            ->queryAll());
    }

    protected function createMigrationHistoryTable(): void
    {
        $db = $this->getDbConnection();
        $db->createCommand()->createTable($this->tableName, [
            'version' => 'varchar(180) NOT NULL PRIMARY KEY',
            'apply_time' => 'integer',
        ]);
        $db->createCommand()->insert($this->tableName, [
            'version' => self::BASE_MIGRATION,
            'apply_time' => time(),
        ]);
    }

    protected function getDbConnection(): DbConnection
    {
        return ORMContext::db($this->connectionName);
    }

    public function getMigrationPath(): string
    {
        return $this->migrationPath;
    }
}
