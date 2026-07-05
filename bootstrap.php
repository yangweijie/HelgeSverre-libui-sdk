<?php

/**
 * Auto-apply patches when the package is installed as a Composer dependency.
 *
 * The root project's `post-autoload-dump` runs `@php patch.php`, but Composer
 * only fires root-package scripts — never dependency scripts.  To bridge that
 * gap, this bootstrap file (always loaded via `autoload.files`) checks a
 * marker and applies patches exactly once after install/update.
 */

$patchesMarker = __DIR__ . '/.patches_applied';
if (!file_exists($patchesMarker)) {
    require __DIR__ . '/patch.php';
}

new \NunoMaduro\Collision\Provider()->register();
