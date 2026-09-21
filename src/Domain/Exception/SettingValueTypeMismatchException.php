<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when a stored value does not fit the constructor parameter it is mapped onto.
 *
 * Nearly always a read model that disagrees with its template - a `text_line` typed as `int`, a
 * selection typed as `string`. The original `TypeError` is kept as the previous exception, since
 * it carries the parameter position.
 */
class SettingValueTypeMismatchException extends \RuntimeException implements SettingsExceptionInterface
{
    /**
     * @param class-string $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        \TypeError $previous,
    ) {
        parent::__construct(\sprintf(
            'A stored value does not fit the settings read model "%s": %s. '
            . 'The constructor parameter and the template property it is filled from disagree on the type.',
            $modelClass,
            $previous->getMessage(),
        ), 0, $previous);
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }
}
