<?php

namespace Efabrica\VersionHistory\Repositories;

use Efabrica\NetteDatabaseRepository\Behavior\BehaviorInjector;
use Efabrica\NetteDatabaseRepository\Behavior\DateBehavior;
use Efabrica\NetteDatabaseRepository\Behaviors\TimestampsBehavior;
use Efabrica\NetteDatabaseRepository\Repositores\Repository;
use Efabrica\NetteDatabaseRepository\Selections\Factories\SelectionFactoryInterface;
use Efabrica\VersionHistory\Repositories\Models\Version;
use Efabrica\VersionHistory\Repositories\Selections\VersionSelection;
use Nette\Database\Explorer;

/**
 * @template-extends Repository<VersionSelection, Version>
 */
class VersionRepository extends Repository
{
    public function __construct(Explorer $db, SelectionFactoryInterface $selectionFactory, BehaviorInjector $behaviorInjector)
    {
        parent::__construct($db, $selectionFactory, $behaviorInjector);
        $this->getBehaviors()->add(new DateBehavior('created_at', null));
    }

    protected string $tableName = 'versions';

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }
}
