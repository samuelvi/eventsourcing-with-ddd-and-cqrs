<?php

declare(strict_types=1);

namespace App\Application\Handler\Quotes;

use App\Application\Command\Quotes\GenerateQuotesCommand;
use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Repository\ProductReadRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateQuotesHandler
{
    public function __construct(
        private DerivationContextBuilder $contextBuilder,
        private ProductReadRepositoryInterface $productReadRepository,
        private QuoteCandidatesFilter $quoteCandidatesFilter,
        private QuoteRequestedPublisher $quoteRequestedPublisher,
    ) {}

    public function __invoke(GenerateQuotesCommand $command): void
    {
        $bookingId = $command->bookingIdVO()->toString();
        $bookingFacts = $this->contextBuilder->buildForBooking($bookingId);

        if ($bookingFacts === null) {
            return;
        }

        $candidates = $this->findCandidates($bookingFacts);
        if ($candidates === []) {
            return;
        }

        $eligibleCandidates = $this->quoteCandidatesFilter->eligibleForBooking($bookingFacts, $candidates);
        if ($eligibleCandidates === []) {
            return;
        }

        $this->publishQuotes($bookingId, $eligibleCandidates, $command->correlationId);
    }

    /**
     * @return array<int, QuoteCandidate>
     */
    private function findCandidates(BookingFacts $bookingFacts): array
    {
        $candidatesData = $this->productReadRepository->findCandidatesFirstFilter(
            $bookingFacts->budget,
            $bookingFacts->country
        );

        return array_map(
            static fn (array $row): QuoteCandidate => QuoteCandidate::fromRow($row),
            $candidatesData
        );
    }

    /**
     * @param array<int, QuoteCandidate> $candidates
     */
    private function publishQuotes(string $bookingId, array $candidates, ?string $correlationId = null): void
    {
        $occurredOn = new DateTimeImmutable();

        foreach ($candidates as $candidate) {
            $this->quoteRequestedPublisher->publish($bookingId, $candidate, $occurredOn, $correlationId);
        }
    }
}
