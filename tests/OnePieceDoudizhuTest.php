<?php

declare(strict_types=1);

use Yangweijie\Ui2\Games\OnePieceDoudizhu\Ai;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Card;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Character;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Combo;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Deck;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Faction;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Game;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\GameController;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\PlayerState;
use Yangweijie\Ui2\Games\OnePieceDoudizhu\Sound;
use Libui\Draw\Params\AreaMouseEvent;

/* ============================ helpers ============================ */

function C(int $r, string $s = '♠'): Card
{
    return new Card($r, $s);
}

function opdMakeGame(string $c0, string $c1, string $c2, array $h0, array $h1, array $h2): Game
{
    $mk = static function (string $cid, array $cards): PlayerState {
        $ps = new PlayerState($cards);
        $ps->characterId = $cid;
        $ps->faction = Character::byId($cid)->faction;
        $ps->initSkill(Character::byId($cid)->skill());

        return $ps;
    };

    return new Game([$mk($c0, $h0), $mk($c1, $h1), $mk($c2, $h2)], []);
}

/* ============================ combos ============================ */

test('card hit-test respects draw order (topmost wins overlap)', function () {
    // 触发 GameController.php 自动加载（该文件同时定义了 hitTopmost 全局函数）
    class_exists(GameController::class);
    // 三张手牌按从左到右顺序绘制，每张宽 58、步进 30 => 相邻重叠 28px
    // 视觉上后绘制的牌叠在先绘制牌之上，重叠区点中后者
    $rects = [
        'A' => [0, 0, 58, 84],
        'B' => [30, 0, 58, 84],
        'C' => [60, 0, 58, 84],
    ];
    $hit = \Yangweijie\Ui2\Games\OnePieceDoudizhu\hitTopmost(...);
    // 点 A 露出的左半部分 -> 选 A
    expect($hit($rects, 15, 40))->toBe('A');
    // 点 A/B 重叠区（B 盖住 A 的部分，x=40）-> 选 B
    expect($hit($rects, 40, 40))->toBe('B');
    // 点 B/C 重叠区（C 盖住 B 的部分，x=70）-> 选 C
    expect($hit($rects, 70, 40))->toBe('C');
    // 点范围之外 -> 空
    expect($hit($rects, 200, 40))->toBeNull();
});

test('combo recognizes basic shapes', function () {
    expect(Combo::parse([C(5)])?->type)->toBe('single');
    expect(Combo::parse([C(7, '♠'), C(7, '♥')])?->type)->toBe('pair');
    expect(Combo::parse([C(8, '♠'), C(8, '♥'), C(8, '♦')])?->type)->toBe('triple');
    $straight = Combo::parse([C(3), C(4), C(5), C(6), C(7)]);
    expect($straight?->type)->toBe('straight');
    $plane = Combo::parse([C(3), C(3), C(3), C(4), C(4), C(4)]);
    expect($plane?->type)->toBe('plane');
});

test('combo recognizes bombs and rockets', function () {
    $bomb = Combo::parse([C(9, '♠'), C(9, '♥'), C(9, '♦'), C(9, '♣')]);
    expect($bomb?->isBomb)->toBeTrue();
    $rocket = Combo::parse([Card::smallJoker(), Card::bigJoker()]);
    expect($rocket?->isRocket)->toBeTrue();
});

test('combo beats hierarchy', function () {
    $bomb = Combo::parse([C(9, '♠'), C(9, '♥'), C(9, '♦'), C(9, '♣')]);
    $rocket = Combo::parse([Card::smallJoker(), Card::bigJoker()]);
    expect(Combo::beats($bomb, Combo::parse([C(7)])))->toBeTrue();
    expect(Combo::beats($rocket, $bomb))->toBeTrue();
    expect(Combo::beats(Combo::parse([C(7)]), $bomb))->toBeFalse();
});

/* ============================ characters ============================ */

