<?php

namespace Efabrica\VersionHistory\Behaviors;

use DateInterval;
use DateTimeInterface;
use Efabrica\Nette\DI\Extension\RequestId\Provider;
use Efabrica\NetteDatabaseRepository\Behaviors\RepositoryBehavior;
use Efabrica\NetteDatabaseRepository\Models\ActiveRow;
use Efabrica\VersionHistory\Enums\VersionFlag;
use Efabrica\VersionHistory\Repositories\Models\Version;
use Efabrica\VersionHistory\Repositories\VersionRepository;
use Nette\Security\User;
use Nette\Utils\DateTime;

trait VersionBehavior
{
    use RepositoryBehavior;

    protected function getVersionColumnsToIgnore(): array
    {
        return [];
    }

    protected function getVersionColumnsToForce(): array
    {
        return [];
    }

    protected function getRelatedTables(ActiveRow $record): array
    {
        return [];
    }

    final public function afterInsertLogChanges(ActiveRow $record, array $data, VersionRepository $versionRepository, User $user, Provider $provider): void
    {
        foreach ($this->getVersionColumnsToIgnore() as $ignored) {
            if (isset($data[$ignored])) {
                unset($data[$ignored]);
            }
        }

        $recordToLink = $this->processLinkedEntries($record, $versionRepository, $user, $provider);

        $versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $record->getPrimary(),
            'foreign_table' => $this->getTableName(),
            'user_id' => $user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode($data),
            'flag' => VersionFlag::CREATE,
            'transaction_id' => $provider->getRequestId(),
            'linked_id' => $recordToLink !== null ? $recordToLink->id : null,
        ]);
    }

    final public function afterUpdateLogChanges(ActiveRow $oldRecord, ActiveRow $newRecord, array $data, VersionRepository $versionRepository, User $user, Provider $provider): void
    {
        $diff = $this->makeDiff($oldRecord, $data);

        foreach ($this->getVersionColumnsToForce() as $column) {
            if (!isset($diff['old'][$column]) && !isset($diff['new'][$column])) {
                $diff['old'][$column] = $oldRecord->$column;
                $diff['new'][$column] = $newRecord->$column;
            }
        }

        $recordToLink = $this->processLinkedEntries($newRecord, $versionRepository, $user, $provider);

        $versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $newRecord->getPrimary(),
            'foreign_table' => $this->getTableName(),
            'user_id' => $user->getId(),
            'old_data' => json_encode($diff['old']),
            'new_data' => json_encode($diff['new']),
            'flag' => VersionFlag::UPDATE,
            'transaction_id' => $provider->getRequestId(),
            'linked_id' => $recordToLink !== null ? $recordToLink->id : null,
        ]);
    }

    final public function afterSoftDeleteVersionTrait(ActiveRow $record, VersionRepository $versionRepository, User $user, Provider $provider): void
    {
        $recordToLink = $this->processLinkedEntries($record, $versionRepository, $user, $provider);

        $versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $record->getPrimary(),
            'foreign_table' => $this->getTableName(),
            'user_id' => $user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'flag' => VersionFlag::SOFT_DELETE,
            'transaction_id' => $provider->getRequestId(),
            'linked_id' => $recordToLink !== null ? $recordToLink->id : null,
        ]);
    }

    final public function afterRestoreDeleteVersionTrait(ActiveRow $record, VersionRepository $versionRepository, User $user, Provider $provider): void
    {
        $recordToLink = $this->processLinkedEntries($record, $versionRepository, $user, $provider);

        $versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $record->getPrimary(),
            'foreign_table' => $this->getTableName(),
            'user_id' => $user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'flag' => VersionFlag::RESTORE,
            'transaction_id' => $provider->getRequestId(),
            'linked_id' => $recordToLink !== null ? $recordToLink->id : null,
        ]);
    }

    final public function afterDeleteVersionTrait(ActiveRow $record, VersionRepository $versionRepository, User $user, Provider $provider): void
    {
        $recordToLink = $this->processLinkedEntries($record, $versionRepository, $user, $provider);

        $versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $record->getPrimary(),
            'foreign_table' => $this->getTableName(),
            'user_id' => $user->getId(),
            'old_data' => json_encode($record->getData()),
            'new_data' => json_encode(array_fill_keys(array_keys($record->getData()), '')),
            'flag' => VersionFlag::DELETE,
            'transaction_id' => $provider->getRequestId(),
            'linked_id' => $recordToLink !== null ? $recordToLink->id : null,
        ]);
    }

    private function processLinkedEntries(ActiveRow $record, VersionRepository $versionRepository, User $user, Provider $provider): ?Version
    {
        if ($provider->getRequestId() === null) {
            return null;
        }

        $recordToLink = null;

        $relatedTables = $this->getRelatedTables($record);
        foreach ($relatedTables as $table => $foreignId) {
            $recordToLink = $this->processLinkedEntry($versionRepository, $user, $provider, $foreignId, $table, $recordToLink);
        }

        return $recordToLink;
    }

    private function processLinkedEntry(VersionRepository $versionRepository, User $user, Provider $provider, $foreignId, string $table, ?Version $recordToLink = null): Version
    {
        $existing = $versionRepository->query()->where([
            'transaction_id' => $provider->getRequestId(),
            'foreign_id' => $foreignId,
            'foreign_table' => $table,
        ])->limit(1)->fetch();

        if ($existing) {
            return $existing;
        }

        return $versionRepository->insert([
            'created_at' => new DateTime('now'),
            'foreign_id' => $foreignId,
            'foreign_table' => $table,
            'user_id' => $user->getId(),
            'old_data' => json_encode([]),
            'new_data' => json_encode([]),
            'flag' => VersionFlag::UPDATE,
            'transaction_id' => $provider->getRequestId(),
            'linked_id' => $recordToLink !== null ? $recordToLink->id : null,
        ]);
    }

    private function makeDiff(ActiveRow $record, array $data): array
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

            if (!isset($record->$column)) {
                foreach ($operationMarks as $operationMark) {
                    if (strpos($column, $operationMark)) {
                        $column = rtrim($column, $operationMark);
                        $value = $operationMark . $value;
                    }
                }
            }

            if ($record->$column !== $value) {
                $columnsToIgnore = $this->getVersionColumnsToIgnore();
                if (!in_array($column, $columnsToIgnore, true)) {
                    $result['old'][$column] = $record->$column === null ? null : $this->convertToString($record->$column);
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
