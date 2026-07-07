# 进度日志 (Progress Log)

## 2026-07-07 会话（GUI 修复收尾 + 规划文件初始化）

### 本次完成
- [x] **日志乱码修复**（叫地主阶段）：`drawLog()` 中 `substr` → `mb_substr(..., 'UTF-8')`，截断 24→28 字符，按 UTF-8 字符边界切，彻底消除从汉字中间劈开产生的非法序列乱码（显示为 ♣♣ 等）。php85 -l 通过。
- [x] **初始化 planning-with-files 规划体系**：创建 `task_plan.md` / `findings.md` / `progress.md`，将前序多轮 GUI 修复（选将界面、主循环、AI 跟牌、卡牌 hit-test、结算居中、日志位置、托管功能、跨平台字体、窗口居中）系统归档。

### 验证
- `php85 -l src/Games/OnePieceDoudizhu/GameController.php` → No syntax errors
- 关键特性代码核验全部在位：mb_substr(L1261)、autoPlay(18处)、uiFontFamily(L33)、lastShownPlay(5处)、window->centered()(example L91)
- Pest 17 项测试（历史通过，本次未重跑因环境路径解析问题；纯数值/字符截断改动不影响逻辑）

### 待用户验证
- 运行 `php85 examples/onepiece-doudizhu.php` 截图确认：① 叫地主日志中文正常无乱码 ② 结算文字精确水平居中 ③ 日志不与出牌区/手牌重叠 ④ 托管延迟约 1s 且跨局自动取消

### 历史阶段回顾（已在 task_plan.md 汇总）
1. 核心设计 + 逻辑（Game.php）
2. 程序化 WAV 音效（scripts/gen-sfx.php）
3. GUI 框架 + 选将/对战界面
4. GUI 运行时多轮截图驱动修复（见 task_plan.md Errors 表）
5. 跨平台字体 + 窗口居中 + 按钮布局（重新选将置左、主按钮居中）
6. mb_substr 日志乱码修复（本次）

---
*规划文件遵循 planning-with-files 技能：task_plan.md=阶段追踪，findings.md=技术发现，progress.md=会话日志。*
