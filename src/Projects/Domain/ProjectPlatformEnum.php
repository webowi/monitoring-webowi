<?php

declare(strict_types=1);

namespace App\Projects\Domain;

enum ProjectPlatformEnum: string
{
    case SYMFONY = 'symfony';
    case VITE_REACT = 'vite_react';

    public function label(): string
    {
        return match ($this) {
            self::SYMFONY => 'Symfony',
            self::VITE_REACT => 'Vite + React',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SYMFONY => 'Monolog → HTTP ingest, minimalna konfiguracja.',
            self::VITE_REACT => 'Frontend (Vite) → wysyłka błędów/zdarzeń do ingest API.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
