<?php

declare(strict_types=1);

namespace App\Application\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Infrastructure\ApiPlatform\Processor\BookingWizardProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BookingWizard',
    operations: [
        new Post(
            uriTemplate: '/booking-wizard',
            processor: BookingWizardProcessor::class,
            input: self::class,
            output: false
        )
    ]
)]
final class BookingWizardDto
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;

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
