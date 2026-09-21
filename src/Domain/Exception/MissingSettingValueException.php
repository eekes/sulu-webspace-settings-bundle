<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when a read model requires a value that has never been filled in.
 *
 * Hydration is the natural place to fail loudly on a required setting that is empty: give the
 * constructor parameter a default to make the setting optional.
 */
class MissingSettingValueException extends \RuntimeException implements SettingsExceptionInterface
{
    /**
     * @param class-string $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly string $property,
        private readonly string $expectedKey,
    ) {
        parent::__construct(\sprintf(
            'The settings read model "%s" requires a value for "$%s", but no property "%s" was filled in. '
            . 'Fill it in, or give the constructor parameter a default value to make it optional.',
            $modelClass,
            $property,
            $expectedKey,
        ));
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getExpectedKey(): string
    {
        return $this->expectedKey;
    }
}
