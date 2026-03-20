<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class N8nBookingReadyDto
{
    public const EVENT_BOOKING_READY_FOR_DERIVATION = 'booking_ready_for_derivation';
    public const EVENT_QUOTE_RESTART_PROCESS = 'quote_restart_process';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $bookingId;

    #[Assert\NotBlank]
    #[Assert\Choice([
        self::EVENT_BOOKING_READY_FOR_DERIVATION,
        self::EVENT_QUOTE_RESTART_PROCESS,
    ])]
    public string $event;

    #[Assert\Uuid]
    public ?string $derivationRunId = null;

    #[Assert\Uuid]
    public ?string $correlationId = null;

    /**
     * @var array<int, string>
     */
    #[Assert\Type('array')]
    #[Assert\All(constraints: [new Assert\Uuid()])]
    public array $supplierIds = [];

    /**
     * @var array<int, string>
     */
    #[Assert\Type('array')]
    #[Assert\All(constraints: [new Assert\Uuid()])]
    public array $productIds = [];

    /**
     * @var array<int, string>
     */
    #[Assert\Type('array')]
    #[Assert\All(constraints: [new Assert\Uuid()])]
    public array $excludedProductIds = [];
}
