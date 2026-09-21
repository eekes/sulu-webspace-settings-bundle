<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

class UnknownSettingsModelException extends \InvalidArgumentException implements SettingsExceptionInterface
{
    /**
     * @param class-string $className
     * @param string[] $availableModels
     */
    public function __construct(
        private readonly string $className,
        array $availableModels = [],
    ) {
        parent::__construct(\sprintf(
            'The class "%s" does not declare a settings area. Add the #[AsSettingsArea] attribute to it. '
            . 'Classes declaring an area: %s.',
            $className,
            [] === $availableModels ? '(none)' : \implode(', ', $availableModels),
        ));
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
