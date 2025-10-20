<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ?string $defaultEnv = null,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/api/')) {
            $this->handleAsJsonResponse($event);
        }
        $acceptableContentTypes = $request->getAcceptableContentTypes();
        if (\in_array('application/json', $acceptableContentTypes, true)) {
            $this->handleAsJsonResponse($event);
        }
    }

    private function handleAsJsonResponse(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $masked = 'dev' !== $this->defaultEnv && !$throwable instanceof \LogicException;
        try {
            $this->logger?->error($throwable->getMessage(), ['exception' => $throwable]);
            $event->setResponse(new JsonResponse(['code' => $throwable->getCode(), 'message' => $masked ? 'Internal Server Error' : $throwable->getMessage()]));
        } catch (\Exception $e) {
            $this->logger?->error(\sprintf('An exception was thrown when handling "%s".', $throwable::class), ['exception' => $e]);
            $throwable = new \RuntimeException('Exception thrown when handling an exception.', 0, $e);
            $event->setThrowable($throwable);
            $event->setResponse(new JsonResponse(['code' => 500, 'message' => $throwable->getMessage() ? 'Internal Server Error' : $throwable->getMessage()]));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