test('roster has 9 characters across 3 factions', function () {
    $all = Character::all();
    expect(\count($all))->toBe(9);

    $factions = [];
    foreach ($all as $c) {
        $factions[$c->faction] = ($factions[$c->faction] ?? 0) + 1;
    }
    expect($factions)->toHaveKeys([Faction::NAVY, Faction::WARLORD, Faction::EMPEROR]);
    expect(\array_sum($factions))->toBe(9);

    // 每名角色必须配齐技能
    foreach ($all as $c) {
        expect($c->skill())->toBeInstanceOf(\Yangweijie\Ui2\Games\OnePieceDoudizhu\Skill::class);
    }
});

/* ============================ engine ============================ */

test('bidding decides a landlord', function () {
    $g = opdMakeGame('garp', 'mihawk', 'shanks',
        [C(5), C(6), C(7)], [C(8), C(9), C(10)], [C(11), C(12), C(13)]);
    $res = $g->bid(0, 3);
    expect($res)->toBe('started');
    expect($g->landlord)->toBe(0);
    expect($g->phase)->toBe('playing');
});

test('play advances turn and pass skips', function () {
    $g = opdMakeGame('garp', 'mihawk', 'shanks',
        [C(5), C(6), C(7)], [C(8), C(9), C(10)], [C(11), C(12), C(13)]);
    $g->bid(0, 3);
    $g->turn = 0;
    $g->play(0, [C(5)]);
    expect($g->turn)->toBe(1);
    $g->pass(1);
    expect($g->turn)->toBe(2);
    $g->pass(2);
    // 一圈过完，回到出牌方且 trick 清空
    expect($g->turn)->toBe(0);
    expect($g->lastPlay)->toBeNull();
});

test('emptying a hand ends the game with a winner', function () {
    $g = opdMakeGame('garp', 'mihawk', 'shanks',
        [C(5)], [C(8), C(9), C(10)], [C(11), C(12), C(13)]);
    $g->bid(0, 3);
    $g->turn = 0;
    $g->play(0, [C(5)]);
    expect($g->isOver())->toBeTrue();
    expect($g->winner)->toBe(0);
    expect($g->winnerSide)->toBe('landlord');
});

/* ============================ skills ============================ */

test('garp shock skips the next opponent', function () {
    $g = opdMakeGame('garp', 'mihawk', 'shanks',
        [C(5), C(6), C(7)], [C(8), C(9), C(10)], [C(11), C(12), C(13)]);
    $g->bid(0, 3);
    $g->turn = 0;
    $g->armSkill(0);
    $g->play(0, [C(5)]);
    expect($g->lastPlay)->not()->toBeNull();
    expect($g->turn)->toBe(2); // p1 被震飞跳过
});

test('shanks haki blocks non-bomb follow but not bombs', function () {
    $g = opdMakeGame('shanks', 'garp', 'mihawk',
        [C(5), C(6), C(7)], [C(8), C(9), C(10)], [C(11), C(12), C(13)]);
    $g->bid(0, 3);
    $g->turn = 0;
    $g->armSkill(0);
    $g->play(0, [C(5)]);
    expect($g->lastPlay['hakiActive'] ?? false)->toBeTrue();

    $blocked = false;
    try {
        $g->play(1, [C(13)]);
    } catch (\Throwable) {
        $blocked = true;
    }
    expect($blocked)->toBeTrue();

    // 独立小局：炸弹仍可压
    $g2 = opdMakeGame('shanks', 'garp', 'mihawk',
        [C(5), C(6), C(7)],
        [C(8, '♠'), C(8, '♥'), C(8, '♦'), C(8, '♣'), C(9)],
        [C(11), C(12), C(13)]);
    $g2->bid(0, 3);
    $g2->turn = 0;
    $g2->armSkill(0);
    $g2->play(0, [C(5)]);
    $g2->turn = 1;
    $g2->play(1, [C(8, '♠'), C(8, '♥'), C(8, '♦'), C(8, '♣')]);
    expect($g2->lastPlay['combo']->isBomb)->toBeTrue();
});

