<?php

declare(strict_types=1);

namespace App\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class N8nBookingReadyDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $bookingId;

    #[Assert\NotBlank]
    #[Assert\Choice(['booking_ready_for_derivation'])]
    public string $event;

    #[Assert\Uuid]
    public ?string $correlationId = null;
}
