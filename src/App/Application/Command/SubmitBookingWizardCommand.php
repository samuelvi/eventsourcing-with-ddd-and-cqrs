<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\ValueObject\Country;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\PersonName;
use App\Domain\ValueObject\PositiveInt;
use Symfony\Component\Uid\Uuid;

final readonly class SubmitBookingWizardCommand
{
    private function __construct(
        public string $id,
        public int $pax,
        public float $budget,
        public string $clientName,
        public string $clientEmail,
        public string $country,
    ) {}

    public static function create(
        string $id,
        int $pax,
        float $budget,
        string $clientName,
        string $clientEmail,
        string $country
    ): self {
        return new self($id, $pax, $budget, $clientName, $clientEmail, $country);
    }

    public function bookingId(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function paxVO(): PositiveInt
    {
        return PositiveInt::fromInt($this->pax);
    }

    public function budgetVO(): NonNegativeAmount
    {
        return NonNegativeAmount::fromFloat($this->budget);
    }

    public function clientNameVO(): PersonName
    {
        return PersonName::fromString($this->clientName);
    }

    public function clientEmailVO(): Email
    {
        return Email::fromString($this->clientEmail);
    }

    public function countryVO(): Country
    {
        return Country::fromString($this->country);
    }
}
