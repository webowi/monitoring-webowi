<?php

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
}
