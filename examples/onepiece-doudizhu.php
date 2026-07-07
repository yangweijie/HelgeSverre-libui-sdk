<?php

declare(strict_types=1);

/**
 * 海贼王·斗地主 (One Piece Dou Dizhu)
 * ────────────────────────────────────────────────────────────────────────
 * 基于三大势力（海军本部 / 王下七武海 / 四皇）的斗地主，融入三国百将牌的
 * 角色技能机制。内置 AI 对手（Yangweijie\Ui2\Games\OnePieceDoudizhu\Ai）与
 * 程序化生成的音效（assets/audio/*.wav，由 scripts/gen-sfx.php 生成）。
 *
 * 界面采用 libui Area 自绘（海洋渐变背景、势力配色卡牌、对手面板、出牌区、
 * 可点击手牌），配合原生按钮完成叫分 / 出牌 / 不出 / 提示 / 技能 / 反击。
 *
 * 游戏控制器逻辑位于 src/Games/OnePieceDoudizhu/GameController.php（PSR-4）。
 *
 * 运行： php85 examples/onepiece-doudizhu.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Libui\App;
use Libui\Area;
use Libui\AreaDelegate;
use Libui\Build;
use Libui\Button;
use Libui\Label;
use Libui\Window;
use Libui\Draw\DrawContext;
use Libui\Draw\Params\AreaDrawParams;
use Libui\Draw\Params\AreaKeyEvent;
use Libui\Draw\Params\AreaMouseEvent;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\GameController;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Sound;
use Yangweijie\Ui2\System\Audio;

/* ============================== Assembly ============================== */

$ctrl = new GameController();

$delegate = new class($ctrl) extends AreaDelegate {
    public function __construct(private GameController $c)
    {
    }

    public function draw(DrawContext $ctx, AreaDrawParams $p): void
    {
        $this->c->render($ctx, $p);
    }

    public function mouse(AreaMouseEvent $e): void
    {
        $this->c->onClick($e);
    }

    public function key(AreaKeyEvent $e): bool
    {
        return $this->c->onKey($e);
    }
};

$area = new Area($delegate);
$ctrl->area = $area;

$status = new Label('选择你的阵营与将领');
$ctrl->status = $status;

$btnSound = new Button('🔊 音效');
$btnSound->onClicked(static function () use ($ctrl): void {
    $ctrl->toggleSound();
});
$ctrl->btnSound = $btnSound;

$actBtns = [];
for ($i = 0; $i < 6; $i++) {
    $btn = new Button('');
    $idx = $i;
    $btn->onClicked(function () use ($ctrl, $idx): void {
        $cb = $ctrl->actCb[$idx] ?? null;
        if ($cb !== null) {
            $cb();
        }
    });
    $btn->disable();
    $actBtns[] = $btn;
}
$ctrl->actBtns = $actBtns;

$window = new Window('海贼王 · 斗地主', 960, 760, false);
$window->setMargined(true);
$window->centered();

$topBar = Build::hbox($status, Build::stretchy(new Label('')), $btnSound);
// 布局：[重新选将] ──── spacer ──── [主操作按钮居中] ──── spacer
$actionBar = Build::hbox(
    $actBtns[0],                                              // 重新选将（最左）
    Build::stretchy(new Label('')),                           // 左弹性占位
    $actBtns[1], $actBtns[2], $actBtns[3], $actBtns[4], $actBtns[5], // 主按钮
    Build::stretchy(new Label(''))                            // 右弹性占位
);
$window->setChild(Build::vbox($topBar, Build::stretchy($area), $actionBar));

try {
    Audio::init();
} catch (\Throwable) {
    // 无音频设备时静默降级
}

$ctrl->newGame();

App::new()
    ->window($window)
    ->onShouldQuit(function () use ($ctrl): bool {
        $ctrl->cancelTimers();
        Sound::instance()->unload();
        try {
            Audio::shutdown();
        } catch (\Throwable) {
        }

        return true;
    })
    ->run();
