<?php

declare(strict_types=1);

namespace App\Kernel\ImageConverter;

interface ImageConverterInterface
{
    public const IMAGE_FILE = 'imageFile';

    public function provideImageResourcePath(ImageResourceInterface $imageResource): ?string;

    public function convertImageToWebp(ImageResourceInterface $imageResource, int $quality = 100): void;
}
