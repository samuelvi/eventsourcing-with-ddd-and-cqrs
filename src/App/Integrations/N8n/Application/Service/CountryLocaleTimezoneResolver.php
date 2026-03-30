<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service;

final readonly class CountryLocaleTimezoneResolver
{
    /**
     * @var array<string, array{locale: string, timezone: string}>
     */
    private const MAP = [
        'ES' => ['locale' => 'es-ES', 'timezone' => 'Europe/Madrid'],
        'MX' => ['locale' => 'es-MX', 'timezone' => 'America/Mexico_City'],
        'AR' => ['locale' => 'es-AR', 'timezone' => 'America/Argentina/Buenos_Aires'],
        'CO' => ['locale' => 'es-CO', 'timezone' => 'America/Bogota'],
        'BR' => ['locale' => 'pt-BR', 'timezone' => 'America/Sao_Paulo'],
        'GB' => ['locale' => 'en-GB', 'timezone' => 'Europe/London'],
        'FR' => ['locale' => 'fr-FR', 'timezone' => 'Europe/Paris'],
        'DE' => ['locale' => 'de-DE', 'timezone' => 'Europe/Berlin'],
    ];

    private const FALLBACK_LOCALE = 'en-US';
    private const FALLBACK_TIMEZONE = 'Etc/UTC';

    /**
     * @return array{country: string, locale: string, timezone: string, utcOffsetMinutes: int}
     */
    public function resolve(string $country): array
    {
        $normalizedCountry = strtoupper(trim($country));
        $resolved = self::MAP[$normalizedCountry] ?? null;

        $locale = $resolved['locale'] ?? self::FALLBACK_LOCALE;
        $timezone = $resolved['timezone'] ?? self::FALLBACK_TIMEZONE;
        $countryCode = $normalizedCountry !== '' ? $normalizedCountry : 'US';

        return [
            'country' => $countryCode,
            'locale' => $locale,
            'timezone' => $timezone,
            'utcOffsetMinutes' => $this->offsetMinutesForTimezone($timezone),
        ];
    }

    private function offsetMinutesForTimezone(string $timezone): int
    {
        try {
            $date = new \DateTimeImmutable('now', new \DateTimeZone($timezone));

            return (int) round($date->getOffset() / 60);
        } catch (\Throwable) {
            return 0;
        }
    }
}
