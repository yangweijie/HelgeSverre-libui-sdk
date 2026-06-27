<?php

/**
 * Bisection test for CircleProgressBar freeze in all-components.php.
 *
 * Each step adds one more element. Change STEP below and re-run.
 *
 * Run: php85 examples/test-circle-progress.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Build;
use Libui\Button;
use Libui\Color;
use Libui\Group;
use Libui\Label;
use Libui\Separator;
use Libui\Tab;
use Libui\Window;
use Libui\Ffi;
use Yangweijie\Ui2\Widgets\CircleProgressBar;
use Yangweijie\Ui2\Widgets\StatusIndicator;
use Yangweijie\Ui2\Widgets\ToggleSwitch;
use Yangweijie\Ui2\Fields\SeparatorLine;

Ffi::init();

// ── Change this to progress through steps ──
const STEP = 0; // Change this: 0..6

echo "Running STEP " . STEP . "\n";

// ── Common widgets used across steps ──
$circleBar = new CircleProgressBar(35);
$circleProgressLabel = new Label("35%");

$groupCircle = Group::titled(
    "CircleProgressBar — custom-drawn ring progress:",
    Build::vbox(Build::stretchy($circleBar->root()), $circleProgressLabel),
);

$circleBtnMinus = new Button("-10")->onClicked(function () use ($circleBar, $circleProgressLabel): void {
    $circleBar->setProgress(max(0, $circleBar->getProgress() - 10));
    $circleProgressLabel->setText($circleBar->getProgress() . "%");
});
$circleBtnPlus = new Button("+10")->onClicked(function () use ($circleBar, $circleProgressLabel): void {
    $circleBar->setProgress(min(100, $circleBar->getProgress() + 10));
    $circleProgressLabel->setText($circleBar->getProgress() . "%");
});
$circleBtnReset = new Button("Reset")->onClicked(function () use ($circleBar, $circleProgressLabel): void {
    $circleBar->setProgress(0);
    $circleProgressLabel->setText("0%");
});

$buttonsRow = Build::hbox($circleBtnMinus, $circleBtnPlus, $circleBtnReset, Build::stretchy(new Label("")));

// ── Step-specific layout ──

if (STEP === 0) {
    // Baseline: just CircleProgressBar in Window → vbox → stretchy(Group) → vbox → stretchy(Area) + Label
    $window = new Window("STEP 0 — CircleProgressBar alone", 600, 500, true);
    $window->setChild(Build::vbox(Build::stretchy($groupCircle), $buttonsRow));
    $window->run();

} elseif (STEP === 1) {
    // Step 1: Wrap in Tab container
    $tab = new Tab();
    $tab->appendMargined("Custom", Build::vbox($groupCircle));
    $window = new Window("STEP 1 — CircleProgressBar in Tab", 600, 500, true);
    $window->setChild($tab);
    $window->run();

} elseif (STEP === 2) {
    // Step 2: Add ToggleSwitch before CircleProgressBar
    $toggle = new ToggleSwitch(false);
    $groupToggleSwitch = Group::titled(
        "Toggle Switch",
        Build::hbox(
            new Label("Enable feature:"),
            Build::stretchy($toggle->root()),
            Build::stretchy(new Label("")),
        ),
    );
    $tab = new Tab();
    $tab->appendMargined("Custom", Build::vbox($groupToggleSwitch, $groupCircle));
    $window = new Window("STEP 2 — + ToggleSwitch", 600, 500, true);
    $window->setChild($tab);
    $window->run();

} elseif (STEP === 3) {
    // Step 3: Add 3x StatusIndicators
    $toggle = new ToggleSwitch(false);
    $groupToggleSwitch = Group::titled(
        "Toggle Switch",
        Build::hbox(
            new Label("Enable feature:"),
            Build::stretchy($toggle->root()),
            Build::stretchy(new Label("")),
        ),
    );
    $statusGreen = new StatusIndicator(Color::rgb(0x22c55e));
    $statusRed = new StatusIndicator(Color::rgb(0xef4444));
    $statusYellow = new StatusIndicator(Color::rgb(0xeab308));
    $groupStatus = Group::titled(
        "Status Indicators",
        Build::hbox(
            new Label("Online:"), Build::stretchy($statusGreen->root()),
            new Label("   "),
            new Label("Offline:"), Build::stretchy($statusRed->root()),
            new Label("   "),
            new Label("Warning:"), Build::stretchy($statusYellow->root()),
            Build::stretchy(new Label("")),
        ),
    );
    $tab = new Tab();
    $tab->appendMargined("Custom", Build::vbox($groupToggleSwitch, $groupStatus, $groupCircle));
    $window = new Window("STEP 3 — + StatusIndicators", 600, 500, true);
    $window->setChild($tab);
    $window->run();

} elseif (STEP === 4) {
    // Step 4: Full Custom tab (all widgets)
    $toggle = new ToggleSwitch(false);
    $groupToggleSwitch = Group::titled(
        "Toggle Switch",
        Build::hbox(
            new Label("Enable feature:"),
            Build::stretchy($toggle->root()),
            Build::stretchy(new Label("")),
        ),
    );
    $statusGreen = new StatusIndicator(Color::rgb(0x22c55e));
    $statusRed = new StatusIndicator(Color::rgb(0xef4444));
    $statusYellow = new StatusIndicator(Color::rgb(0xeab308));
    $groupStatus = Group::titled(
        "Status Indicators",
        Build::hbox(
            new Label("Online:"), Build::stretchy($statusGreen->root()),
            new Label("   "),
            new Label("Offline:"), Build::stretchy($statusRed->root()),
            new Label("   "),
            new Label("Warning:"), Build::stretchy($statusYellow->root()),
            Build::stretchy(new Label("")),
        ),
    );

    $toggleStatusBtn = new Button("Toggle Status");
    $separator3 = new SeparatorLine();
    $separator4 = new SeparatorLine();

    $customToastLabel = new Label("Toast — native OS desktop notification:");
    $toastBtn = new Button("Send Toast");

    $tab = new Tab();
    $tab->appendMargined("Custom", Build::vbox(
        $groupToggleSwitch,
        $groupStatus,
        $toggleStatusBtn,
        $separator3->root(),
        $groupCircle,
        $buttonsRow,
        $separator4->root(),
        $customToastLabel,
        Build::hbox($toastBtn, Build::stretchy(new Label(""))),
        Build::stretchy(new Label("")),
    ));
    $window = new Window("STEP 4 — Full Custom tab", 600, 500, true);
    $window->setChild($tab);
    $window->run();

} elseif (STEP === 5) {
    // Step 5: Use App::new() instead of $window->run()
    $toggle = new ToggleSwitch(false);
    $groupToggleSwitch = Group::titled(
        "Toggle Switch",
        Build::hbox(
            new Label("Enable feature:"),
            Build::stretchy($toggle->root()),
            Build::stretchy(new Label("")),
        ),
    );
    $statusGreen = new StatusIndicator(Color::rgb(0x22c55e));
    $statusRed = new StatusIndicator(Color::rgb(0xef4444));
    $statusYellow = new StatusIndicator(Color::rgb(0xeab308));
    $groupStatus = Group::titled(
        "Status Indicators",
        Build::hbox(
            new Label("Online:"), Build::stretchy($statusGreen->root()),
            new Label("   "),
            new Label("Offline:"), Build::stretchy($statusRed->root()),
            new Label("   "),
            new Label("Warning:"), Build::stretchy($statusYellow->root()),
            Build::stretchy(new Label("")),
        ),
    );

    $toggleStatusBtn = new Button("Toggle Status");
    $separator3 = new SeparatorLine();
    $separator4 = new SeparatorLine();

    $customToastLabel = new Label("Toast — native OS desktop notification:");
    $toastBtn = new Button("Send Toast");

    $tab = new Tab();
    $tab->appendMargined("Custom", Build::vbox(
        $groupToggleSwitch,
        $groupStatus,
        $toggleStatusBtn,
        $separator3->root(),
        $groupCircle,
        $buttonsRow,
        $separator4->root(),
        $customToastLabel,
        Build::hbox($toastBtn, Build::stretchy(new Label(""))),
        Build::stretchy(new Label("")),
    ));

    // Fields tab (minimal)
    $fieldsTab = new Tab();
    $fieldsTab->appendMargined("Fields", Build::vbox(new Label("Fields placeholder")));
    $fieldsTab->appendMargined("Custom", $tab);
    $fieldsTab->appendMargined("Dialogs", Build::vbox(new Label("Dialogs placeholder")));
    $fieldsTab->appendMargined("Pickers", Build::vbox(new Label("Pickers placeholder")));
    $fieldsTab->appendMargined("Table", Build::vbox(new Label("Table placeholder")));
    $fieldsTab->appendMargined("WebView", Build::vbox(new Label("WebView placeholder")));

    $mainWindow = new Window("STEP 5 — App::new()", 800, 600, true);
    $outputLabel = new Label("Ready.");
    $mainWindow->setChild(Build::vbox($fieldsTab, $outputLabel));
    App::new()->window($mainWindow)->onShouldQuit(fn() => true)->run();

} elseif (STEP === 6) {
    // Step 6: all-components.php's Custom tab EXACTLY — but rest of tabs are empty
    // (excluding TableView, TreeView, WebView, CodeEditor to avoid WebView dependency)
    $toggle = new ToggleSwitch(false);
    $groupToggleSwitch = Group::titled(
        "Toggle Switch",
        Build::hbox(
            new Label("Enable feature:"),
            Build::stretchy($toggle->root()),
            Build::stretchy(new Label("")),
        ),
    );
    $statusGreen = new StatusIndicator(Color::rgb(0x22c55e));
    $statusRed = new StatusIndicator(Color::rgb(0xef4444));
    $statusYellow = new StatusIndicator(Color::rgb(0xeab308));
    $groupStatus = Group::titled(
        "Status Indicators",
        Build::hbox(
            new Label("Online:"), Build::stretchy($statusGreen->root()),
            new Label("   "),
            new Label("Offline:"), Build::stretchy($statusRed->root()),
            new Label("   "),
            new Label("Warning:"), Build::stretchy($statusYellow->root()),
            Build::stretchy(new Label("")),
        ),
    );

    $toggleStatusBtn = new Button("Toggle Status");
    $separator3 = new SeparatorLine();
    $separator4 = new SeparatorLine();

    $customToastLabel = new Label("Toast — native OS desktop notification:");
    $toastBtn = new Button("Send Toast");

    $tab = new Tab();
    $tab->appendMargined("Custom", Build::vbox(
        $groupToggleSwitch,
        $groupStatus,
        $toggleStatusBtn,
        $separator3->root(),
        $groupCircle,
        $buttonsRow,
        $separator4->root(),
        $customToastLabel,
        Build::hbox($toastBtn, Build::stretchy(new Label(""))),
        Build::stretchy(new Label("")),
    ));

    $mainWindow = new Window("STEP 6 — Full tabs (no WebView)", 800, 600, true);
    $outputLabel = new Label("Ready.");
    $mainWindow->setChild(Build::vbox($tab, $outputLabel));
    App::new()->window($mainWindow)->onShouldQuit(fn() => true)->run();

} else {
    echo "Unknown STEP. Set STEP=0..6\n";
    exit(1);
}

echo "STEP " . STEP . " done — event loop ended normally\n";
