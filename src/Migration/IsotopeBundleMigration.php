<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\IsotopeExtensionBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class IsotopeBundleMigration implements MigrationInterface
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'Isotope Bundle Migration';
    }

    public function shouldRun(): bool
    {
        if ($this->connection->getSchemaManager()->tablesExist('tl_iso_product_data')) {
            $result = $this->connection->executeQuery('SELECT id FROM tl_iso_product_data');

            return $result->rowCount() > 0;
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $result = $this->connection->executeQuery('SELECT id,stock,downloadCount,relevance FROM tl_iso_product');

        if ($result->rowCount() > 0) {
            $stmt = $this->connection->prepare('SELECT id,stock,downloadCount,relevance FROM tl_iso_product_data WHERE pid=?');

            while ($product = (object) $result->fetchAssociative()) {
                $productData = (object) $stmt->executeQuery([$product->id])->fetchAssociative();

                $this->connection->executeQuery('UPDATE tl_iso_product_data SET stock=?,downloadCount=?,relevance=?', [
                    min($productData->stock, $product->stock),
                    max($productData->downloadCount, $product->downloadCount),
                    max($productData->relevance, $product->relevance),
                ]);
            }
        }

        return new MigrationResult(true, 'Successfully migrated isotope bundle data.');
    }
}
