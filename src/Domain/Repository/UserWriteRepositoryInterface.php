<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\UserEntity;

interface UserWriteRepositoryInterface
{
    public function save(UserEntity $user): void;
    public function remove(UserEntity $user): void;
}
