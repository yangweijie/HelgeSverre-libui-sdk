<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Ffi;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\Widgets\CodeEditor;

Ffi::init();

$window = new Window('CodeEditor Test', 800, 600, true);

$label = new Label('Type in the editor on the right — text should appear.');
$window->setChild($label);

// Window must be shown before creating WebView-based widgets —
// wvb_create() calls IsWindow(HWND) which requires uiInit() + a visible window.
$window->show();

$editor = new CodeEditor(
    $window,
    0,
    0,
    780,
    560,
    'php',
    false,
    "<?php\n\necho 'Hello, World!';\n\n\$data = ['foo' => 'bar'];\nforeach (\$data as \$k => \$v) {\n    print \"\$k: \$v\\n\";\n}\n",
);

// Keep the editor filling the window content area on resize (20px right/bottom margin)
$editor->autoResize($window, 0, 0, 20, 40);

$editor->onChange(function (string $code) use ($label): void {
    $label->setText('Changed: ' . mb_substr($code, 0, 60) . '...');
});

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
