<?php

/**
 * All Components Demo — showcases every widget/field/picker in the ui2 SDK.
 *
 * Tabs:
 *   - "Fields"   — all HasValue fields (TextField, NumberField, SliderField, …)
 *   - "Custom"   — ToggleSwitch, StatusIndicator, CircleProgressBar
 *   - "Dialogs"  — MessageBox, DialogConfirm, DialogPrompt, Toast
 *   - "Pickers"  — ColorPickerDialog, FontPickerDialog, DatePickerDialog, TimePickerDialog
 *   - "Table"    — TableView (editable cells, sortable headers)
 *   - "WebView"  — TreeView, CodeEditor (overlay child-window widgets)
 *
 * Run: php examples/all-components.php
 */

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Libui\App;
use Libui\Build;
use Libui\Color;
use Libui\Group;
use Libui\Label;
use Libui\Separator;
use Libui\Tab;
use Libui\Window;
use Libui\Button;
use Libui\Entry;
use Libui\Ffi;

use Yangweijie\Ui2\Dialogs\DialogConfirm;
use Yangweijie\Ui2\Dialogs\DialogPrompt;
use Yangweijie\Ui2\Dialogs\MessageBox;

use Yangweijie\Ui2\Fields\CheckboxField;
use Yangweijie\Ui2\Fields\ComboBoxField;
use Yangweijie\Ui2\Fields\DatePickerField;
use Yangweijie\Ui2\Fields\EditableComboBoxField;
use Yangweijie\Ui2\Fields\FilePickerField;
use Yangweijie\Ui2\Fields\NumberField;
use Yangweijie\Ui2\Fields\PasswordField;
use Yangweijie\Ui2\Fields\ProgressBarField;
use Yangweijie\Ui2\Fields\RadioGroup;
use Yangweijie\Ui2\Fields\SearchField;
use Yangweijie\Ui2\Fields\SeparatorLine;
use Yangweijie\Ui2\Fields\SliderField;
use Yangweijie\Ui2\Fields\TextAreaField;
use Yangweijie\Ui2\Fields\TextField;

use Yangweijie\Ui2\Pickers\ColorPickerDialog;
use Yangweijie\Ui2\Pickers\DatePickerDialog;
use Yangweijie\Ui2\Pickers\FontPickerDialog;
use Yangweijie\Ui2\Pickers\TimePickerDialog;

use Yangweijie\Ui2\Widgets\CircleProgressBar;
use Yangweijie\Ui2\Widgets\CodeEditor;
use Yangweijie\Ui2\Widgets\StatusIndicator;
use Yangweijie\Ui2\Widgets\TableView;
use Yangweijie\Ui2\Widgets\Toast;
use Yangweijie\Ui2\Widgets\ToggleSwitch;
use Yangweijie\Ui2\Widgets\TreeView;

Ffi::init();

// ── Status / output label (shared across all tabs) ──
$outputLabel = new Label(
    "Interact with the controls above — events appear here.",
);

// ═════════════════════════════════════════════════════════════════════════════
// TAB 1 — Fields
// ═════════════════════════════════════════════════════════════════════════════

$textField = new TextField("Name:", "John Doe");
$searchField = new SearchField("Search:", "");
$passwordField = new PasswordField("Password:", "");
$numberField = new NumberField("Quantity:", 0, 100, 5);
$sliderField = new SliderField("Volume:", 0, 100);
$checkboxField = new CheckboxField("Enable feature");
$radioGroup = new RadioGroup("Theme:");
$comboBoxField = new ComboBoxField("Font Size:");
$editableComboBoxField = new EditableComboBoxField("City:");
$datePickerField = DatePickerField::dateOnly("Date:");
$textAreaField = new TextAreaField("Description:", "");
$progressBarField = new ProgressBarField("Progress:");

$radioGroup->addOptions(["Light", "Dark", "Auto"]);
$comboBoxField->addOptions(["12px", "14px", "16px", "18px", "24px"]);
$editableComboBoxField->addOptions([
    "Beijing",
    "Shanghai",
    "Shenzhen",
    "Guangzhou",
]);

// FilePickerField needs a Window — entries added after window creation below
$filePickerField = null;

