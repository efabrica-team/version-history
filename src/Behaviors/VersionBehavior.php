<?php

namespace Efabrica\VersionHistory\Behaviors;

use DateInterval;
use DateTimeInterface;
use Efabrica\Nette\DI\Extension\RequestId\Provider;
use Efabrica\NetteDatabaseRepository\Behavior\Behavior;
use Efabrica\NetteDatabaseRepository\Behavior\BehaviorWithSoftDelete;
use Efabrica\NetteDatabaseRepository\Models\ActiveRow;
use Efabrica\NetteDatabaseRepository\Repositores\Repository;
use Efabrica\VersionHistory\Enums\VersionFlag;
use Efabrica\VersionHistory\Repositories\Models\Version;
use Efabrica\VersionHistory\Repositories\VersionableRepository;
use Efabrica\VersionHistory\Repositories\VersionRepository;
use Nette\Security\User;
use Nette\Utils\DateTime;

class VersionBehavior extends Behavior implements BehaviorWithSoftDelete
{
    /** @inject */
    public Provider $provider;

    /** @inject */
    public User $user;

    /** @inject */
    public VersionRepository $versionRepository;
    private array $versionColumnsToIgnore = [];
    private array $versionColumnsToForce = [];

    /**
     * @var VersionableRepository&Repository
     */
    private Repository $repository;

    /**
     * @param VersionableRepository&Repository $repository
     */
    public function __construct(VersionableRepository $repository)
    {
        $this->repository = $repository;
    }

    public function afterInsert(ActiveRow $row, iterable $data): void
    {
        foreach ($this->versionColumnsToIgnore as $ignored) {
            if (isset($data[$ignored])) {
                unset($data[$ignored]);
            }
        }

        $rowToLink = $this->processLinkedEntries($row);

        $this->versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $row->getPrimary(),
            'foreign_table' => $this->repository->getTableName(),
            'user_id' => $this->user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode($data),
            'flag' => VersionFlag::CREATE,
            'transaction_id' => $this->provider->getRequestId(),
            'linked_id' => $rowToLink !== null ? $rowToLink->id : null,
        ]);
    }

    final public function afterUpdate(ActiveRow $oldRecord, ActiveRow $newRecord, iterable $data): void
    {
        $diff = $this->makeDiff($oldRecord, $data);

        foreach ($this->versionColumnsToForce as $column) {
            if (!isset($diff['old'][$column]) && !isset($diff['new'][$column])) {
                $diff['old'][$column] = $oldRecord->$column;
                $diff['new'][$column] = $newRecord->$column;
            }
        }

        $rowToLink = $this->processLinkedEntries($newRecord);

        $this->versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $newRecord->getPrimary(),
            'foreign_table' => $this->repository->getTableName(),
            'user_id' => $this->user->getId(),
            'old_data' => json_encode($diff['old']),
            'new_data' => json_encode($diff['new']),
            'flag' => VersionFlag::UPDATE,
            'transaction_id' => $this->provider->getRequestId(),
            'linked_id' => $rowToLink !== null ? $rowToLink->id : null,
        ]);
    }

    public function beforeSoftDelete(ActiveRow $row): void
    {
    }

    final public function afterSoftDelete(ActiveRow $row): void
    {
        $rowToLink = $this->processLinkedEntries($row);

        $this->versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $row->getPrimary(),
            'foreign_table' => $this->repository->getTableName(),
            'user_id' => $this->user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'flag' => VersionFlag::SOFT_DELETE,
            'transaction_id' => $this->provider->getRequestId(),
            'linked_id' => $rowToLink !== null ? $rowToLink->id : null,
        ]);
    }

    public function beforeRestore(ActiveRow $row): void
    {
    }

    final public function afterRestore(ActiveRow $row): void
    {
        $rowToLink = $this->processLinkedEntries($row);

        $this->versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $row->getPrimary(),
            'foreign_table' => $this->repository->getTableName(),
            'user_id' => $this->user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'flag' => VersionFlag::RESTORE,
            'transaction_id' => $this->provider->getRequestId(),
            'linked_id' => $rowToLink !== null ? $rowToLink->id : null,
        ]);
    }

    final public function afterDelete(ActiveRow $row): void
    {
        $rowToLink = $this->processLinkedEntries($row);

        $this->versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $row->getPrimary(),
            'foreign_table' => $this->repository->getTableName(),
            'user_id' => $this->user->getId(),
            'old_data' => json_encode($row->getData()),
            'new_data' => json_encode(array_fill_keys(array_keys($row->getData()), '')),
            'flag' => VersionFlag::DELETE,
            'transaction_id' => $this->provider->getRequestId(),
            'linked_id' => $rowToLink !== null ? $rowToLink->id : null,
        ]);
    }

    private function processLinkedEntries(ActiveRow $row): ?Version
    {
        if ($this->provider->getRequestId() === null) {
            return null;
        }

        $rowToLink = null;

        $relatedTables = $this->repository->getRelatedTables($row);
        foreach ($relatedTables as $table => $foreignId) {
            $rowToLink = $this->processLinkedEntry($foreignId, $table, $rowToLink);
        }

        return $rowToLink;
    }

    private function processLinkedEntry($foreignId, string $table, ?Version $rowToLink = null): Version
    {
        $existing = $this->versionRepository->getSelection()->where([
            'transaction_id' => $this->provider->getRequestId(),
            'foreign_id' => $foreignId,
            'foreign_table' => $table,
        ])->limit(1)->fetch();

        if ($existing) {
            return $existing;
        }

        return $this->versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $foreignId,
            'foreign_table' => $table,
            'user_id' => $this->user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'flag' => VersionFlag::UPDATE,
            'transaction_id' => $this->provider->getRequestId(),
            'linked_id' => $rowToLink !== null ? $rowToLink->id : null,
        ]);
    }

    private function makeDiff(ActiveRow $row, iterable $data): array
    {
        $result = [
            'old' => [],
            'new' => [],
        ];

        $operationMarks = ['+=', '-='];
        foreach ($data as $column => $value) {
            if ($value instanceof ActiveRow) {
                $value = (string)$value;
            }

            if (!isset($row->$column)) {
                foreach ($operationMarks as $operationMark) {
                    if (strpos($column, $operationMark)) {
                        $column = rtrim($column, $operationMark);
                        $value = $operationMark . $value;
                    }
                }
            }

            if ($row->$column !== $value) {
                if (!in_array($column, $this->versionColumnsToIgnore, true)) {
                    $result['old'][$column] = $row->$column === null ? null : $this->convertToString($row->$column);
                    $result['new'][$column] = $value === null ? null : $this->convertToString($value);
                }
            }
        }

        return $result;
    }

    private function convertToString($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return (string)DateTime::from($value);
        }

        if ($value instanceof DateInterval) {
            return $value->format('%r%h:%I:%S');
        }

        return (string)$value;
    }
}
