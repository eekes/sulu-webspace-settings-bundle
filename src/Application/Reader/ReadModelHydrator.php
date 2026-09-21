<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Reader;

use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\MissingSettingValueException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingValueTypeMismatchException;

/**
 * Maps resolved values onto a read model constructor by parameter name, `snake_case` to
 * `camelCase`.
 *
 * Deliberately mechanical and predictable: consumers debug this by reading the template XML next
 * to the class, so a clever mapping layer would cost more than it gives. A parameter is also
 * matched by its exact name, for templates that already use camelCase property names.
 */
final class ReadModelHydrator
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     *
     * @throws MissingSettingValueException
     * @throws SettingValueTypeMismatchException
     *
     * @return T
     */
    public function hydrate(string $className, array $data): object
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (null === $constructor || 0 === $constructor->getNumberOfParameters()) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $key = self::toSnakeCase($name);

            $value = $data[$key] ?? $data[$name] ?? null;

            if (null === $value) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();

                    continue;
                }

                if ($parameter->allowsNull()) {
                    $arguments[] = null;

                    continue;
                }

                throw new MissingSettingValueException($className, $name, $key);
            }

            $arguments[] = $value;
        }

        try {
            return $reflection->newInstanceArgs($arguments);
        } catch (\TypeError $error) {
            // a raw TypeError names the constructor and the position, never the template property
            // that produced the value, which is the only thing worth knowing here
            throw new SettingValueTypeMismatchException($className, $error);
        }
    }

    /**
     * Mechanical, which means an acronym is split the way any other run of capitals is:
     * `facebookUrl` becomes `facebook_url` but `facebookURL` becomes `facebook_u_r_l`. Name the
     * parameter the way the template names the property and the question never comes up.
     */
    public static function toSnakeCase(string $value): string
    {
        return \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