$fieldFormEntries = [
    "Text" => $textField,
    "Search" => $searchField,
    "Password" => $passwordField,
    "Number" => $numberField,
    "Slider" => $sliderField,
    "Checkbox" => $checkboxField,
    "Radio" => $radioGroup,
    "Combo" => $comboBoxField,
    "Editable" => $editableComboBoxField,
    "Date" => $datePickerField,
    "Text Area" => $textAreaField,
    "Progress" => $progressBarField,
];

$fieldsGroup = Build::form($fieldFormEntries);

$onChange = function (mixed $val) use ($outputLabel): void {
    $outputLabel->setText(
        "Changed: " .
            (is_bool($val) ? ($val ? "true" : "false") : (string) $val),
    );
};
$textField->on("change", $onChange);
$searchField->on("change", $onChange);
$numberField->on("change", $onChange);
$sliderField->on("change", $onChange);
$checkboxField->on("change", $onChange);
$passwordField->on("change", fn() => $outputLabel->setText("Password changed"));
$radioGroup->on(
    "change",
    fn(int $idx) => $outputLabel->setText("Theme index: {$idx}"),
);
$comboBoxField->on(
    "change",
    fn(int $idx) => $outputLabel->setText("Font size index: {$idx}"),
);
$editableComboBoxField->on(
    "change",
    fn(string $val) => $outputLabel->setText("City: {$val}"),
);
$datePickerField->on(
    "change",
    fn(\DateTimeImmutable $dt) => $outputLabel->setText(
        "Date: {$dt->format("Y-m-d")}",
    ),
);
$textAreaField->on("change", fn() => $outputLabel->setText("Notes updated"));

$buttonsBox = Build::hbox(
    new Button("Read All Fields")->onClicked(function () use (
        $datePickerField,
        $textAreaField,
        $textField,
        $numberField,
        $sliderField,
        $outputLabel,
    ): void {
        $lines = [
            "Text: " . $textField->value(),
            "Number: " . $numberField->value(),
            "Slider: " . $sliderField->value(),
            "Date: " . $datePickerField->value()->format("Y-m-d"),
            "Notes: " . mb_substr($textAreaField->value(), 0, 30),
        ];
        $outputLabel->setText(implode(" | ", $lines));
    }),
    new Button("Start Progress")->onClicked(function () use (
        $progressBarField,
        $outputLabel,
    ): void {
        $progressBarField->indeterminate();
        $outputLabel->setText("Progress: indeterminate");
    }),
);

$separator1 = new SeparatorLine();
$separator2 = new SeparatorLine();

$fieldsBox = Build::vbox($fieldsGroup, $buttonsBox);

// ═════════════════════════════════════════════════════════════════════════════
// TAB 2 — Custom Widgets (ToggleSwitch, StatusIndicator, CircleProgressBar)
// ═════════════════════════════════════════════════════════════════════════════

$toggle = new ToggleSwitch(false);
$toggle->on(
    "change",
    fn(bool $on) => $outputLabel->setText($on ? "Toggle: ON" : "Toggle: OFF"),
);

$statusGreen = new StatusIndicator(Color::rgb(0x22c55e));
$statusRed = new StatusIndicator(Color::rgb(0xef4444));
$statusYellow = new StatusIndicator(Color::rgb(0xeab308));

$groupToggleSwitch = Group::titled(
    "Toggle Switch",
    Build::hbox(
        new Label("Enable feature:"),
        Build::stretchy($toggle->root()),
        Build::stretchy(new Label("")),
    ),
);

$groupStatus = Group::titled(
    "Status Indicators",
    Build::hbox(
        new Label("Online:"),
        Build::stretchy($statusGreen->root()),
        new Label("   "),
        new Label("Offline:"),
        Build::stretchy($statusRed->root()),
        new Label("   "),
        new Label("Warning:"),
        Build::stretchy($statusYellow->root()),
        Build::stretchy(new Label("")),
    ),
);

$toggleStatusBtn = new Button("Toggle Status")->onClicked(function () use (
    $statusGreen,
    $statusRed,
    $outputLabel,
): void {
    static $which = false;
    $which = !$which;
    if ($which) {
        $statusRed->setColorHex(0x22c55e);
        $statusGreen->setColorHex(0xef4444);
        $outputLabel->setText("Status: swapped");
    } else {
        $statusGreen->setColorHex(0x22c55e);
        $statusRed->setColorHex(0xef4444);
        $outputLabel->setText("Status: restored");
    }
});

