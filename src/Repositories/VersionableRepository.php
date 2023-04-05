<?php

namespace Efabrica\VersionHistory\Repositories;

use Efabrica\NetteDatabaseRepository\Models\ActiveRow;

interface VersionableRepository
{
    public function getRelatedTables(ActiveRow $row): array;
}
