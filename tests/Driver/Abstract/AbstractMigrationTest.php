<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Contracts\PreMigrationInterface;
use Yii1x\ActiveRecord\Exceptions\MigrationException;
use Yii1x\ActiveRecord\Migration\MigrationManager;
use Yii1x\ActiveRecord\Migration\PreMigration;
use Yii1x\ActiveRecord\ORMContext;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestContainer;

abstract class AbstractMigrationTest extends AbstractDatabaseTest
{
    protected string $migrationPath;
    protected MigrationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;

        $container = new TestContainer();
        $container->set('default', $this->connection);
        ORMContext::bootstrap($container);

        $this->dropMigrationTables();

        $this->migrationPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yii1x_mig_' . bin2hex(random_bytes(6));
        mkdir($this->migrationPath, 0777, true);

        PreMigration::$migrationCache = [];
        $this->manager = new MigrationManager('tbl_migration', 'default', $this->migrationPath);
    }

    protected function tearDown(): void
    {
        $this->dropMigrationTables();
        $this->removeDirectory($this->migrationPath);
        PreMigration::$migrationCache = [];
        ORMContext::bootstrap(new TestContainer());
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    private function dropMigrationTables(): void
    {
        foreach (['tbl_migration', 'mig_a', 'mig_c'] as $table) {
            $this->connection->createCommand("DROP TABLE IF EXISTS {$table}")->execute();
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (glob($path . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($path);
    }

    protected function writeMigration(string $name, string $safeUp, string $safeDown = ''): void
    {
        $code = "<?php\n\nuse Yii1x\\ActiveRecord\\Db\\DbMigration;\n\n"
            . "return new class extends DbMigration\n{\n"
            . "    public function safeUp()\n    {\n" . $this->indent($safeUp) . "\n    }\n\n"
            . "    public function safeDown()\n    {\n" . $this->indent($safeDown) . "\n    }\n"
            . "};\n";

        file_put_contents($this->migrationPath . DIRECTORY_SEPARATOR . $name . '.php', $code);
    }

    private function indent(string $code): string
    {
        if (trim($code) === '') {
            return '';
        }

        return implode("\n", array_map(static fn(string $line): string => '        ' . $line, explode("\n", $code)));
    }

    protected function historyNames(): array
    {
        return array_map(
            static fn(PreMigrationInterface $migration): string => $migration->getName(),
            $this->manager->migrationHistory()
        );
    }

    protected function tableExists(string $table): bool
    {
        return $this->connection->getSchema()->getTable($table, true) !== null;
    }

    protected function rowCount(string $table): int
    {
        return (int)$this->connection->createCommand("SELECT COUNT(*) FROM {$table}")->queryScalar();
    }

    // ---------------------------------------------------------------
    //  Discovery & history
    // ---------------------------------------------------------------

    public function testGetMigrationPath(): void
    {
        $this->assertSame($this->migrationPath, $this->manager->getMigrationPath());
    }

    public function testMigrationHistoryCreatesTableWithBaseEntry(): void
    {
        $history = $this->manager->migrationHistory();
        $this->assertTrue($this->tableExists('tbl_migration'));

        $this->assertCount(1, $history);
        $this->assertSame(MigrationManager::BASE_MIGRATION, $history[0]->getName());
        $this->assertSame(PreMigrationInterface::STATUS_APPLIED, $history[0]->getStatus());
    }

    public function testNewMigrationsFindsAndSorts(): void
    {
        $this->writeMigration('m250101_000003_c', '');
        $this->writeMigration('m250101_000001_a', '');
        $this->writeMigration('m250101_000002_b', '');

        $names = array_map(
            static fn(PreMigrationInterface $m): string => $m->getName(),
            $this->manager->newMigrations()
        );

        $this->assertSame(['m250101_000001_a', 'm250101_000002_b', 'm250101_000003_c'], $names);
    }

    public function testNewMigrationsRespectsLimit(): void
    {
        $this->writeMigration('m250101_000001_a', '');
        $this->writeMigration('m250101_000002_b', '');
        $this->writeMigration('m250101_000003_c', '');

        $names = array_map(
            static fn(PreMigrationInterface $m): string => $m->getName(),
            $this->manager->newMigrations(2)
        );

        $this->assertSame(['m250101_000001_a', 'm250101_000002_b'], $names);
    }

    // ---------------------------------------------------------------
    //  Up / down / redo
    // ---------------------------------------------------------------

    public function testUpAppliesMigrationsAndRecordsHistory(): void
    {
        $this->writeMigration('m250101_000001_a', "\$this->createTable('mig_a', ['id' => 'pk']);", "\$this->dropTable('mig_a');");
        $this->writeMigration('m250101_000002_b', "\$this->insert('mig_a', ['id' => 1]);", "\$this->delete('mig_a', 'id=1');");

        $applied = $this->manager->up();

        $this->assertCount(2, $applied);
        foreach ($applied as $migration) {
            $this->assertSame(PreMigrationInterface::STATUS_APPLIED, $migration->getStatus());
        }

        $this->assertTrue($this->tableExists('mig_a'));
        $this->assertSame(1, $this->rowCount('mig_a'));
        $this->assertSame(['m250101_000002_b', 'm250101_000001_a', MigrationManager::BASE_MIGRATION], $this->historyNames());
    }

    public function testDownRevertsAndRemovesHistory(): void
    {
        $this->writeMigration('m250101_000001_a', "\$this->createTable('mig_a', ['id' => 'pk']);", "\$this->dropTable('mig_a');");
        $this->writeMigration('m250101_000002_b', "\$this->insert('mig_a', ['id' => 1]);", "\$this->delete('mig_a', 'id=1');");

        $this->manager->up();

        $reverted = $this->manager->down(1);

        $this->assertCount(1, $reverted);
        $this->assertSame('m250101_000002_b', $reverted[0]->getName());
        $this->assertSame(PreMigrationInterface::STATUS_REVERTED, $reverted[0]->getStatus());
        $this->assertSame(0, $this->rowCount('mig_a'));
        $this->assertSame(['m250101_000001_a', MigrationManager::BASE_MIGRATION], $this->historyNames());
    }

    public function testRedo(): void
    {
        $this->writeMigration('m250101_000001_a', "\$this->createTable('mig_a', ['id' => 'pk']);", "\$this->dropTable('mig_a');");
        $this->writeMigration('m250101_000002_b', "\$this->insert('mig_a', ['id' => 1]);", "\$this->delete('mig_a', 'id=1');");

        $this->manager->up();
        $this->manager->redo(1);

        $this->assertSame(1, $this->rowCount('mig_a'));
        $this->assertContains('m250101_000002_b', $this->historyNames());
    }

    public function testYieldUpYieldsAppliedMigrations(): void
    {
        $this->writeMigration('m250101_000001_a', "\$this->createTable('mig_a', ['id' => 'pk']);", "\$this->dropTable('mig_a');");

        $yielded = [];
        foreach ($this->manager->yieldUp() as $migration) {
            $yielded[] = $migration;
        }

        $this->assertCount(1, $yielded);
        $this->assertSame(PreMigrationInterface::STATUS_APPLIED, $yielded[0]->getStatus());
        $this->assertTrue($this->tableExists('mig_a'));
    }

    // ---------------------------------------------------------------
    //  Failure handling
    // ---------------------------------------------------------------

    public function testStopsOnFirstFailureAndKeepsPreviousMigrations(): void
    {
        $this->writeMigration('m250101_000001_a', "\$this->createTable('mig_a', ['id' => 'pk']);", "\$this->dropTable('mig_a');");
        $this->writeMigration('m250101_000002_b', "\$this->insert('mig_a', ['id' => 1]);\nthrow new \\RuntimeException('boom');");
        $this->writeMigration('m250101_000003_c', "\$this->createTable('mig_c', ['id' => 'pk']);");

        try {
            $this->manager->up();
            $this->fail('MigrationException was expected');
        } catch (MigrationException $e) {
            $this->assertSame('m250101_000002_b', $e->migrationName);
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }

        // A is applied and recorded
        $this->assertTrue($this->tableExists('mig_a'));
        $this->assertSame(['m250101_000001_a', MigrationManager::BASE_MIGRATION], $this->historyNames());

        // B's changes are rolled back and it is not recorded
        $this->assertSame(0, $this->rowCount('mig_a'));

        // C was never applied
        $this->assertFalse($this->tableExists('mig_c'));

        // B and C remain new
        $this->assertSame(['m250101_000002_b', 'm250101_000003_c'], array_map(
            static fn(PreMigrationInterface $m): string => $m->getName(),
            $this->manager->newMigrations()
        ));
    }

    public function testSafeUpReturningFalseThrowsAndRollsBack(): void
    {
        $this->writeMigration(
            'm250101_000001_a',
            "\$this->insert('users', ['username' => 'mig_user', 'password' => 'p', 'email' => 'mig@example.com']);\nreturn false;"
        );

        try {
            $this->manager->up();
            $this->fail('MigrationException was expected');
        } catch (MigrationException $e) {
            $this->assertSame('m250101_000001_a', $e->migrationName);
        }

        $this->assertSame(
            0,
            (int)$this->connection->createCommand("SELECT COUNT(*) FROM users WHERE username = 'mig_user'")->queryScalar()
        );
        $this->assertSame([MigrationManager::BASE_MIGRATION], $this->historyNames());
    }

    public function testThrownErrorIsWrapped(): void
    {
        $this->writeMigration('m250101_000001_a', "throw new \\Error('broken');");

        try {
            $this->manager->up();
            $this->fail('MigrationException was expected');
        } catch (MigrationException $e) {
            $this->assertInstanceOf(\Error::class, $e->getPrevious());
        }

        $this->assertSame([MigrationManager::BASE_MIGRATION], $this->historyNames());
    }

    public function testMissingMigrationFileThrows(): void
    {
        $pre = new PreMigration($this->manager, 'm250101_000000_ghost', PreMigrationInterface::STATUS_NEW);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('could not be loaded');

        $pre->up();
    }

    public function testCaptureDebugIsCleanedUpOnFailure(): void
    {
        $this->writeMigration('m250101_000001_a', "throw new \\RuntimeException('boom');");
        $level = ob_get_level();

        try {
            $this->manager->up();
            $this->fail('MigrationException was expected');
        } catch (MigrationException) {
            // expected
        }

        $this->assertSame($level, ob_get_level());
    }

    // ---------------------------------------------------------------
    //  create()
    // ---------------------------------------------------------------

    public function testCreateRejectsInvalidName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->create('../evil');
    }

    public function testCreateRequiresExistingDirectory(): void
    {
        $manager = new MigrationManager('tbl_migration', 'default', $this->migrationPath . DIRECTORY_SEPARATOR . 'missing');

        $this->expectException(\InvalidArgumentException::class);
        $manager->create('create_users');
    }

    public function testNewMigrationsRequiresExistingDirectory(): void
    {
        $manager = new MigrationManager('tbl_migration', 'default', $this->migrationPath . DIRECTORY_SEPARATOR . 'missing');

        $this->expectException(\InvalidArgumentException::class);
        $manager->newMigrations();
    }

    public function testCreateWritesMigrationFile(): void
    {
        $migration = $this->manager->create('create_users');

        $this->assertNotNull($migration);
        $this->assertSame(PreMigrationInterface::STATUS_NEW, $migration->getStatus());
        $this->assertMatchesRegularExpression('/^m\d{6}_\d{6}_create_users$/', $migration->getName());
        $this->assertFileExists($this->migrationPath . DIRECTORY_SEPARATOR . $migration->getName() . '.php');
    }
}
