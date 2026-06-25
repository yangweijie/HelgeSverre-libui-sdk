<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Label;
use Libui\Window;
use Yangweijie\Ui2\WebView;

$window = new Window('Bridge Debug', 500, 400, true);

$log = new Label('Starting debug...');

$webview = new WebView($window, 0, 0, 500, 350, true);

// Step 1: Set minimal HTML
$webview->setHtml(<<<'HTML'
<html><body>
<div id="log">waiting...</div>
<script>
document.getElementById("log").textContent = "step1: init script ran, __webview__=" + typeof window.__webview__;
</script>
</body></html>
HTML
);

// Step 2: Check if __webview__ exists after setHtml
$webview->eval('document.getElementById("log").textContent += " | step2: " + typeof window.__webview__;');

// Step 3: Bind a function
$webview->bind('testBind', function (string $id, string $req) use ($webview, $log): void {
    $log->setText("PHP callback fired! req={$req}");
    $webview->return($id, 0, '"pong"');
});

// Step 4: Check if the function was created
$webview->eval('document.getElementById("log").textContent += " | step3: testBind=" + typeof window.testBind;');

// Step 5: Try calling it
$webview->eval(<<<'JS'
document.getElementById("log").textContent += " | step4: calling...";
try {
    window.testBind(JSON.stringify({hello: "world"})).then(function(r) {
        document.getElementById("log").textContent += " | step5: result=" + JSON.stringify(r);
    }).catch(function(e) {
        document.getElementById("log").textContent += " | step5: error=" + e.message;
    });
} catch(e) {
    document.getElementById("log").textContent += " | step5: catch=" + e.message;
}
JS
);

// Step 6: Also check after a delay
$webview->eval('setTimeout(function() {
    document.getElementById("log").textContent += " | step6: testBind=" + typeof window.testBind + " __webview__=" + typeof window.__webview__;
}, 1000);');

$window->setChild(Build::vbox($log));

App::new()
    ->window($window)
    ->onShouldQuit(fn () => true)
    ->run();
