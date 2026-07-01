<?php

declare(strict_types=1);

namespace App\Kernel\ImageConverter;

use Symfony\Component\HttpFoundation\File\File;

interface ImageResourceInterface
{
    public function getImagePath(): ?string;

    public function setImagePath(?string $imagePath): self;

    public function setImageFile(?File $imageFile = null): void;

    public function setImageName(?string $imageName): self;
}
