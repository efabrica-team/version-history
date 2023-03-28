<?php

namespace Efabrica\VersionHistory\Repositories;

use Efabrica\NetteDatabaseRepository\Behaviors\TimestampsBehavior;
use Efabrica\NetteDatabaseRepository\Repositores\Repository;
use Efabrica\VersionHistory\Repositories\Models\Version;
use Efabrica\VersionHistory\Repositories\Selections\VersionSelection;

/**
 * @template-extends Repository<VersionSelection, Version>
 */
class VersionRepository extends Repository
{
    use TimestampsBehavior;

    protected string $tableName = 'versions';

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    protected function updatedAtField(): ?string
    {
        return null;
    }
}