test('akainu counter returns the bomb to its owner', function () {
    $g = opdMakeGame('akainu', 'shanks', 'garp',
        [C(5), C(6)],
        [C(11, '♠'), C(11, '♥'), C(11, '♦'), C(11, '♣'), C(9), C(10), C(3), C(4)],
        [C(7), C(8)]);
    $g->bid(1, 3); // shanks 地主
    $g->turn = 1;
    $g->play(1, [C(9)]);
    $g->turn = 2; $g->pass(2);
    $g->turn = 0; $g->pass(0);
    $g->turn = 1;
    $g->play(1, [C(10)]);
    $g->turn = 2; $g->pass(2);
    $g->turn = 0; $g->pass(0);
    $g->turn = 1;
    $g->play(1, [C(11, '♠'), C(11, '♥'), C(11, '♦'), C(11, '♣')]);
    expect($g->lastPlay['combo']->isBomb)->toBeTrue();

    $before = $g->handCount(1);
    $g->counterBomb(0);
    expect($g->lastPlay)->toBeNull();
    expect($g->turn)->toBe(0);
    expect($g->handCount(1))->toBe($before + 4);
});

test('aokiji freeze and bigmom steal modify hands', function () {
    $gf = opdMakeGame('aokiji', 'boa', 'kuma', [C(5), C(6)], [C(7), C(8)], [C(9), C(10)]);
    $gf->bid(0, 3);
    $gf->turn = 0;
    $gf->armSkill(0, 1);
    expect($gf->mods['frozen'][1])->toBe(1);

    $gm = opdMakeGame('bigmom', 'boa', 'kuma', [C(5), C(6)], [C(7), C(8)], [C(9), C(10)]);
    $gm->bid(0, 3);
    $h0 = $gm->handCount(0);
    $h1 = $gm->handCount(1);
    $gm->armSkill(0, 1);
    expect($gm->handCount(0))->toBe($h0 + 1);
    expect($gm->handCount(1))->toBe($h1 - 1);
});

/* ============================ AI self-play ============================ */

test('100-game AI self-play always finishes', function () {
    $chars = \array_map(static fn (Character $c) => $c->id, Character::all());
    $wins = ['landlord' => 0, 'peasant' => 0];
    $unfinished = 0;
    $maxTurns = 0;
    $games = 100;

    $setup = static function (int $seed) use ($chars): Game {
        $deal = Deck::deal();
        $players = [];
        for ($i = 0; $i < 3; $i++) {
            $ps = new PlayerState($deal['hands'][$i]);
            $ps->characterId = $chars[($seed + $i) % 9];
            $ps->faction = Character::byId($ps->characterId)->faction;
            $ps->initSkill(Character::byId($ps->characterId)->skill());
            $players[] = $ps;
        }

        return new Game($players, $deal['bottom']);
    };

    for ($gi = 0; $gi < $games; $gi++) {
        $g = $setup($gi);
        $started = false;
        $attempts = 0;
        while (!$started && $attempts < 10) {
            $attempts++;
            foreach ([0, 1, 2] as $p) {
                $r = $g->bid($p, Ai::bid($g->players[$p]->hand));
                if ($r === 'redeal') {
                    $g = $setup($gi + $attempts * 1000);
                    continue 2;
                }
                if ($r === 'started') {
                    $started = true;
                    break;
                }
            }
        }
        if (!$started) {
            $unfinished++;
            continue;
        }

        $turns = 0;
        while (!$g->isOver() && $turns < 4000) {
            $p = $g->turn;
            if ($g->lastPlay !== null && $g->lastPlay['combo']->isBomb) {
                $bomber = $g->lastPlay['player'];
                $countered = false;
                foreach ([0, 1, 2] as $q) {
                    if ($q === $bomber) {
                        continue;
                    }
                    if (Ai::maybeCounter($g, $q)) {
                        $countered = true;
                        break;
                    }
                }
                if ($countered) {
                    $turns++;
                    continue;
                }
            }
            Ai::act($g, $p);
            $turns++;
        }

        if ($g->isOver()) {
            $wins[$g->winnerSide]++;
            $maxTurns = \max($maxTurns, $turns);
        } else {
            $unfinished++;
        }
    }

    expect($unfinished)->toBe(0); // 没有任何一局卡死
    expect($wins['landlord'] + $wins['peasant'])->toBe($games - $unfinished);
    expect($maxTurns)->toBeLessThan(4000);
});

/* ============================ sound ============================ */

