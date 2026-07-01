<?php

declare(strict_types=1);

namespace App\Kernel\ImageConverter;

use Doctrine\Common\Collections\Collection;

interface ImageCollectionResourceInterface extends ImageResourceInterface
{
    /**
     * @return Collection<int, ImageResourceInterface>
     */
    public function getImages(): Collection;
}
