<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Tests;

use Libui\Dialogs;
use Libui\Window;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for the upstream Libui\Dialogs helper.
 *
 * The core private method Dialogs::nullIfEmpty() converts empty C strings
 * (which libui returns when the user cancels a file dialog) to null. These
 * tests verify that conversion via reflection — the only way to reach the
 * private method without instantiating a real FFI dialog.
 */
final class DialogsTest extends TestCase
{
    private Dialogs $dialogs;

    protected function setUp(): void
    {
        // Dialogs needs a Window, but we never call methods that touch FFI
        // during these tests. Create a minimal mock.
        $window = $this->createStub(Window::class);
        $this->dialogs = new Dialogs($window);
    }

    /**
     * nullIfEmpty('') must return null — this is the critical path that
     * converts a cancelled C dialog (empty string) into a PHP null.
     */
    public function testNullIfEmptyReturnsNullForEmptyString(): void
    {
        $result = $this->invokeNullIfEmpty('');
        self::assertNull($result, 'An empty string must become null');
    }

    /**
     * nullIfEmpty() must return the input unchanged when it is non-empty.
     * This covers the "user picked a file" path.
     */
    public function testNullIfEmptyReturnsStringForNonEmptyValue(): void
    {
        $result = $this->invokeNullIfEmpty('/home/user/file.txt');
        self::assertSame('/home/user/file.txt', $result);
    }

    public function testNullIfEmptyReturnsStringForSingleCharacter(): void
    {
        $result = $this->invokeNullIfEmpty('/');
        self::assertSame('/', $result);
    }

    public function testNullIfEmptyReturnsNullForWhitespaceString(): void
    {
        // libui never returns whitespace-only paths, but the implementation
        // uses === '' so whitespace is preserved. Documenting current behaviour.
        $result = $this->invokeNullIfEmpty('   ');
        self::assertSame('   ', $result, 'nullIfEmpty uses strict === "" — whitespace is NOT empty');
    }

    /**
     * Invoke the private Dialogs::nullIfEmpty() via reflection.
     */
    private function invokeNullIfEmpty(string $value): ?string
    {
        $method = new \ReflectionMethod(Dialogs::class, 'nullIfEmpty');
        $method->setAccessible(true);

        /** @var string|null $result */
        return $method->invoke($this->dialogs, $value);
    }
}