test('sound resolves the audio directory and maps events', function () {
    $s = Sound::instance();
    $s->setEnabled(false); // 不触碰音频后端
    expect($s->directory())->toContain('assets/audio');
    expect($s->pathFor(Sound::CLICK))->not()->toBeNull();
    expect($s->pathFor('nonexistent'))->toBeNull();
    // 事件绑定映射
    $s->trigger('bomb'); // 关闭状态下应为 no-op，不抛异常
    expect($s->isEnabled())->toBeFalse();
});

test('sound plays through the audio backend when enabled', function () {
    $s = Sound::instance();
    try {
        // 探测后端是否可用
        $probe = $s->pathFor(Sound::CLICK);
        if ($probe === null) {
            $this->markTestSkipped('No SFX files generated');
        }
        // 直接尝试加载一个真实 wav，失败则跳过
        \Yangweijie\Ui2\System\Audio::load($probe)->unload();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Audio backend unavailable: ' . $e->getMessage());
    }

    $s->setEnabled(true);
    $s->trigger(Sound::CLICK);
    $s->setEnabled(false);
    expect(true)->toBeTrue();
});

/* ============================ controller (headless) ============================ */

test('game controller drives a full match headlessly for every character', function () {
    Sound::instance()->setEnabled(false);

    $mockBtn = new class {
        public function setText(string $t): void {}
        public function enable(): void {}
        public function disable(): void {}
    };

    $scenarios = ['shanks', 'akainu', 'aokiji', 'boa', 'garp'];
    foreach ($scenarios as $char) {
        $ctrl = new GameController();
        $ctrl->area = null;
        $ctrl->status = null;
        $ctrl->actBtns = \array_fill(0, 6, $mockBtn);

        $ctrl->startMatch($char);
        expect($ctrl->mode)->toBe('bid');

        $ctrl->humanBid(3);
        expect($ctrl->mode)->toBe('play');
        expect($ctrl->game->landlord)->toBe($ctrl->human);

        $guard = 0;
        $usedSkill = false;
        while ($ctrl->mode !== 'over' && $guard < 5000) {
            $guard++;
            if ($ctrl->game->isOver()) {
                break;
            }
            if ($ctrl->game->turn === $ctrl->human) {
                if (!$usedSkill && $ctrl->game->canUse(
                    $ctrl->human,
                    Character::byId($char)->skill()
                )) {
                    $ctrl->useSkill();
                    $usedSkill = true;
                    if ($ctrl->pendingTarget !== null) {
                        $ctrl->armWithTarget(1);
                    }
                }
                $moves = $ctrl->game->legalMoves($ctrl->human);
                if ($ctrl->game->lastPlay === null || $moves !== []) {
                    $mv = $ctrl->game->lastPlay === null
                        ? Ai::pickLead($ctrl->game, $ctrl->human, $moves)
                        : Ai::pickFollow($ctrl->game, $ctrl->human, $moves);
                    if ($mv !== null) {
                        $ctrl->selected = \array_map(static fn (Card $c) => $c->id(), $mv->cards);
                        $ctrl->humanPlay();
                    } else {
                        $ctrl->humanPass();
                    }
                } else {
                    $ctrl->humanPass();
                }
            } else {
                $ctrl->aiStep();
            }
        }

        expect($ctrl->mode)->toBe('over');
        expect($ctrl->game->winnerSide)->toBeIn(['landlord', 'peasant']);
    }
});

/* ============================ auto-play (托管) ============================ */

test('auto-play toggle works', function () {
    Sound::instance()->setEnabled(false);
    $ctrl = new GameController();
    expect($ctrl->autoPlay)->toBeFalse();
    $ctrl->autoPlay = true;
    expect($ctrl->autoPlay)->toBeTrue();
    $ctrl->toggleAutoPlay();
    expect($ctrl->autoPlay)->toBeFalse();
    $ctrl->newGame();
    expect($ctrl->autoPlay)->toBeFalse();
});

/* ============================ 拖拽选牌 ============================ */

/**
 * 构造一个 AreaMouseEvent。
 * @param int $down  按下的按钮（1=左）
 * @param int $up    松开的按钮（1=左）
 * @param int $held  当前按住的按钮掩码（1=左）
 */
