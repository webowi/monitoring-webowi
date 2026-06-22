<?php

declare(strict_types=1);

namespace App\Logging\Ui\Ingest;

use App\Logging\Application\Ingest\IngestLogEntryMessage;
use App\Logging\Application\Ingest\IngestionRateLimitExceededException;
use App\Projects\Infrastructure\Security\IngestionPrincipal;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/logs/ingest', name: 'logs_ingest', methods: ['POST'])]
final class IngestLogController
{
    public function __construct(
        private readonly Security $security,
        private readonly MessageBusInterface $messageBus,
        #[Autowire(service: 'limiter.log_ingestion')]
        private readonly RateLimiterFactory $logIngestionLimiter,
    ) {}

    public function __invoke(#[MapRequestPayload] IngestLogInput $input): JsonResponse
    {
        /** @var IngestionPrincipal $principal */
        $principal = $this->security->getUser();

        $projectUuid = (string) $principal->getProject()->getUuid();

        $limiter = $this->logIngestionLimiter->create($projectUuid);
        if (false === $limiter->consume()->isAccepted()) {
            throw new IngestionRateLimitExceededException();
        }

        $this->messageBus->dispatch(new IngestLogEntryMessage(
            projectId: $projectUuid,
            occurredAt: $input->datetime->format(\DateTimeInterface::ATOM),
            severity: $input->level->value,
            message: $input->message,
            context: $input->context,
        ));

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }
}
