<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class N8nSupplierResponseProcessDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $bookingId;

    #[Assert\Uuid]
    public ?string $derivationRunId = null;

    #[Assert\Uuid]
    public ?string $correlationId = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $quoteId;

    #[Assert\NotBlank]
    public string $event;

    #[Assert\Type('bool')]
    public ?bool $supplierResponded = null;

    #[Assert\PositiveOrZero]
    public ?int $elapsedMinutes = null;

    #[Assert\NotBlank]
    #[Assert\Url]
    public string $callbackUrl;
}
