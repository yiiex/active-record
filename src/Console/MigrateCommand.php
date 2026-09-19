<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Yii1x\ActiveRecord\Contracts\MigrationManagerInterface;
use Yii1x\ActiveRecord\Contracts\PreMigrationInterface;
use Yii1x\ActiveRecord\Exceptions\MigrationException;

#[AsCommand(
    name: 'migrate',
    description: 'Database migrations management',
)]
final class MigrateCommand extends Command
{
    public function __construct(protected MigrationManagerInterface $migrateManager)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->setHelp('Run database migrations: up, down, redo, make, new, history')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'Action to perform: up, down, redo, make, new, history',
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Number of migrations for up/down actions',
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Migration name for new action',
            )
            ->addOption(
                'debug',
                'd',
                InputOption::VALUE_NONE,
                'Show detailed migration output',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $limit = $input->getOption('limit');
        $limit = is_numeric($limit) ? (int)$limit : $limit;
        $name = $input->getOption('name');
        $debug = $input->getOption('debug');

        if ($limit !== null) {
            if (!is_numeric($limit)) {
                $output->writeln('');
                $output->writeln('<fg=red>❌ Error: Limit must be a number</>');
                $output->writeln('<fg=green>✅ Correct usage: -l 5 or --limit=5 or -l5</>');
                $output->writeln('');
                return Command::INVALID;
            }
            $limit = (int)$limit;
            if ($limit <= 0) {
                $output->writeln('');
                $output->writeln('<fg=red>❌ Error: Limit must be greater than 0</>');
                $output->writeln('');
                return Command::INVALID;
            }
        }
        try {
            return match ($action) {
                'up' => $this->actionUp($limit, $output, $debug),
                'down' => $this->actionDown($limit, $output, $debug),
                'redo' => $this->actionRedo($limit, $output, $debug),
                'make' => $this->actionMake($name, $output),
                'new' => $this->actionNew($limit, $output),
                'history' => $this->actionHistory($limit, $output),
                default => $this->invalidAction($action, $output),
            };
        } catch (MigrationException $e) {
            $output->writeln('');
            $output->writeln('<fg=red>❌ ' . $e->getMessage() . '</>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }

    protected function actionMake(?string $name, OutputInterface $output): int
    {
        $output->writeln('');
        if (!$name) {
            $output->writeln('<fg=red>❌ Error: Migration name required</>');
            $output->writeln('');
            return Command::INVALID;
        }

        $preMigration = $this->migrateManager->create($name);

        if ($preMigration) {
            $output->writeln('<options=bold;fg=green>📦 Migration created successfully:</>');
            $output->writeln('');
            $table = $this->getMigrationTable([$preMigration], $output);
            $table->render();
            $output->writeln('');
            return Command::SUCCESS;
        }

        $output->writeln('<fg=red>❌ Migration creation failed</>');
        $output->writeln('');
        return Command::FAILURE;
    }

    protected function actionUp(?int $limit, OutputInterface $output, bool $debug = false): int
    {
        $output->writeln('');
        if ($limit === null) {
            $output->writeln('<options=bold;fg=green>⬆️  Applying all new migrations...</>');
        } else {
            $output->writeln("<options=bold;fg=green>⬆️  Applying {$limit} new migration(s)...</>");
        }
        $output->writeln('');
        $migrations = [];
        foreach($this->migrateManager->yieldUp($limit) as $migration) {
            $migrations[] = $migration;
            if ($debug) {
                $this->showDebugOutput([$migration], $output);
            }
        }

        if (!empty($migrations)) {
            $table = $this->getMigrationTable($migrations, $output, ['executionTime']);
            $table->render();
        } else {
            $output->writeln('<fg=yellow>⚠️  No migrations to apply</>');
        }
        $output->writeln('');
        return Command::SUCCESS;
    }

    protected function actionDown(?int $limit, OutputInterface $output, bool $debug = false): int
    {
        $output->writeln('');
        $output->writeln(sprintf("<options=bold;fg=magenta>🔄 Reverting %d applied migration(s)...</>", $limit ?: 1));
        $output->writeln('');

        $migrations = [];
        foreach($this->migrateManager->yieldDown($limit) as $migration) {
            $migrations[] = $migration;
            if ($debug) {
                $this->showDebugOutput([$migration], $output);
            }
        }

        if (!empty($migrations)) {
            $table = $this->getMigrationTable($migrations, $output, ['executionTime']);
            $table->render();
        } else {
            $output->writeln('<fg=yellow>⚠️  No migrations to revert</>');
        }
        $output->writeln('');
        return Command::SUCCESS;
    }

    protected function actionRedo(?int $limit, OutputInterface $output, bool $debug = false): int
    {
        $output->writeln('');
        $output->writeln(sprintf("<options=bold;fg=magenta>🔄 Redoing %d applied migration(s)...</>", $limit ?: 1));
        $output->writeln('');
        $migrations = [];
        foreach($this->migrateManager->yieldRedo($limit) as $migration) {
            $migrations[] = $migration;
            if ($debug) {
                $this->showDebugOutput([$migration], $output);
            }
        }
        if (!empty($migrations)) {
            $table = $this->getMigrationTable($migrations, $output, ['executionTime']);
            $table->render();
        } else {
            $output->writeln('<fg=yellow>⚠️  No migrations to redo</>');
        }
        $output->writeln('');
        return Command::SUCCESS;
    }

    protected function actionNew(?int $limit, OutputInterface $output): int
    {
        $output->writeln('');
        if ($migrations = $this->migrateManager->newMigrations($limit)) {
            $output->writeln('<options=bold;fg=green>📋 New migrations found:</>');
            $output->writeln('');
            $migrationTable = $this->getMigrationTable($migrations, $output);
            $migrationTable->render();
            $output->writeln('');
            return Command::SUCCESS;
        }

        $output->writeln('<fg=yellow>⚠️  No new migrations found</>');
        $output->writeln('');
        return Command::SUCCESS;
    }

    protected function actionHistory(?int $limit, OutputInterface $output): int
    {
        $output->writeln('');
        $migrations = $this->migrateManager->migrationHistory($limit);

        if (!empty($migrations)) {
            $output->writeln('<options=bold;fg=blue>📜 Migration history:</>');
            $output->writeln('');

            // Для истории показываем еще и дату применения
            $table = $this->getMigrationTable($migrations, $output, ['whenApplied']);
            $table->render();
        } else {
            $output->writeln('<fg=yellow>⚠️  No migration history found</>');
        }
        $output->writeln('');

        return Command::SUCCESS;
    }

    protected function getMigrationTable(array $migrations, OutputInterface $output, $additionColumns = []): Table
    {
        $table = new Table($output);

        $columns = [
            '#' => [
                'value' => fn($index, $migration) => $index + 1,
            ],
            'Migration' => [
                'value' => fn($index, $migration) => $migration->getName(),
            ],
            ...array_filter([
                'Execution time' => [
                    'value' => fn($i, $m) => $m->getExecutionTimeHuman() ?? '—',
                    'color' => fn($i, $m) => $m->getExecutionTime() > 1 ? 'yellow' : 'green',
                    'key' => 'executionTime',
                ],
                'Applied at' => [
                    'value' => fn($i, $m) => $m->whenApplied(),
                    'color' => fn($i, $m) => 'blue',
                    'key' => 'whenApplied',
                ],
            ], fn($column) => in_array($column['key'], $additionColumns)),
            'Status' => [
                'value' => fn($i, $m) => $m->getStatus(),
                'color' => fn($i, $m) => match ($m->getStatus()) {
                    PreMigrationInterface::STATUS_NEW, PreMigrationInterface::STATUS_APPLIED => 'green',
                    PreMigrationInterface::STATUS_REVERTED => 'yellow',
                    PreMigrationInterface::STATUS_FAILED => 'red',
                    default => 'white'
                },
            ],
        ];
        $table->setHeaders(array_keys($columns));
        $table->setStyle('box');

        foreach ($migrations as $index => $migration) {
            $rows = [];
            foreach ($columns as $value) {
                if (isset($value['color'])) {
                    $rows[] = '<fg=' . $value['color']($index, $migration) . '>[' . $value['value']($index, $migration) . ']</>';
                } else {
                    $rows[] = $value['value']($index, $migration);
                }
            }
            $table->addRow($rows);
        }
        return $table;
    }

    protected function showDebugOutput(array $migrations, OutputInterface $output): void
    {
        foreach ($migrations as $migration) {
            if ($debug = $migration->getDebug()) {
                $output->writeln('');
                $output->writeln("<options=bold>🔍 Debug output for {$migration->getName()}:</>");
                $output->writeln('<fg=gray>────────────────────────────────</>');
                foreach ($debug as $line) {
                    $coloredLine = preg_replace('/\b(done)\b/i', '<fg=green>$1</>', $line);
                    $output->writeln("<fg=gray>{$coloredLine}</>");
                }

                $output->writeln('<fg=gray>────────────────────────────────</>');
            }
        }
    }

    private function invalidAction(string $action, OutputInterface $output): int
    {
        $output->writeln("<error>Invalid action '{$action}'. Use one of: up, down, redo, make, new, history.</error>");
        return Command::FAILURE;
    }
}
