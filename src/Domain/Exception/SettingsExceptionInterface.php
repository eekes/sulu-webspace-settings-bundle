<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Implemented by every exception this bundle throws.
 *
 * So a consuming project can catch everything that comes out of the bundle without naming each
 * class, and so adding an exception later is not a breaking change for code that already catches
 * this interface.
 */
interface SettingsExceptionInterface extends \Throwable
{
}
