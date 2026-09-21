<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Reader;

/**
 * The resolved values of one area, resolved on first access.
 *
 * Resolving is lazy per area, not per property: Sulu's `ContentResolver` resolves a dimension
 * content in one pass and has no property filter - its second argument maps nested properties
 * onto the root, it does not restrict what is resolved. So the whole area is resolved once, on
 * the first property that is touched, and memoised for the rest of the request. A template that
 * never mentions an area still costs nothing, which is what the laziness is for.
 *
 * Stored data may be cached, resolved smart content may not: smart content is a live query by
 * definition, and caching its result silently freezes a "latest news" block.
 *
 * A property is read as `$settings->name`, `$settings['name']` or `$settings->get('name')`. The
 * first form goes through `__isset()`/`__get()`, which is also what Twig uses, so every property
 * the template declares wins over a method of the same name. A property the template does *not*
 * declare and that happens to be called `get`, `view`, `all` or `count` falls through to the
 * method instead of reading as null - use `$settings['count']` if a template ever needs one of
 * those names for something that may be empty.
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
final class ResolvedSettings implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * @var array{content: array<string, mixed>, view: array<string, mixed>}|null
     */
    private ?array $resolved = null;

    /**
     * @param \Closure(): array{content: array<string, mixed>, view: array<string, mixed>} $resolve
     */
    public function __construct(private readonly \Closure $resolve)
    {
    }

    public function get(string $property): mixed
    {
        return $this->resolveEverything()['content'][$property] ?? null;
    }

    /**
     * The `view` half of a resolved property: the metadata Sulu returns next to the value, such as
     * the smart content query that produced it.
     */
    public function view(?string $property = null): mixed
    {
        $view = $this->resolveEverything()['view'];

        if (null === $property) {
            return $view;
        }

        return $view[$property] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->resolveEverything()['content'];
    }

    public function __get(string $property): mixed
    {
        return $this->get($property);
    }

    /**
     * Answers for every property the template declares, including the ones an editor left empty.
     *
     * That is what keeps a declared property from colliding with a method of this class: Twig and
     * `$settings->name` consult `__isset()` before looking for a method, so `{{ settings('x').view }}`
     * reads the empty `view` property of the template rather than calling {@see view()}.
     *
     * It is also what makes `isset()` agree with `offsetExists()`.
     */
    public function __isset(string $property): bool
    {
        return \array_key_exists($property, $this->all());
    }

    /**
     * Twig falls back to a method call when `offsetExists()` says the key is not there, so an
     * unset setting reads as null in a template instead of throwing under `strict_variables`.
     *
     * Keeping `offsetExists()` honest is what makes this necessary: `isset()` and `??` must still
     * be able to tell an unset setting from a stored null.
     *
     * It answers Twig's `defined` test as well, which is therefore always true here. `??` is the
     * form that tells a template apart from a stored null.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $property, array $arguments): mixed
    {
        return $this->get($property);
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists((string) $offset, $this->all());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Resolved settings are read only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Resolved settings are read only.');
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->all());
    }

    public function count(): int
    {
        return \count($this->all());
    }

    /**
     * @return array{content: array<string, mixed>, view: array<string, mixed>}
     */
    private function resolveEverything(): array
    {
        return $this->resolved ??= ($this->resolve)();
    }
}
