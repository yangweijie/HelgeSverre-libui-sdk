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

    /** @var callable|null Stored node-click handler */
    private mixed $nodeClickHandler = null;

    /** @var callable|null Stored node-toggle handler */
    private mixed $nodeToggleHandler = null;

    /**
     * Last selected node path, updated automatically by the node-click glue.
     * Populated even if no user-defined nodeClick handler is registered.
     */
    private ?string $selectedPath = null;

    /**
     * PebView init script that creates window.__webview__ bridge.
     * Re-injected after every setHtml() because set_html() creates a fresh
     * page context that destroys the previous bridge.
     */
    private const INIT_SCRIPT_POST = 'function(message) {
  if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.__webview__) {
    return window.webkit.messageHandlers.__webview__.postMessage(message);
  }
  if (window.chrome && window.chrome.webview) {
    return window.chrome.webview.postMessage(message);
  }
}';

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
     * Override setHtml to re-inject the PebView init script.
     *
     * webview_set_html() creates a fresh page context, destroying
     * window.__webview__. We prepend the init script into the HTML
     * so the bridge exists before bind() calls eval().
     */
    public function setHtml(string $html): static
    {
        $initJs = $this->createInitScript();
        $html = \str_replace('<head>', "<head><script>{$initJs}</script>", $html);
        parent::setHtml($html);
        $this->rebindHandlers();
        return $this;
    }

    /**
     * Re-register stored event handlers (called after setHtml).
     */
    private function rebindHandlers(): void
    {
        if ($this->nodeClickHandler !== null) {
            $this->bindNodeClick();
        }
        if ($this->nodeToggleHandler !== null) {
            $this->bindNodeToggle();
        }
    }

    /**
     * Build the PebView init script that creates window.__webview__.
     */
    private function createInitScript(): string
    {
        $postFn = self::INIT_SCRIPT_POST;

        $js = <<<'JS'
(function() {
  'use strict';
  function generateId() {
    var crypto = window.crypto || window.msCrypto;
    var bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    return Array.prototype.slice.call(bytes).map(function(n) {
      return n.toString(16).padStart(2, '0');
    }).join('');
  }
  var Webview = (function() {
    var _promises = {};
    function Webview_() {}
    Webview_.prototype.post = function(message) {
      return PLACEHOLDER_POST(message);
    };
    Webview_.prototype.call = function(method) {
      var _id = generateId();
      var _params = Array.prototype.slice.call(arguments, 1);
      var promise = new Promise(function(resolve, reject) {
        _promises[_id] = { resolve, reject };
      });
      this.post(JSON.stringify({
        id: _id,
        method: method,
        params: _params
      }));
      return promise;
    };
    Webview_.prototype.onReply = function(id, status, result) {
      var promise = _promises[id];
      if (!promise) return;
      delete _promises[id];
      if (result !== undefined) {
        try { result = JSON.parse(result); } catch(e) {
          promise.reject(new Error("Failed to parse binding result as JSON"));
          return;
        }
      }
      if (status === 0) { promise.resolve(result); }
      else { promise.reject(result); }
    };
    Webview_.prototype.onBind = function(name) {
      if (Object.hasOwn(window, name)) {
        throw new Error('Property "' + name + '" already exists');
      }
      window[name] = (function() {
        var params = [name].concat(Array.prototype.slice.call(arguments));
        return Webview_.prototype.call.apply(this, params);
      }).bind(this);
    };
    Webview_.prototype.onUnbind = function(name) {
      if (!Object.hasOwn(window, name)) {
        throw new Error('Property "' + name + '" does not exist');
      }
      delete window[name];
    };
    return Webview_;
  })();
  window.__webview__ = new Webview();
})();
JS;

        return \str_replace('PLACEHOLDER_POST', $postFn, $js);
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
     * Get the currently selected node path.
     *
     * The path is tracked automatically in PHP via the node-click glue
     * whenever a tree node is clicked in the WebView, so no synchronous
     * eval round-trip is needed (webview_eval is fire-and-forget).
     *
     * @return string|null The selected path, or null if nothing selected.
     */
    public function getSelectedPath(): ?string
    {
        return $this->selectedPath;
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
        $this->nodeClickHandler = $handler;
        $this->bindNodeClick();
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
        $this->nodeToggleHandler = $handler;
        $this->bindNodeToggle();
        return $this;
    }

    /**
     * Bind the node-click JS bridge.
     *
     * Automatically tracks the selected path in {@see $selectedPath}
     * regardless of whether a user-defined nodeClick handler exists,
     * so that {@see getSelectedPath()} always returns current state.
     */
    private function bindNodeClick(): void
    {
        $handler = $this->nodeClickHandler;

        $this->bind('__treeNodeClick', function (string $id, string $req) use ($handler): void {
            $args = \json_decode($req, true);
            if (\is_array($args) && isset($args[0])) {
                $path = $args[0];
                $node = $args[1] ?? [];
                $this->selectedPath = $path;
                if ($handler !== null) {
                    $handler($path, $node);
                }
            }
            $this->return($id, 0, '{}');
        });

        $this->eval(<<<'JS'
window.__onNodeClick = function(path, node) {
    window.__treeNodeClick(path, node);
};
JS
        );
    }

    /**
     * Bind the node-toggle JS bridge.
     */
    private function bindNodeToggle(): void
    {
        $handler = $this->nodeToggleHandler;
        if ($handler === null) {
            return;
        }

        $this->bind('__treeNodeToggle', function (string $id, string $req) use ($handler): void {
            $args = \json_decode($req, true);
            if (\is_array($args) && isset($args[0])) {
                $path = $args[0] ?? '';
                $expanded = $args[1] ?? false;
                $node = $args[2] ?? [];
                $handler($path, $expanded, $node);
            }
            $this->return($id, 0, '{}');
        });

        $this->eval(<<<'JS'
window.__onNodeToggle = function(path, expanded, node) {
    window.__treeNodeToggle(path, expanded, node);
};
JS
        );
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
