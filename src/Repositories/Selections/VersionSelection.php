<?php

namespace Efabrica\VersionHistory\Repositories\Selections;

use Efabrica\NetteDatabaseRepository\Models\ActiveRow;
use Efabrica\NetteDatabaseRepository\Selections\Selection;
use Efabrica\VersionHistory\Repositories\Models\Version;
use Iterator;

/**
 * @template-extends Selection<Version>
 * @template-implements Iterator<int, Version>
 *
 * @method bool|int|Version insert(iterable $data)
 * @method Version|null get(mixed $key)
 * @method Version|null fetch()
 * @method Version[] fetchAll()
 */
class VersionSelection extends Selection
{
    /**
     * @return static
     */
    public function forRecord(ActiveRow $record): self
    {
        return $this->where('foreign_id', $record->getPrimary())->where('foreign_table', $record->getTableName());
    }
}