$separator3 = new SeparatorLine();
$separator4 = new SeparatorLine();

$circleBar = new CircleProgressBar(35);

$circleBtnMinus = new Button("-10")->onClicked(function () use (
    $circleBar,
    $outputLabel,
): void {
    $circleBar->setProgress(max(0, $circleBar->getProgress() - 10));
    $outputLabel->setText("Progress: {$circleBar->getProgress()}%");
});
$circleBtnPlus = new Button("+10")->onClicked(function () use (
    $circleBar,
    $outputLabel,
): void {
    $circleBar->setProgress(min(100, $circleBar->getProgress() + 10));
    $outputLabel->setText("Progress: {$circleBar->getProgress()}%");
});
$circleBtnReset = new Button("Reset")->onClicked(function () use (
    $circleBar,
    $outputLabel,
): void {
    $circleBar->setProgress(0);
    $outputLabel->setText("Progress: 0%");
});

$customToastLabel = new Label("Toast — native OS desktop notification:");
$toastBtn = new Button("Send Toast")->onClicked(function () use (
    $outputLabel,
): void {
    $ok = Toast::show("ui2 Demo", "This is a native OS notification!");
    if ($ok) {
        $outputLabel->setText("Toast sent");
    } else {
        $error = Toast::lastError();
        $outputLabel->setText("Toast failed: " . ($error ?? "unknown error"));
    }
});

$groupCircle = Group::titled(
    "CircleProgressBar — custom-drawn ring progress:",
    Build::vbox(Build::stretchy($circleBar->root())),
);

$toggleControls = Build::vbox(
    $groupToggleSwitch,
    $groupStatus,
    $toggleStatusBtn,
    $separator3->root(),
    Build::stretchy($groupCircle),
    $separator4->root(),
    $customToastLabel,
    Build::hbox($toastBtn, Build::stretchy(new Label(""))),
);

// ═════════════════════════════════════════════════════════════════════════════
// TAB 3 — Dialogs (MessageBox, DialogConfirm, DialogPrompt)
// ═════════════════════════════════════════════════════════════════════════════

$separator5 = new SeparatorLine();
$separator6 = new SeparatorLine();
$separator7 = new SeparatorLine();

$dialogControls = Build::vbox(
    new Label("MessageBox — native info/warning/error dialogs:"),
    Build::hbox(
        new Button("Info")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            MessageBox::info(
                $mainWindow,
                "Info",
                "This is an information dialog.",
            );
            $outputLabel->setText("Info dialog closed");
        }),
        new Button("Warning")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            MessageBox::warning(
                $mainWindow,
                "Warning",
                "This is a warning dialog.",
            );
            $outputLabel->setText("Warning dialog closed");
        }),
        new Button("Error")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            MessageBox::error($mainWindow, "Error", "This is an error dialog.");
            $outputLabel->setText("Error dialog closed");
        }),
        Build::stretchy(new Label("")),
    ),
    $separator5->root(),
    new Label("DialogConfirm — return true/false:"),
    Build::hbox(
        new Button("Confirm Delete")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            $confirmed = DialogConfirm::ask(
                $mainWindow,
                "Delete",
                "Delete this item?",
            );
            $outputLabel->setText(
                $confirmed
                    ? "User confirmed deletion"
                    : "User cancelled deletion",
            );
        }),
        Build::stretchy(new Label("")),
    ),
    $separator6->root(),
    new Label("DialogPrompt — return ?string:"),
    Build::hbox(
        new Button("Enter Name")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            $name = DialogPrompt::ask(
                $mainWindow,
                "Name",
                "Enter your name:",
                "Guest",
            );
            $outputLabel->setText(
                $name !== null ? "Hello, {$name}!" : "Prompt cancelled",
            );
        }),
        Build::stretchy(new Label("")),
    ),
    Build::stretchy(new Label("")),
);

// ═════════════════════════════════════════════════════════════════════════════
// TAB 4 — Pickers
// ═════════════════════════════════════════════════════════════════════════════

