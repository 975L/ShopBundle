<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'c975l:shop:migrate-category-many-to-many',
    description: 'Migrates Product-ProductCategory relationship from ManyToOne to ManyToMany',
)]
class MigrateCategoryManyToManyCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the migration in dry-run mode')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command migrates the Product-ProductCategory relationship from ManyToOne to ManyToMany.

This migration:
  * Creates a new join table shop_product_category_link
  * Migrates existing category_id data to the new table
  * Removes the old category_id column from shop_product table

<info>php %command.full_name%</info>

You can check what SQL will be executed without applying changes:
<info>php %command.full_name% --dry-run</info>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Migrating Product-ProductCategory to ManyToMany');

        if ($dryRun) {
            $io->note('Running in DRY-RUN mode - no changes will be applied');
        }

        try {
            // Check if migration is needed
            if (!$this->isMigrationNeeded()) {
                $io->success('Migration already applied or not needed. The shop_product_category_link table already exists.');
                return Command::SUCCESS;
            }

            $io->section('Step 1: Creating join table');
            $this->createJoinTable($io, $dryRun);

            $io->section('Step 2: Migrating existing data');
            $migratedCount = $this->migrateData($io, $dryRun);
            $io->text(sprintf('Migrated %d product-category associations', $migratedCount));

            $io->section('Step 3: Removing old category_id column');
            $this->removeOldColumn($io, $dryRun);

            if ($dryRun) {
                $io->success('DRY-RUN completed successfully. Run without --dry-run to apply changes.');
            } else {
                $io->success('Migration completed successfully!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function isMigrationNeeded(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // If join table already exists, migration is not needed
        return !in_array('shop_product_category_link', $tables, true);
    }

    private function createJoinTable(SymfonyStyle $io, bool $dryRun): void
    {
        $sql = <<<'SQL'
CREATE TABLE shop_product_category_link (
    product_id INT NOT NULL,
    product_category_id INT NOT NULL,
    PRIMARY KEY(product_id, product_category_id),
    INDEX IDX_shop_product_category_link_product (product_id),
    INDEX IDX_shop_product_category_link_category (product_category_id),
    CONSTRAINT FK_shop_product_category_link_product
        FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE,
    CONSTRAINT FK_shop_product_category_link_category
        FOREIGN KEY (product_category_id) REFERENCES shop_product_category (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL;

        if ($dryRun) {
            $io->text('<comment>Would execute:</comment>');
            $io->text($sql);
        } else {
            $this->connection->executeStatement($sql);
            $io->text('<info>✓</info> Join table created');
        }
    }

    private function migrateData(SymfonyStyle $io, bool $dryRun): int
    {
        // First, check if category_id column still exists
        $schemaManager = $this->connection->createSchemaManager();
        $productColumns = $schemaManager->listTableColumns('shop_product');

        if (!isset($productColumns['category_id'])) {
            $io->text('<comment>!</comment> No category_id column found - skipping data migration');
            return 0;
        }

        $sql = <<<'SQL'
INSERT INTO shop_product_category_link (product_id, product_category_id)
SELECT id, category_id
FROM shop_product
WHERE category_id IS NOT NULL
SQL;

        if ($dryRun) {
            $io->text('<comment>Would execute:</comment>');
            $io->text($sql);

            // Count how many rows would be migrated
            $count = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM shop_product WHERE category_id IS NOT NULL'
            );
            return (int) $count;
        } else {
            $result = $this->connection->executeStatement($sql);
            $io->text('<info>✓</info> Data migrated');
            return $result;
        }
    }

    private function removeOldColumn(SymfonyStyle $io, bool $dryRun): void
    {
        // Check if column still exists
        $schemaManager = $this->connection->createSchemaManager();
        $productColumns = $schemaManager->listTableColumns('shop_product');

        if (!isset($productColumns['category_id'])) {
            $io->text('<comment>!</comment> Column category_id already removed');
            return;
        }

        // Find the foreign key constraint name dynamically
        $foreignKeys = $schemaManager->listTableForeignKeys('shop_product');
        $foreignKeyName = null;

        foreach ($foreignKeys as $foreignKey) {
            $localColumns = $foreignKey->getLocalColumns();
            if (in_array('category_id', $localColumns, true)) {
                $foreignKeyName = $foreignKey->getName();
                break;
            }
        }

        if ($dryRun) {
            $io->text('<comment>Would execute:</comment>');
            if ($foreignKeyName) {
                $io->text("ALTER TABLE shop_product DROP FOREIGN KEY {$foreignKeyName}");
            }
            $io->text('ALTER TABLE shop_product DROP category_id');
        } else {
            // Drop foreign key constraint if found
            if ($foreignKeyName) {
                try {
                    $this->connection->executeStatement(
                        "ALTER TABLE shop_product DROP FOREIGN KEY {$foreignKeyName}"
                    );
                    $io->text("<info>✓</info> Foreign key constraint '{$foreignKeyName}' removed");
                } catch (\Exception $e) {
                    $io->text('<comment>!</comment> Could not remove foreign key: ' . $e->getMessage());
                }
            } else {
                $io->text('<comment>!</comment> No foreign key constraint found on category_id');
            }

            // Drop the column (this will automatically drop the associated index)
            try {
                $this->connection->executeStatement('ALTER TABLE shop_product DROP category_id');
                $io->text('<info>✓</info> Column category_id removed');
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to remove category_id column: ' . $e->getMessage());
            }
        }
    }
}
