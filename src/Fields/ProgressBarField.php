<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Fields;

use Libui\Box;
use Libui\Control;
use Libui\Label;
use Libui\ProgressBar;
use Yangweijie\Ui2\Composite;

/**
 * A labelled progress bar — Label + ProgressBar in a horizontal row.
 *
 * The progress bar is read-only; its value is set programmatically.
 * Use the indeterminate state for ongoing operations with unknown duration.
 *
 * ```php
 * $progress = new ProgressBarField('Downloading:');
 * $progress->setProgress(50);  // 0..100
 * $progress->indeterminate();  // pulsing animation
 * ```
 */
class ProgressBarField extends Composite
{
    private readonly Label $label;
    private readonly ProgressBar $progressBar;
    private readonly Box $box;

    public function __construct(string $labelText, int $initialValue = 0)
    {
        $this->label = new Label($labelText);
        $this->progressBar = new ProgressBar();
        $this->progressBar->setValue($initialValue);

        $this->box = Box::horizontal(padded: true);
        $this->box->append($this->label);
        $this->box->append($this->progressBar);
    }

    public function root(): Control
    {
        return $this->box;
    }

    /**
     * Set the progress value (0–100).
     *
     * @return $this
     */
    public function setProgress(int $value): static
    {
        $this->progressBar->setValue($value);
        return $this;
    }

    /**
     * Switch to indeterminate (pulsing) mode.
     *
     * libui-ng uses -1 to signal indeterminate progress.
     *
     * @return $this
     */
    public function indeterminate(): static
    {
        $this->progressBar->setValue(-1);
        return $this;
    }

    /**
     * Switch back to determinate mode at the given percentage.
     *
     * @return $this
     */
    public function determinate(int $value = 0): static
    {
        $this->progressBar->setValue($value);
        return $this;
    }
}
