<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\User;

/**
 * @extends EventStoreRepositoryInterface<User>
 */
interface UserEventStoreRepositoryInterface extends EventStoreRepositoryInterface
{
}
