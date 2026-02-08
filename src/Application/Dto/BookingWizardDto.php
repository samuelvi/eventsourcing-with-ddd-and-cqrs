<?php

declare(strict_types=1);

namespace App\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class BookingWizardDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $bookingId;

    #[Assert\Positive]
    public int $pax;

    #[Assert\Positive]
    public float $budget;

    #[Assert\NotBlank]
    public string $clientName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $clientEmail;
}