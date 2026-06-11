<?php

declare(strict_types=1);

namespace App\Identity\Ui;

use App\Identity\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @method User|null getUser()
 */
abstract class AbstractBaseController extends AbstractController
{
}
