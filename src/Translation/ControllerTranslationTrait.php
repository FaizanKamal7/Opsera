<?php

declare(strict_types=1);

namespace App\Translation;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Webmozart\Assert\Assert;

trait ControllerTranslationTrait
{
    /**
     * @param array<string, TranslatableInterface|scalar> $parameters
     */
    protected function flashSuccess(string $message, array $parameters = []): void
    {
        $this->addFlashTranslated('success', $message, $parameters);
    }

    /**
     * @param array<string, TranslatableInterface|scalar> $parameters
     */
    protected function flashWarning(string $message, array $parameters = []): void
    {
        $this->addFlashTranslated('warning', $message, $parameters);
    }

    /**
     * @param array<string, TranslatableInterface|scalar> $parameters
     */
    protected function flashError(string $message, array $parameters = []): void
    {
        $this->addFlashTranslated('error', $message, $parameters);
    }

    protected function flashCreateException(\Throwable $exception): void
    {
        $this->flashException($exception, 'action.create.error');
    }

    protected function flashUpdateException(\Throwable $exception): void
    {
        $this->flashException($exception, 'action.update.error');
    }

    protected function flashDeleteException(\Throwable $exception): void
    {
        $this->flashException($exception, 'action.delete.error');
    }

    protected function flashException(\Throwable $exception, string $message): void
    {
        $this->logException($exception);

        $this->addFlashTranslated('error', $message, ['%reason%' => $exception->getMessage()]);
    }

    /**
     * @param array<string, TranslatableInterface|scalar> $parameters
     */
    private function addFlashTranslated(string $type, string $message, array $parameters = []): void
    {
        $this->addFlash($type, new TranslatableMessage($message, $parameters, 'flashmessages'));
    }

    protected function logException(\Throwable $ex): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $logger = $this->container->get('logger');
        Assert::isInstanceOf($logger, LoggerInterface::class);
        $logger->critical($ex->getMessage());
    }
}
