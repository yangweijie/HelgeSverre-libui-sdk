<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test groups
|--------------------------------------------------------------------------
|
| Tests that construct native libui controls or otherwise depend on the FFI
| binding / a display server are marked with ->group('ffi'). They are excluded
| from the default `composer test` run (which uses --exclude-group ffi) so the
| stable subset stays green in headless / CI environments where FFI or a display
| is unavailable. Run them explicitly with `composer test:ffi`.
*/

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific
| PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
|
| You may need to change it using the "uses()" function to bind custom test
| case classes.
*/

// uses(Tests\TestCase::class)->in('Feature');
