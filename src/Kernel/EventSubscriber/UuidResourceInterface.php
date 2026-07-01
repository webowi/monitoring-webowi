<?php

declare(strict_types=1);

namespace App\Kernel\EventSubscriber;

use Symfony\Component\Uid\Uuid;

interface UuidResourceInterface
{
    public function getUuid(): ?Uuid;

    public function setUuid(?Uuid $uuid): self;
}
