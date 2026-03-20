<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Dto;

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
    public ?string $derivationRunId = null;

    #[Assert\Uuid]
    public ?string $correlationId = null;
}
