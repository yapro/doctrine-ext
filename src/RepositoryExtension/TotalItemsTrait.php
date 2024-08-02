<?php

declare(strict_types=1);

namespace YaPro\DoctrineExt\RepositoryExtension;

use YaPro\DoctrineExt\Wrapping\DBALConnectionWrapper;

trait TotalItemsTrait
{
    private function getTotalItems(): int
    {
        return filter_var(
            $this->getEntityManager()->getConnection()->fetchColumn(DBALConnectionWrapper::SELECT_FOUND_ROWS),
            FILTER_VALIDATE_INT
        );
    }
}
