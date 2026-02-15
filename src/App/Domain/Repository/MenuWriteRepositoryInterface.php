<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\MenuEntity;

interface MenuWriteRepositoryInterface
{
    public function save(MenuEntity $menu): void;
}
