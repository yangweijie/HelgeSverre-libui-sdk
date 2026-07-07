# 海贼王斗地主 — 实施计划 (task_plan)

> 融合「三国百将牌」策略 + 「斗地主」玩法，海贼王三大势力背景。
> 平台：PHP/libui 桌面端（复用 `src/System/Audio.php`）。音效：程序化 WAV。范围：MVP 9 角色。
> 设计文档见 `findings.md`。

## 目标
可运行、可玩、有 AI 对手、有音效、三势力差异化策略的海贼王斗地主桌面游戏。
引擎纯 PHP（可单测、可无头自对弈），UI 用 libui，音效用 miniaudio。

## Phases

### Phase 1: 设计 + 规划 ✅
- [x] 需求拆解、三势力/角色/技能设计（findings.md）
- [x] 决策确认：PHP/libui 桌面 / MVP 9 角色 / 程序化 WAV
- [x] 创建隔离计划目录 + 写 design/findings

### Phase 2: 斗地主引擎核心（纯 PHP，无 UI）✅
- [x] `Card.php`：rank/suit 模型、牌力序、显示
- [x] `Deck.php`：洗牌、发 3 手 + 底牌
- [x] `Combo.php`：识别全部牌型（单/对/三/三带/顺/连对/飞机/炸/火箭）+ `beats()` 比较
- [x] `PlayerState.php` + `Game.php`：手牌、trick、上一手、turn、场状态 modifiers（skip/frozen/petrified/bombDisabled/haki/unblockable）
- [x] `Game.php`：bid / play / pass / 胜负判定 / trick 流转 + `MoveGenerator.php` 合法牌枚举
- [x] 单元测试：发牌、牌型识别、beats、完整一局自对弈不崩

### Phase 3: 势力 + 角色 + 技能系统 ✅
- [x] `Faction.php`：三势力特性（海军/七武海/四皇）声明 + 费用约束
- [x] `Skill.php`：技能定义（trigger/cost/effect 回调 + 场状态修改）
- [x] `Character.php`：9 名角色数据（卡普/赤犬/青雉、鹰眼/女帝/熊、香克斯/白胡子/大妈）
- [x] 技能 hook 接入引擎：onTurnStart/onPlay/onBombPlayed/onTrickWon（并修复 onPlay/onBombPlayed 技能未被消耗的平衡 bug）
- [x] 费用系统：once / charges / haki token

### Phase 4: AI 对手系统 ✅
- [x] `Ai.php`：手牌评估 + 跟牌/lead 启发式（`pickLead`/`pickFollow` 已公开供提示与测试复用）
- [x] 叫分 AI（按手牌强度）
- [x] 势力感知行为（海军激进/七武海保命/四皇囤霸气）
- [x] 技能决策（效用评分触发 vs 乱放）+ 赤犬 `maybeCounter` 反击窗口

### Phase 5: 程序化音效 ✅
- [x] `scripts/gen-sfx.php`：合成 9 个 WAV（click/deal/play/pass/bomb/skill/bid/win/lose）
- [x] `assets/audio/*.wav` 生成（RIFF/WAVE，16-bit PCM，mono，44.1kHz）
- [x] `Sound.php`：封装 Audio::load/play，事件钩子触发映射

### Phase 6: libui GUI ✅
- [x] `examples/onepiece-doudizhu.php`：选将界面（三势力+角色卡）
- [x] 手牌渲染（Area 自绘可点击卡牌网格）、出牌区、对手状态面板、技能按钮（海洋渐变背景）
- [x] 叫分 / 出牌 / 过 / 提示 / 技能 / 反击 交互（含技能目标点选、Enter/P/H 快捷键）
- [x] 音效接入各事件（onGameEvent → Sound::trigger）
- [x] 控制器逻辑抽离至 `src/Games/OnePieceDoudizhu/GameController.php`（PSR-4，可无头驱动）

### Phase 7: 测试 + 验证 + 收尾 ✅
- [x] `tests/OnePieceDoudizhuTest.php`：牌型/角色/引擎/技能/AI自对弈(100局)/音效/无头控制器流程（15 项全过）
- [x] `php85 -l` 全 12 个游戏源文件通过
- [x] `pest` 通过（15 passed，含 100 局无头自对弈零卡死）
- [x] 临时 smoke-*.php 验证脚本已删除（覆盖并入 Pest 套件）
- [x] 更新规划文件为 completed

## 验证结果（2026-07）
- 引擎：牌型识别 + beats 层级正确；叫地主/出牌/pass/trick 流转正常；空手牌即判胜。
- 技能：卡普震飞跳下家、香克斯霸气挡非炸（炸弹可破）、赤犬反击收回炸弹、青雉冻结、大妈偷牌 —— 全部经测试验证。
- AI：100 局自对弈全部正常结束（无卡死），地主/农民双方均有胜局，技能事件正常触发。
- 音效：9 个 WAV 程序化生成有效；Sound 事件映射正确；后端可用时真实播放（click 已验证）。
- GUI 控制器：5 名代表角色（shanks/akainu/aokiji/boa/garp）无头全流程跑通至 `over`。

## 关键风险 / 决策
| 风险 | 处理 |
|------|------|
| 技能破坏斗地主平衡 | 费用/次数硬约束；霸气仍可被炸压；反制仅 once |
| 百将牌 vs 斗地主融合生硬 | 技能挂出牌事件 hook，改场状态，不另起炉灶 |
| 音效需外部素材 | 程序化合成 WAV，自包含 |
| GUI 手牌渲染复杂 | MVP 用 libui Area 自绘（海洋渐变 + 势力配色卡牌 + 命中测试点选） |
| libui 事件回调首参=控件实例 | 已踩坑，闭包首参用控件实例 |
| 控制器不可单测（内联于 example） | 已抽离至 src/ PSR-4，便于无头测试 |

## 目录结构（最终）
```
src/Games/OnePieceDoudizhu/
  Card.php Deck.php Combo.php PlayerState.php Game.php MoveGenerator.php
  Faction.php Skill.php Character.php Ai.php Sound.php GameController.php
examples/onepiece-doudizhu.php   libui GUI（薄装配层，引用 src 控制器）
assets/audio/*.wav               程序化音效（9 个）
scripts/gen-sfx.php              音效生成
tests/OnePieceDoudizhuTest.php   引擎/牌型/技能/AI/音效/控制器 测试
```
命名空间：`Yangweijie\Ui2\Games\OnePieceDoudizhu`（复用 PSR-4 `src/`）。

## 运行方式
- 音效生成：`php85 scripts/gen-sfx.php`
- 启动游戏：`php85 examples/onepiece-doudizhu.php`
- 运行测试：`php85 vendor/bin/pest tests/OnePieceDoudizhuTest.php`
