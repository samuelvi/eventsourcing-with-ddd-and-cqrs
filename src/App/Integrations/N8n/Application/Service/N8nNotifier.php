<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service;

use App\Application\Service\DerivationRunContext;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class N8nNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CountryLocaleTimezoneResolver $countryLocaleTimezoneResolver,
        private ?LoggerInterface $n8nLogger = null,
    ) {}

    public function notifyBookingReady(DerivationRunContext $context, string $country): void
    {
        $webhookUrl = getenv('N8N_WEBHOOK_BOOKING_URL');
        if ($webhookUrl === false || $webhookUrl === '') {
            $this->n8nLogger?->warning('N8n booking webhook url is not configured');

            return;
        }

        $localeTimezone = $this->countryLocaleTimezoneResolver->resolve($country);

        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'json' => [
                    'bookingId' => $context->bookingId,
                    'event' => 'booking_ready_for_derivation',
                    'correlationId' => $context->correlationId,
                    'occurredOn' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'country' => $localeTimezone['country'],
                    'locale' => $localeTimezone['locale'],
                    'timezone' => $localeTimezone['timezone'],
                    'timeZone' => $localeTimezone['timezone'],
                    'utcOffsetMinutes' => $localeTimezone['utcOffsetMinutes'],
                ],
                'timeout' => 10,
            ]);

            $this->n8nLogger?->info('N8n notification sent', [
                'bookingId' => $context->bookingId,
                'correlationId' => $context->correlationId,
                'country' => $localeTimezone['country'],
                'locale' => $localeTimezone['locale'],
                'timezone' => $localeTimezone['timezone'],
                'status' => $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            $this->n8nLogger?->error('N8n notification failed', [
                'bookingId' => $context->bookingId,
                'correlationId' => $context->correlationId,
                'country' => $localeTimezone['country'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
