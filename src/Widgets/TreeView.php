<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Window;
use Yangweijie\Ui2\WebView;

/**
 * A tree/file browser widget built on WebView with collapsible nodes.
 *
 * ```php
 * $tree = new TreeView($window, 0, 0, 280, 500, [
 *     ['label' => 'src', 'children' => [
 *         ['label' => 'index.php', 'icon' => 'code'],
 *         ['label' => 'style.css', 'icon' => 'file'],
 *     ]],
 * ]);
 * $tree->onNodeClick(fn (string $path, array $node) => print("Clicked: {$path}"));
 * ```
 */
class TreeView extends WebView
{
    /** @var string Path to the tree-view HTML asset */
    private string $assetPath;

    /** @var array|null Cached node data for re-injection */
    private ?array $treeData = null;

    /**
     * Create an embedded tree view widget.
     *
     * @param Window $window    Parent libui Window (must be shown first).
     * @param int    $x         X offset.
     * @param int    $y         Y offset.
     * @param int    $w         Width.
     * @param int    $h         Height.
     * @param array  $treeData  Initial tree data structure.
     * @param bool   $debug     Enable Web Inspector (default: false).
     *
     * @see setData() for the expected array format.
     */
    public function __construct(
        Window $window,
        int    $x = 0,
        int    $y = 0,
        int    $w = 280,
        int    $h = 500,
        array  $treeData = [],
        bool   $debug = false,
    ) {
        $this->assetPath = \dirname(__DIR__, 2) . '/assets/tree-view.html';
        $this->treeData = $treeData;

        parent::__construct($window, $x, $y, $w, $h, $debug);

        $this->loadHtml();
    }

    /**
     * Set the tree data and re-render.
     *
     * Expected format:
     * ```php
     * [
     *     ['label' => 'Folder', 'icon' => 'folder', 'children' => [
     *         ['label' => 'File.php', 'icon' => 'code'],
     *     ]],
     * ]
     * ```
     *
     * Icons: 'folder', 'file', 'image', 'code', 'default'
     *
     * @return $this
     */
    public function setData(array $data): static
    {
        $this->treeData = $data;
        $this->eval('window.__setTreeData(' . \json_encode($data) . ');');
        return $this;
    }

    /**
     * Expand a node by its dot-separated path (e.g. "src.Controllers").
     *
     * @return $this
     */
    public function expandNode(string $path): static
    {
        $this->eval('window.__expandNode(' . \json_encode($path) . ');');
        return $this;
    }

    /**
     * Collapse a node by its dot-separated path.
     *
     * @return $this
     */
    public function collapseNode(string $path): static
    {
        $this->eval('window.__collapseNode(' . \json_encode($path) . ');');
        return $this;
    }

    /**
     * Get the currently selected node path via JS eval.
     *
     * @return string|null The selected path, or null on error.
     */
    public function getSelectedPath(): ?string
    {
        // This is a best-effort read; for reactive updates use onNodeClick().
        try {
            return $this->eval('window.__getSelectedPath();');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Register a callback for when a tree node is clicked.
     *
     * The callback receives (string $path, array $nodeData).
     *
     * @return $this
     */
    public function onNodeClick(callable $handler): static
    {
        $this->bind('__treeNodeClick', function (string $id, string $req) use ($handler): void {
            $data = \json_decode($req, true);
            if (\is_array($data) && isset($data['path'])) {
                $handler($data['path'], $data['node'] ?? []);
            }
            $this->return($id, 0, '{}');
        });

        $this->eval(<<<'JS'
window.__onNodeClick = function(path, node) {
    window.__treeNodeClick(JSON.stringify({path: path, node: node}));
};
JS
        );

        return $this;
    }

    /**
     * Register a callback for when a tree node is expanded or collapsed.
     *
     * The callback receives (string $path, bool $isExpanded, array $nodeData).
     *
     * @return $this
     */
    public function onNodeToggle(callable $handler): static
    {
        $this->bind('__treeNodeToggle', function (string $id, string $req) use ($handler): void {
            $data = \json_decode($req, true);
            if (\is_array($data)) {
                $handler($data['path'] ?? '', $data['expanded'] ?? false, $data['node'] ?? []);
            }
            $this->return($id, 0, '{}');
        });

        $this->eval(<<<'JS'
window.__onNodeToggle = function(path, expanded, node) {
    window.__treeNodeToggle(JSON.stringify({path: path, expanded: expanded, node: node}));
};
JS
        );

        return $this;
    }

    /**
     * Load the tree-view HTML into the WebView.
     */
    private function loadHtml(): void
    {
        if (!\file_exists($this->assetPath)) {
            throw new \RuntimeException(
                'TreeView asset not found at ' . $this->assetPath,
            );
        }

        $html = \file_get_contents($this->assetPath);

        if (!empty($this->treeData)) {
            // Inject tree data into the HTML as JSON
            $dataJson = \json_encode($this->treeData);
            $html = \str_replace(
                'window.__treeData = window.__treeData || [];',
                "window.__treeData = {$dataJson};",
                $html,
            );
        }

        $this->setHtml($html);
    }
}