$colorSwatch = new Label("(click Pick Color)");
$fontPreview = new Label("(click Pick Font)");
$datePreview = new Label("(click Pick Date)");
$timePreview = new Label("(click Pick Time)");

$pickerControls = Build::vbox(
    Group::titled(
        "Color Picker",
        Build::vbox(
            new Label("Pick a color from the native dialog:"),
            Build::hbox(
                new Button("Pick Color")->onClicked(function () use (
                    &$mainWindow,
                    $colorSwatch,
                    $outputLabel,
                ): void {
                    $color = ColorPickerDialog::pick($mainWindow);
                    if ($color !== null) {
                        $colorSwatch->setText(
                            "R={$color->r} G={$color->g} B={$color->b}",
                        );
                        $outputLabel->setText("Color selected");
                    } else {
                        $outputLabel->setText("Color picker cancelled");
                    }
                }),
                $colorSwatch,
                Build::stretchy(new Label("")),
            ),
        ),
    ),
    Group::titled(
        "Font Picker",
        Build::vbox(
            new Label("Pick a font from the native dialog:"),
            Build::hbox(
                new Button("Pick Font")->onClicked(function () use (
                    &$mainWindow,
                    $fontPreview,
                    $outputLabel,
                ): void {
                    $font = FontPickerDialog::pick($mainWindow);
                    if ($font !== null) {
                        $fontPreview->setText(
                            $font->family() . ", " . $font->size() . "pt",
                        );
                        $outputLabel->setText("Font selected");
                    } else {
                        $fontPreview->setText("cancelled");
                    }
                }),
                $fontPreview,
                Build::stretchy(new Label("")),
            ),
        ),
    ),
    Group::titled(
        "Date Picker",
        Build::vbox(
            new Label("Pick a date:"),
            Build::hbox(
                new Button("Pick Date")->onClicked(function () use (
                    &$mainWindow,
                    $datePreview,
                    $outputLabel,
                ): void {
                    $date = DatePickerDialog::pick($mainWindow);
                    if ($date !== null) {
                        $datePreview->setText($date->format("Y-m-d"));
                        $outputLabel->setText("Date selected");
                    } else {
                        $outputLabel->setText("Date picker cancelled");
                    }
                }),
                $datePreview,
                Build::stretchy(new Label("")),
            ),
        ),
    ),
    Group::titled(
        "Time Picker",
        Build::vbox(
            new Label("Pick a time:"),
            Build::hbox(
                new Button("Pick Time")->onClicked(function () use (
                    &$mainWindow,
                    $timePreview,
                    $outputLabel,
                ): void {
                    $time = TimePickerDialog::pick($mainWindow);
                    if ($time !== null) {
                        $timePreview->setText($time->format("H:i"));
                        $outputLabel->setText("Time selected");
                    } else {
                        $outputLabel->setText("Time picker cancelled");
                    }
                }),
                $timePreview,
                Build::stretchy(new Label("")),
            ),
        ),
    ),
    Build::stretchy(new Label("")),
);

// ═════════════════════════════════════════════════════════════════════════════
// TAB 5 — TableView
// ═════════════════════════════════════════════════════════════════════════════

$table = new TableView(
    columns: ["Name", "Age", "Score"],
    rows: [["Alice", 30, 95], ["Bob", 25, 87], ["Charlie", 35, 92]],
    editable: [1, 2],
);
$table->onHeaderClicked(function ($t, int $col) use (
    $table,
    $outputLabel,
): void {
    static $direction = [];
    $dir = ($direction[$col] ?? "desc") === "asc" ? "desc" : "asc";
    $direction[$col] = $dir;
    $table->sortByColumn($col, $dir);
    $outputLabel->setText("Sorted by " . $col . " " . $dir);
});
$table->onRowClicked(function ($t, int $row) use ($outputLabel, $table): void {
    $rows = $table->selectedRows();
    $outputLabel->setText(
        "Row " . $row . " clicked, " . count($rows) . " selected",
    );
});
$addRowBtn = new Button("Add Row")->onClicked(function () use (
    $table,
    $outputLabel,
): void {
    $table->addRow(["New", 0, 0]);
    $outputLabel->setText("Row added (count: " . $table->rowCount() . ")");
});
$removeRowBtn = new Button("Remove Last")->onClicked(function () use (
    $table,
    $outputLabel,
): void {
    if ($table->rowCount() > 0) {
        $table->removeRow($table->rowCount() - 1);
        $outputLabel->setText("Last row removed");
    }
});

