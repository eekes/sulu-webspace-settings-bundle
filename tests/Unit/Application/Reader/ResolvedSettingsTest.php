<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Reader;

use Eekes\SuluWebspaceSettingsBundle\Application\Reader\ResolvedSettings;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ResolvedSettingsTest extends TestCase
{
    public function testResolvesNothingUntilAPropertyIsTouched(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings(['facebook_url' => 'https://facebook.com/sulu'], [], $calls);

        $this->assertCount(0, $calls, 'Building the object must not resolve anything');

        $this->assertSame('https://facebook.com/sulu', $settings->get('facebook_url'));
        $this->assertCount(1, $calls);
    }

    public function testMemoisesTheResolvedArea(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings(['teasers' => ['a', 'b']], [], $calls);

        $this->assertSame(['a', 'b'], $settings->get('teasers'));
        $this->assertSame(['a', 'b'], $settings['teasers']);
        $this->assertSame(['a', 'b'], $this->readMagicProperty($settings, 'teasers'));
        $this->assertSame(['a', 'b'], $settings->all()['teasers']);

        $this->assertCount(1, $calls);
    }

    public function testReadsAnUnknownPropertyAsNull(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings(['facebook_url' => 'https://facebook.com/sulu'], [], $calls);

        $this->assertNull($settings->get('nope'));
        $this->assertNull($this->readMagicProperty($settings, 'nope'));
        $this->assertFalse(isset($settings['nope']));
    }

    /**
     * Twig checks `offsetExists()` first and falls back to a method call, so an unset setting must
     * be readable as a method for a template not to blow up under `strict_variables`.
     */
    public function testReadsAnUnknownPropertyAsAMethodCallForTwig(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings(['facebook_url' => 'https://facebook.com/sulu'], [], $calls);

        $this->assertNull($this->callMagicMethod($settings, 'nope'));
        $this->assertSame('https://facebook.com/sulu', $this->callMagicMethod($settings, 'facebook_url'));
    }

    public function testExposesTheViewHalf(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings(
            ['teasers' => ['a']],
            ['teasers' => ['page' => 1, 'hasNextPage' => false]],
            $calls,
        );

        $this->assertSame(['page' => 1, 'hasNextPage' => false], $settings->view('teasers'));
        $this->assertSame(['teasers' => ['page' => 1, 'hasNextPage' => false]], $settings->view());
        $this->assertNull($settings->view('nope'));
        $this->assertCount(1, $calls);
    }

    /**
     * `view`, `all`, `get` and `count` are methods of this class, and Twig looks for a method when
     * the property path finds nothing. A property the template declares must win, including when
     * the editor left it empty - otherwise `{{ settings('x').view }}` silently renders the view
     * map instead of the empty value.
     */
    public function testADeclaredPropertyWinsOverAMethodOfTheSameNameInTwig(): void
    {
        $settings = $this->createSettings(
            ['view' => null, 'count' => null, 'all' => 'shown'],
            ['facebook_url' => ['meta' => 1]],
            new \ArrayObject(),
        );

        $this->assertSame('', $this->render('{{ s.view }}', $settings));
        $this->assertSame('', $this->render('{{ s.count }}', $settings));
        $this->assertSame('shown', $this->render('{{ s.all }}', $settings));
    }

    public function testKeepsTwigWorkingForPropertiesTheTemplateDoesNotDeclare(): void
    {
        $settings = $this->createSettings(['facebook_url' => 'https://facebook.com/sulu'], [], new \ArrayObject());

        $this->assertSame('fallback', $this->render('{{ s.nope ?? "fallback" }}', $settings));
        $this->assertSame('', $this->render('{{ s.nope }}', $settings), 'strict_variables must not blow up');

        // the catch-all __call() is what keeps the line above from throwing, and it also answers
        // the `defined` test - so `is defined` is always true here and `??` is the honest form
        $this->assertSame('defined', $this->render('{{ s.nope is defined ? "defined" : "no" }}', $settings));
    }

    /**
     * A stored null is still a declared property, and both ways of asking have to agree on that.
     */
    public function testIssetAgreesWithOffsetExists(): void
    {
        $settings = $this->createSettings(['empty' => null, 'filled' => 'a'], [], new \ArrayObject());

        $this->assertTrue(isset($settings['empty']));
        $this->assertTrue(isset($settings->empty));
        $this->assertTrue(isset($settings['filled']));
        $this->assertTrue(isset($settings->filled));
        $this->assertFalse(isset($settings['nope']));
        $this->assertFalse(isset($settings->nope));
    }

    public function testIsCountableAndIterable(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings(['a' => 1, 'b' => 2], [], $calls);

        $this->assertCount(2, $settings);
        $this->assertSame(['a' => 1, 'b' => 2], \iterator_to_array($settings));
    }

    public function testIsReadOnly(): void
    {
        $calls = new \ArrayObject();

        $settings = $this->createSettings([], [], $calls);

        $this->expectException(\LogicException::class);

        $settings['a'] = 'b';
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $view
     * @param \ArrayObject<int, true> $calls one entry per time the area was resolved
     */
    private function createSettings(array $content, array $view, \ArrayObject $calls): ResolvedSettings
    {
        return new ResolvedSettings(static function () use ($content, $view, $calls): array {
            $calls[] = true;

            return ['content' => $content, 'view' => $view];
        });
    }

    /**
     * Rendered through Twig on purpose: the order in which Twig tries a property, `__isset()` and
     * a method is the whole point, and no hand written call reproduces it.
     */
    private function render(string $template, ResolvedSettings $settings): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]), ['strict_variables' => true]);

        return $twig->render('t', ['s' => $settings]);
    }

    /**
     * Twig falls back to a method call, so that path is called the way Twig calls it.
     */
    private function callMagicMethod(ResolvedSettings $settings, string $property): mixed
    {
        return $settings->$property();
    }

    /**
     * Twig reaches values through __get as well, so that path is tested the way Twig uses it.
     */
    private function readMagicProperty(ResolvedSettings $settings, string $property): mixed
    {
        return $settings->$property;
    }
}
