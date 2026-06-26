<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Widgets;

use Libui\Window;
use Yangweijie\Ui2\WebView;

/**
 * A code editor widget built on WebView with syntax highlighting via highlight.js.
 *
 * Supports PHP, JavaScript, TypeScript, Python, HTML, CSS, JSON, SQL, XML,
 * Markdown, Java, C++, C, Rust, Go, YAML, and Bash.
 *
 * ```php
 * $editor = new CodeEditor($window, 0, 0, 600, 400, 'php', false, '<?php echo "Hello";');
 * $editor->onChange(fn (string $code) => print("Code changed: {$code}\n"));
 * // Get current code
 * echo $editor->getCode();
 * ```
 */
class CodeEditor extends WebView
{
    /** @var string Path to the code-editor HTML asset */
    private string $assetPath;

    /** @var string Current language */
    private string $language;

    /** @var string|null Initial code value */
    private ?string $initialValue;

    /**
     * Create an embedded code editor widget.
     *
     * @param Window     $window       Parent libui Window (must be shown first).
     * @param int        $x            X offset.
     * @param int        $y            Y offset.
     * @param int        $w            Width.
     * @param int        $h            Height.
     * @param string     $language     Initial language for syntax highlighting.
     * @param bool       $debug        Enable Web Inspector (default: false).
     * @param string|null $initialValue Initial code content.
     */
    public function __construct(
        Window  $window,
        int     $x = 0,
        int     $y = 0,
        int     $w = 600,
        int     $h = 400,
        string  $language = 'php',
        bool    $debug = false,
        ?string $initialValue = null,
    ) {
        $this->assetPath = \dirname(__DIR__, 2) . '/assets/code-editor.html';
        $this->language = $language;
        $this->initialValue = $initialValue;

        parent::__construct($window, $x, $y, $w, $h, $debug);

        $this->loadHtml();

        if ($this->initialValue !== null) {
            $this->setCode($this->initialValue);
        }

        $this->setLanguage($this->language);

        // Focus the editor once the page has loaded (after pending eval flush).
        $this->focus();
    }

    /**
     * Programmatically focus the editor textarea.
     *
     * Call this after the parent window is shown if auto-focus didn't work,
     * or to restore focus after another widget was interacted with.
     *
     * @return $this
     */
    public function focus(): static
    {
        $this->eval('document.getElementById("codeEditor").focus();');
        return $this;
    }

    /**
     * Set the editor content.
     *
     * @return $this
     */
    public function setCode(string $code): static
    {
        $this->eval('window.__setCode(' . \json_encode($code) . ');');
        return $this;
    }

    /**
     * Get the current editor content via JS eval.
     *
     * Note: due to FFI limitations, this uses webview_eval which dispatches
     * asynchronously. For reactive updates, use onChange() callback.
     *
     * @return string|null The current code content, or null on failure.
     */
    public function getCode(): ?string
    {
        // Best-effort read. For production, use onChange() binding.
        try {
            return $this->eval('window.__getCode();');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Set the syntax highlighting language.
     *
     * @return $this
     */
    public function setLanguage(string $language): static
    {
        $this->language = $language;
        $this->eval('window.__setLanguage(' . \json_encode($language) . ');');
        return $this;
    }

    /**
     * Get the current language.
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Register a callback for when the editor content changes.
     *
     * The callback receives (string $code).
     *
     * @return $this
     */
    public function onChange(callable $handler): static
    {
        $this->bind('__editorChange', function (string $id, string $req) use ($handler): void {
            $data = \json_decode($req, true);
            $code = $data['code'] ?? '';
            $handler($code);
            $this->return($id, 0, '{}');
        });

        $this->eval(<<<'JS'
window.__onCodeChange = function(code) {
    window.__editorChange(JSON.stringify({code: code}));
};
JS
        );

        return $this;
    }

    /**
     * Load the code-editor HTML into the WebView.
     */
    private function loadHtml(): void
    {
        if (!\file_exists($this->assetPath)) {
            throw new \RuntimeException(
                'CodeEditor asset not found at ' . $this->assetPath,
            );
        }

        $html = \file_get_contents($this->assetPath);
        $this->setHtml($html);
    }
}