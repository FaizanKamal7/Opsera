<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder;

use App\Utils\Doctrine\DataSource\Builder;

class FeaturePayload extends \stdClass
{
    private string $name = '';
    private string $class = '';
    private int $priority = 0;
    private array $options = [];
    private ?FeatureInterface $feature = null;

    public function __construct($name, $class, array $options = [], int $priority = 0)
    {
        if (!\is_string($name)) {
            throw new \RuntimeException(\sprintf('Expected string for builder feature name, but got "%s"', \is_object($name) ? $name::class : \gettype($name)));
        }
        if (!\is_string($class)) {
            throw new \RuntimeException(\sprintf('Expected string for builder feature class, but got "%s"', \is_object($class) ? $class::class : \gettype($class)));
        }
        $this->name = $name;
        $this->class = $class;
        $this->priority = $priority;
        $this->options = $options;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return class-string<FeatureInterface>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setFeature(FeatureInterface $feature): static
    {
        $this->feature = $feature;

        return $this;
    }

    public function getFeature(): ?FeatureInterface
    {
        return $this->feature;
    }

    /**
     * @throws Exception\AssertFeatureLoadedStateException
     */
    public function load(Builder $builder): ?FeatureInterface
    {
        $this->assertLoaded(false);
        $featureClass = $this->class;
        if (!is_subclass_of($featureClass, FeatureInterface::class)) {
            throw new \RuntimeException(\sprintf('Class %s must implement "%s"!', $featureClass, FeatureInterface::class));
        }
        $this->feature = new $featureClass($builder, $this->options);

        return $this->feature;
    }

    public function unload(): void
    {
        if ($this->isLoaded()) {
            $this->feature->unprepare();
        }
        $this->feature = null;
    }

    /**
     * @throws Exception\AssertFeatureLoadedStateException
     */
    public function assertLoaded($state): void
    {
        if ($this->isLoaded() !== $state) {
            throw new Exception\AssertFeatureLoadedStateException(\sprintf('The dataset build feature payload "%s" is in unxpected load state "%s"', $this->name, $state ? 'FALSE' : 'TRUE'));
        }
    }

    public function isLoaded(): bool
    {
        return null !== $this->feature;
    }

    public static function create(string $name, FeatureInterface|string $feature, array $options = []): static
    {
        if (\is_object($feature)) {
            $instance = new static($name, $feature::class, $options);
            $instance->setFeature($feature);

            return $instance;
        }

        return new static($feature, $options);
    }
}
