<?php

namespace Efabrica\VersionHistory\Repositories\Models;

use Efabrica\NetteDatabaseRepository\Casts\JsonArrayCast;
use Efabrica\NetteDatabaseRepository\Models\ActiveRow;
use Nette\Utils\DateTime;

/**
 * @property int $id
 * @property ?int $linked_id
 * @property ?string $transaction_id
 * @property string $foreign_id
 * @property string $foreign_table
 * @property ?int $user_id
 * @property ?array $old_data
 * @property ?array $new_data
 * @property ?string $flag
 * @property DateTime $created_at
 */
class Version extends ActiveRow
{
    public function getCasts(): array
    {
        return [
            'old_data' => JsonArrayCast::class,
            'new_data' => JsonArrayCast::class,
        ];
    }
}