function opdMouse(float $x, float $y, int $down = 0, int $up = 0, int $held = 0): AreaMouseEvent
{
    return new AreaMouseEvent($x, $y, 960.0, 760.0, $down, $up, 1, 0, $held);
}

test('drag-select adds cards, drag from selected removes them', function () {
    Sound::instance()->setEnabled(false);
    $mockBtn = new class {
        public function setText(string $t): void {}
        public function enable(): void {}
        public function disable(): void {}
    };
    $ctrl = new GameController();
    $ctrl->area = null;
    $ctrl->status = null;
    $ctrl->actBtns = \array_fill(0, 6, $mockBtn);
    $ctrl->startMatch('akainu');
    $ctrl->humanBid(3); // 人类当地主，先手出牌
    expect($ctrl->mode)->toBe('play');
    expect($ctrl->game->turn)->toBe($ctrl->human);
    expect($ctrl->game->phase)->toBe('playing');

    // 手动布置手牌命中矩形（id 与 selected 对应），牌宽 68、步进 36
    $ctrl->hit['hand'] = [
        'h1' => [0, 600, 68, 90],
        'h2' => [36, 600, 68, 90],
        'h3' => [72, 600, 68, 90],
        'h4' => [108, 600, 68, 90],
    ];
    $ctrl->selected = [];

    // 在 h1 上按下（未选中 -> 连选模式），依次拖过 h2/h3/h4
    $ctrl->onClick(opdMouse(34, 645, down: 1, held: 1));   // 按下 h1
    expect($ctrl->selected)->toBe(['h1']);
    $ctrl->onClick(opdMouse(70, 645, down: 0, held: 1));   // 拖到 h2
    $ctrl->onClick(opdMouse(106, 645, down: 0, held: 1));  // 拖到 h3
    $ctrl->onClick(opdMouse(142, 645, down: 0, held: 1));  // 拖到 h4
    expect($ctrl->selected)->toBe(['h1', 'h2', 'h3', 'h4']);
    // 松开，结束拖拽（第二次按下能正确识别 h4 已选中并进入连消模式，
    // 即证明本次拖拽已正确结束并可开启新拖拽）
    $ctrl->onClick(opdMouse(142, 645, down: 0, up: 1, held: 0));

    // 从已选的 h4 上按下（已选中 -> 连消模式），拖回 h3/h2
    $ctrl->onClick(opdMouse(142, 645, down: 1, held: 1));  // 按下 h4
    expect($ctrl->selected)->toBe(['h1', 'h2', 'h3']);
    $ctrl->onClick(opdMouse(106, 645, down: 0, held: 1));  // 拖到 h3
    $ctrl->onClick(opdMouse(70, 645, down: 0, held: 1));   // 拖到 h2
    expect($ctrl->selected)->toBe(['h1']);                  // 只剩 h1
    $ctrl->onClick(opdMouse(70, 645, down: 0, up: 1, held: 0)); // 松开
});

test('single click still toggles one card (no drag)', function () {
    Sound::instance()->setEnabled(false);
    $mockBtn = new class {
        public function setText(string $t): void {}
        public function enable(): void {}
        public function disable(): void {}
    };
    $ctrl = new GameController();
    $ctrl->area = null;
    $ctrl->status = null;
    $ctrl->actBtns = \array_fill(0, 6, $mockBtn);
    $ctrl->startMatch('akainu');
    $ctrl->humanBid(3);

    $ctrl->hit['hand'] = ['h1' => [0, 600, 68, 90], 'h2' => [36, 600, 68, 90]];
    $ctrl->selected = [];

    // 仅一次按下+松开，不做拖拽：应选中 1 张
    $ctrl->onClick(opdMouse(34, 645, down: 1, held: 1));
    $ctrl->onClick(opdMouse(34, 645, down: 0, up: 1, held: 0));
    expect($ctrl->selected)->toBe(['h1']);

    // 再次点同一张：应取消
    $ctrl->onClick(opdMouse(34, 645, down: 1, held: 1));
    $ctrl->onClick(opdMouse(34, 645, down: 0, up: 1, held: 0));
    expect($ctrl->selected)->toBe([]);
});
