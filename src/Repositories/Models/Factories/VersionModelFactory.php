<?php

namespace Efabrica\VersionHistory\Repositories\Models\Factories;

use Efabrica\NetteDatabaseRepository\Models\Factories\ModelFactoryInterface;
use Efabrica\VersionHistory\Repositories\Models\Version;
use Nette\Database\Table\Selection;

interface VersionModelFactory extends ModelFactoryInterface
{
    public function create(array $data, Selection $table): Version;
}
