<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;

Ffi::init();

$iconPath = match (PHP_OS_FAMILY) {
    'Windows' => __DIR__ . '/../assets/icon.ico',
    default   => __DIR__ . '/../assets/app-icon.png',
};

echo "Icon path: {$iconPath}\n";
echo "Exists: " . (file_exists($iconPath) ? 'yes' : 'no') . "\n";

$window = new Window("Icon Test", 400, 200, true);
$window->setChild(new Label("Check taskbar icon after this window appears."));

$app = App::new()->window($window)->onShouldQuit(fn() => true);

$app->afterInit(function () use ($window, $iconPath): void {
    echo "Setting icon...\n";
    try {
        $window->setWindowIcon($iconPath);
        echo "Icon set successfully!\n";
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
});

$app->run();
echo "Done.\n";
