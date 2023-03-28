<?php

namespace Efabrica\VersionHistory\Repositories\Selections\Factories;

use Efabrica\NetteDatabaseRepository\Selections\Factories\SelectionFactoryInterface;
use Efabrica\VersionHistory\Repositories\Selections\VersionSelection;

interface VersionSelectionFactory extends SelectionFactoryInterface
{
    public function create(string $tableName): VersionSelection;
}