$tableControls = Build::vbox(
    Group::titled(
        "Data Table (Age/Score editable — click headers to sort)",
        Build::vbox(
            $table->root(),
            Build::hbox(
                $addRowBtn,
                $removeRowBtn,
                Build::stretchy(new Label("")),
            ),
        ),
    ),
);

// ═════════════════════════════════════════════════════════════════════════════
// TAB 6 — WebView (TreeView, CodeEditor)
// ═════════════════════════════════════════════════════════════════════════════

$separator8 = new SeparatorLine();
$separator9 = new SeparatorLine();

$webviewControls = Build::vbox(
    new Label(
        "TreeView — collapsible file tree (opens in overlay child window):",
    ),
    Build::hbox(
        new Button("Open File Tree")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            if ($mainWindow === null) {
                return;
            }
            $tree = new TreeView($mainWindow, 300, 0, 480, 500, [
                [
                    "label" => "src",
                    "icon" => "folder",
                    "children" => [
                        ["label" => "index.php", "icon" => "code"],
                        ["label" => "style.css", "icon" => "file"],
                        ["label" => "app.js", "icon" => "code"],
                        [
                            "label" => "images",
                            "icon" => "folder",
                            "children" => [
                                ["label" => "logo.png", "icon" => "image"],
                                ["label" => "bg.jpg", "icon" => "image"],
                            ],
                        ],
                    ],
                ],
                [
                    "label" => "vendor",
                    "icon" => "folder",
                    "children" => [
                        ["label" => "autoload.php", "icon" => "code"],
                    ],
                ],
                ["label" => "composer.json", "icon" => "file"],
                ["label" => "README.md", "icon" => "file"],
            ]);
            $tree->onNodeClick(
                fn(string $path, array $node) => $outputLabel->setText(
                    "Tree clicked: {$path}",
                ),
            );
            $outputLabel->setText("File tree opened (right side of window)");
        }),
        Build::stretchy(new Label("")),
    ),
    $separator8->root(),
    new Label(
        "CodeEditor — highlight.js code editor (opens in overlay child window):",
    ),
    Build::hbox(
        new Button("Open Code Editor")->onClicked(function () use (
            &$mainWindow,
            $outputLabel,
        ): void {
            if ($mainWindow === null) {
                return;
            }
            $editor = new CodeEditor(
                $mainWindow,
                20,
                20,
                760,
                500,
                "php",
                false,
                "<?php\n\necho 'Hello, World!';\n\n\$data = ['foo' => 'bar'];\nforeach (\$data as \$k => \$v) {\n    print \"\$k: \$v\\n\";\n}\n",
            );
            $editor->onChange(
                fn(string $code) => $outputLabel->setText(
                    "Editor: " . mb_substr($code, 0, 40) . "...",
                ),
            );
            $outputLabel->setText("Code editor opened");
        }),
        Build::stretchy(new Label("")),
    ),
    $separator9->root(),
    new Label(
        "Note: WebView-based widgets open borderless child windows that float",
    ),
    new Label(
        "over the libui layout. They can be repositioned with autoResize().",
    ),
    Build::stretchy(new Label("")),
);

// ═════════════════════════════════════════════════════════════════════════════
// Window + Tab container
// ═════════════════════════════════════════════════════════════════════════════

$tab = new Tab();
$tab->appendMargined("Fields", $fieldsBox);
$tab->appendMargined("Custom", $toggleControls);
$tab->appendMargined("Dialogs", $dialogControls);
$tab->appendMargined("Pickers", $pickerControls);
$tab->appendMargined("Table", $tableControls);
$tab->appendMargined("WebView", $webviewControls);

// Window takes the tab layout + output label at bottom
$mainWindow = new Window("All Components — ui2 Demo", 800, 600, true);

// FilePickerField needs a Window reference — set up now
$filePickerField = new FilePickerField($mainWindow, "Browse…");
$fieldsGroup->append("File", $filePickerField);

$mainWindow->setChild(Build::vbox($tab, $outputLabel));

App::new()->window($mainWindow)->onShouldQuit(fn() => true)->run();
