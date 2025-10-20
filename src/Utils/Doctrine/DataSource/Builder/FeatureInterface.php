<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder;

use App\Utils\Doctrine\DataSource\Builder;

interface FeatureInterface
{
    public function getBuilder(): Builder;

    public function getDefaultOptions(): array;

    public function setOptions(array $options): static;

    public function getOptions(): array;

    /**
     * Determines weather the feature must break the prepare procedure and the following features gets skipped.
     */
    public function isBreaking(): bool;

    /**
     * Check if the feature is prepared.
     */
    public function isPrepared(): bool;

    /**
     * Check if the feature is applied.
     */
    public function isApplied(): bool;

    /**
     * Check if the feature can be applied.
     */
    public function canApply(): bool;

    /**
     * Prepare the feature with given parameters.
     *
     * If this method returns false this means that the feature can not be applied with these parameters
     */
    public function prepare(array $params): bool;

    /** @noinspection SpellCheckingInspection */
    public function unprepare(): static;

    /**
     * Apply the feature. Note that the feature must be prepared and not already applied.
     */
    public function apply(): void;

    public function unapply(): void;
}
