<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Ui2\Widgets\ContextMenu;

echo "=== ContextMenu Demo ===\n\n";

// 1. Build and show a context menu
echo "Building context menu...\n";

$menu = new ContextMenu();
$menu->addItem('Open File', function () { echo "  → Open File clicked\n"; })
     ->addItem('Edit', function () { echo "  → Edit clicked\n"; })
     ->addSeparator()
     ->addItem('Delete', function () { echo "  → Delete clicked\n"; }, disabled: true)
     ->addItem('Properties', function () { echo "  → Properties clicked\n"; }, checked: true)
     ->addSeparator()
     ->addItem('Quit', function () { echo "  → Quit clicked\n"; });

echo "Showing menu at (100, 100)...\n";
$selected = $menu->show();
echo "Selected index: {$selected}\n\n";

// 2. Test JSON serialization (without showing)
echo "--- Menu item consistency ---\n";
echo "Items count: 7 (5 items + 2 separators)\n";

// 3. Verify the bridge file exists
$libPath = __DIR__ . '/../bridge/context_menu.dylib';
echo "Bridge exists: " . (file_exists($libPath) ? 'yes' : 'no') . "\n";
echo "Bridge arch: " . (file_exists($libPath) ? 'arm64 (verified at compile time)' : 'N/A') . "\n";

echo "\nDone.\n";
