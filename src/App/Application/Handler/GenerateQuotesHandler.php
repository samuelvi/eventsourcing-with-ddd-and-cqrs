<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\GenerateQuotesCommand;
use App\Domain\Derivation\DerivationContext;
use App\Domain\Derivation\DerivationRuleEngine;
use App\Domain\Derivation\Facts\BookingFacts;
use App\Domain\Derivation\Facts\ProductFacts;
use App\Domain\Derivation\Facts\SupplierFacts;
use App\Domain\Event\QuoteRequested;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\ProductReadRepositoryInterface;
use App\Infrastructure\EventSourcing\StoredEvent;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use App\Domain\Shared\TypeAssert;
use App\Domain\ValueObject\NonNegativeAmount;
use App\Domain\ValueObject\UuidString;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GenerateQuotesHandler
{
    public function __construct(
        private BookingReadRepositoryInterface $bookingReadRepository,
        private ProductReadRepositoryInterface $productReadRepository,
        private DerivationRuleEngine $derivationRuleEngine,
        private MongoStore $mongoStore,
        private MessageBusInterface $eventBus,
    ) {}

    public function __invoke(GenerateQuotesCommand $command): void
    {
        $bookingId = $command->bookingIdVO();
        $bookingRow = $this->bookingReadRepository->findById($bookingId->toString());
        if (!$bookingRow) {
            return;
        }

        /** @var array<string, mixed> $bookingData */
        $bookingData = TypeAssert::array(json_decode(TypeAssert::string($bookingRow['data']), true));
        $budget = NonNegativeAmount::fromFloat(TypeAssert::float($bookingData['budget'] ?? 0.0));
        $bookingCountry = isset($bookingData['country'])
            ? TypeAssert::string($bookingData['country'])
            : (isset($bookingRow['country']) ? TypeAssert::string($bookingRow['country']) : null);

        $bookingFacts = new BookingFacts(
            bookingId: $bookingId->toString(),
            country: $bookingCountry,
            budget: $budget->toFloat(),
        );

        $matches = $this->productReadRepository->findByBudgetWithSupplierData(
            $bookingFacts->budget,
            $bookingFacts->country
        );

        if (empty($matches)) {
            return;
        }

        $occurredOn = new \DateTimeImmutable();

        foreach ($matches as $product) {
            $supplierFacts = new SupplierFacts(
                supplierId: TypeAssert::string($product['supplier_id']),
                country: isset($product['supplier_country']) ? TypeAssert::string($product['supplier_country']) : null,
                isActive: (bool) ($product['supplier_is_active'] ?? false),
            );

            $productFacts = new ProductFacts(
                productId: TypeAssert::string($product['id']),
                price: TypeAssert::float($product['price']),
            );

            $context = new DerivationContext(
                booking: $bookingFacts,
                supplier: $supplierFacts,
                product: $productFacts,
            );

            if (!$this->derivationRuleEngine->allows($context)) {
                continue;
            }

            $quoteId = Uuid::v7();

            // 1. Create the Domain Event
            $supplierId = UuidString::fromString(TypeAssert::string($product['supplier_id']));
            $productId = UuidString::fromString(TypeAssert::string($product['id']));
            $requestedPrice = NonNegativeAmount::fromFloat((float) $product['price']);

            $quoteEvent = new QuoteRequested(
                quoteId: $quoteId->toRfc4122(),
                bookingId: $bookingId->toString(),
                supplierId: $supplierId->toString(),
                productId: $productId->toString(),
                requestedPrice: $requestedPrice->toFloat(),
                occurredOn: $occurredOn
            );

            // 2. Persist to Event Store (Mongo)
            $storedEvent = StoredEvent::commit(
                aggregateId: $quoteId,
                eventType: QuoteRequested::class,
                payload: [
                    'quoteId' => $quoteId->toRfc4122(),
                    'bookingId' => $bookingId->toString(),
                    'supplierId' => $supplierId->toString(),
                    'productId' => $productId->toString(),
                    'requestedPrice' => $requestedPrice->toFloat(),
                    'occurredOn' => $occurredOn->format(\DateTimeInterface::ATOM)
                ],
                occurredOn: $occurredOn
            );

            $this->mongoStore->saveEvent($storedEvent);

            // 3. Dispatch for Projections
            $this->eventBus->dispatch($quoteEvent);
        }
    }
}
