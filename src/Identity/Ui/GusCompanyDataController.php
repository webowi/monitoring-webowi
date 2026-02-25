<?php

declare(strict_types=1);

namespace App\Identity\Ui;

use App\Identity\Application\CompanyDataProviderInterface;
use App\Identity\Application\Exception\CompanyNotFoundException;
use App\Identity\Ui\Exception\CannotGetGusDataException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED')]
class GusCompanyDataController extends AbstractController
{
    public function __construct(
        private readonly CompanyDataProviderInterface $companyDataProvider,
        #[Autowire(service: 'limiter.gus_api')]
        private readonly RateLimiterFactory $gusApiLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/dashboard/company/fetch-gus-data', name: 'app_company_gus_data', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload]
        GusDataInput $input,
        Request                           $request,
    ): JsonResponse {
        $limiter = $this->gusApiLimiter->create($request->getClientIp() ?? 'unknown');
        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: $this->translator->trans('exception.gusApiTooManyRequests'));
        }

        try {
            $companyData = $this->companyDataProvider->getByTin($input->tin);

            return new JsonResponse([
                'tin'      => $companyData->tin,
                'name'     => $companyData->name,
                'regon'    => $companyData->regon,
                'province' => $companyData->province,
                'street'   => $companyData->street,
                'zipCode'  => $companyData->zipCode,
                'city'     => $companyData->city,
            ]);
        } catch (CompanyNotFoundException) {
            throw new NotFoundHttpException($this->translator->trans('exception.gusApiNotFound'));
        } catch (\Throwable $exception) {
            throw new CannotGetGusDataException();
        }
    }
}
