<?php
declare(strict_types=1);
require __DIR__ . "/../vendor/autoload.php";

error_reporting(E_ALL);
ini_set('display_errors', '1');

function dbg(string $msg): void
{
    $line = date('[H:i:s]') . " " . $msg . PHP_EOL;
    fwrite(STDERR, $line);
    flush();
}

dbg("=== Toast Test ===");

dbg("1. Creating libui window...");
\Libui\Ffi::init();
dbg("   Ffi::init OK");

dbg("2. Sending via Toast class...");
$ok = \Yangweijie\Ui2\Widgets\Toast::show("ui2 Toast", "Hello from Toast::show()!");
dbg("   Result: " . ($ok ? "SUCCESS" : "FAILED"));
dbg("   Error: " . (\Yangweijie\Ui2\Widgets\Toast::lastError() ?? "none"));

dbg("3. Creating window...");
$win = new \Libui\Window("Toast Test", 400, 200);
$btn = new \Libui\Button("Send Toast");
$btn->onClicked(function () {
    $ok = \Yangweijie\Ui2\Widgets\Toast::show("Clicked!", "Button was clicked");
    fwrite(STDERR, "   Button click toast: " . ($ok ? "OK" : "FAIL") . "\n");
});
$label = new \Libui\Label("Click button to test toast");
$win->setChild(\Libui\Build::vbox($btn, $label));
dbg("4. Starting event loop...");
\Libui\App::new()->window($win)->onShouldQuit(fn() => true)->run();
