<?php

declare(strict_types=1);

namespace App\Kernel\ImageConverter;

use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @codeCoverageIgnore
 *
 * @infection-ignore-all
 */
readonly class ImageConverter implements ImageConverterInterface
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
    ) {
    }

    public function provideImageResourcePath(
        ImageResourceInterface $imageResource,
    ): ?string {
        return $this->uploaderHelper->asset($imageResource, self::IMAGE_FILE);
    }

    public function convertImageToWebp(ImageResourceInterface $imageResource, int $quality = 100): void
    {
        if ($imageResource instanceof ImageCollectionResourceInterface) {
            foreach ($imageResource->getImages() as $image) {
                $this->processConvertImageToWebp($image, $quality);
            }

            $this->processConvertImageToWebp($imageResource, $quality);

            return;
        }

        $this->processConvertImageToWebp($imageResource, $quality);
    }

    private function processConvertImageToWebp(ImageResourceInterface $imageResource, int $quality = 100): void
    {
        $relativePath = $this->provideImageResourcePath($imageResource);

        $absolutePath = sprintf('%s/public%s', $this->getProjectDir(), $relativePath);

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            return;
        }

        $fileType = exif_imagetype($absolutePath);

        try {
            switch ($fileType) {
                case IMAGETYPE_JPEG:
                    $image = \safe\imagecreatefromjpeg($absolutePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = \safe\imagecreatefrompng($absolutePath);
                    \imagepalettetotruecolor($image);
                    \imagealphablending($image, true);
                    \imagesavealpha($image, true);
                    break;
                case IMAGETYPE_WEBP:
                default:
                    $imageResource->setImagePath($relativePath);
                    return;
            }

            $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $absolutePath);

            if (!\imagewebp($image, $webpPath, $quality)) {
                throw new \RuntimeException(sprintf('Failed to convert image to WebP: %s', $absolutePath));
            }

            \safe\imagedestroy($image);
            $imageResource->setImagePath(preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $this->provideImageResourcePath($imageResource)));
            $imageResource->setImageFile(new File($webpPath));
            $imageResource->setImageName(basename($webpPath));

            if (!\unlink($absolutePath)) {
                throw new \RuntimeException(sprintf('Failed to delete original image: %s', $absolutePath));
            }

        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Failed to convert image to WebP: %s', $e->getMessage()));
        }
    }

    private function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }
}
