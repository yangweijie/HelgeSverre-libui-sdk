# 可观测性接口预留与内嵌自动化服务器设计

> 状态：前瞻设计（本期只固定接口契约，**不实现**自动化服务器）
> 日期：2026-07-14
> 关联：`docs/zh/guide/architecture.md`、`src/Semantics/`、`src/Events/`、`src/State/`、`src/System/`

## 1. 背景与目标

Native SDK（如 Flutter Driver、SwiftUI 的 Accessibility / `accessibilityIdentifier`）通常会在每个应用内嵌一个**自动化服务器**：AI 代理可以读取无障碍快照、驱动控件、断言状态。这是"让 AI 操控 GUI"的标准范式。

本库当前没有这一层。但考虑到 PHP 生态中 AI 工具链的发展，我们在**设计 API 时就预留可观测性接口**是值得的——将来接入 AI 代理时不需要重构。

本设计回答两个问题：

1. **现在该预留哪些接口**，才能让未来的 AI 自动化服务器零侵入接入？
2. **未来服务器挂载在哪里**，且对现有架构不造成破坏？

核心原则：**让 AI 代理只与三类稳定契约对话——语义树（`SemanticsNode`）、事件流（`EmitsEvents`）、状态（`AppRuntime`）。底层是 libui 原生控件还是自绘控件，对代理透明。** 无论将来 server 用 HTTP / WebSocket / MCP 哪种协议，都不需要改动控件或业务代码。

---

## 2. 现有资产盘点（好消息：雏形已存在）

| 资产 | 位置 | 对 AI 自动化的价值 | 缺口 |
|---|---|---|---|
| `SemanticsNode` | `src/Semantics/SemanticsNode.php` | ARIA 式节点：`id / role / label / value / enabled / checked / selected / focusable / focused / geometry / children`；`fromLayout()` 可 headless 构建整棵树 | **无 `toArray()`/`toJson()`**，外部只能反射遍历 |
| `WidgetRole` | `src/Semantics/WidgetRole.php` | 平台无关角色枚举（Button/Checkbox/Slider/TextBox/ComboBox/Tab/Dialog…），注释已写明 "a platform accessibility bridge can consume" | 需扩展到原生控件 |
| `LayoutNode` | `src/Layout/LayoutNode.php` | 自带 `$id`、几何 `$x/$y/$w/$h`、`$role`；内建 `find(id)` / `findAt(x,y)`（命中测试）/ `focusables()` / `pathTo(id)`——**正是"按 id 定位/读取/点击"所需原语** | 仅覆盖自绘树 |
| `EmitsEvents` | `src/EmitsEvents.php` | `on(event, handler)` / `emit(event, data)` 订阅-发射模型 | 无历史、无旁路 hook、事件非类型化 |
| `Log` | `src/Logging/Log.php` | PSR-3 风格（Monolog），BufferHandler + 落盘、可分级 | 与 UI 事件/状态/快照未打通 |
| `App::afterInit()` | `patches/helgesverre/libui/src/App.php` | `Ffi::init()` 之后、`Ffi::main()` 之前的安全钩子——**启动 server 的唯一正确位置** | — |
| `Loop::repeat/defer` | `Loop.php` | 基于 `uiTimer`/`uiQueueMain`；`GlobalHotkey::startPolling()` 已证明"在 GUI 循环里周期轮询外部逻辑"可行 | — |
| `Capability`/`CapabilityRegistry` | `src/System/` | 既有"探测 + 注册"扩展模式（Audio/Tray/Hotkey 均如此） | — |

> 关键判断：**不需要从零设计可观测性**。它已经在 `SemanticsNode` 里了，只是还没被当成对外契约固定下来、也没覆盖 libui 原生控件。

---

## 3. 核心约束：组件树是分裂的

这是整个设计必须正视的现实，也是最大的"预留"工作量所在。

```
┌─ 自绘控件（Surface 家族）─────────────┐   ┌─ libui 原生控件 ─────────────┐
│ LayoutNode 树（id / geometry / role） │   │ Control 树                    │
│   └─ SemanticsNode（信息丰富）        │   │   ├─ 无 $id、无 parent/children│
│   └─ find/findAt/focusables/pathTo   │   │   ├─ 容器只能 numChildren()   │
│                                       │   │   │    不能取回子控件           │
│   ✅ AI 可读、可遍历                   │   │   └─ uiControlParent 已声明    │
│                                       │   │       但从未被 PHP 包装        │
│                                       │   │   ❌ 几乎不可遍历              │
└───────────────────────────────────────┘   └──────────────────────────────┘
```

- 自绘控件走 `LayoutNode`/`SemanticsNode`，信息丰富、可查询。
- libui 原生 `Control` 树**几乎不可遍历**：`Control` 无 `$id`、无 `parent/children`；`Box`/`Form`/`Grid`/`Tab` 只能 `numChildren()` 不能取回子控件（C 头里 `uiBoxChild` 不存在）；`uiControlParent` 在 `Native/libui.gen.h` 已声明但**从未被 PHP 包装**。

**设计结论**：AI server 应以 `SemanticsNode` 为统一模型，但**必须新增一层桥接，把 libui 原生控件（Window/Group 的 title、Form 的 `$fields` 等）也纳入语义树**。这是本期最该"占住"的接口扩展点。

---

## 4. 预留接口契约（本期固定，不实现）

以下接口应在设计阶段就确定签名/行为，避免将来重构。均为**对外契约**，实现可渐进。

### 4.1 语义树序列化出口（P0，最核心）

```php
// src/Semantics/SemanticsNode.php —— 新增
interface JsonSerializable /* 或显式方法 */
{
    public function toArray(): array;   // id/role/label/value/enabled/checked/selected/
                                         // focusable/focused/geometry/children 递归
    public function toJson(int $flags = 0): string;
}
```

### 4.2 运行期快照入口（P0）

目前快照能力只在 `tests/Helpers/LayoutSnapshot.php`（golden-file 测试工具）。需暴露一个**运行期**入口，且包含实时交互态：

```php
// 建议位置：src/Observability/UiSnapshot.php（新增，本期只定义）
final class UiSnapshot
{
    // 当前窗口的语义树（含实时 value/pressed/focused，而非测试用的确定性快照）
    public static function capture(Window|Surface $target): SemanticsNode;
    public static function toArray(Window|Surface $target): array;
    public static function toJson(Window|Surface $target): string;
}
```

> 自绘控件直接走 `LayoutNode::semantics()`；原生控件走 §4.3 桥接后并入同一棵树。

### 4.3 原生控件桥接（P0，工作量最大）

```php
// patches/helgesverre/libui/src/Control.php —— 新增
public function parent(): ?Control;          // 包装已声明的 uiControlParent()
public function children(): array;            // 容器需维护 children 列表

// Box/Form/Grid/Group/Tab 补 childAt(int $i): ?Control
// Form 已有 $fields 私有数组，是天然参照

// 任意 Control 应能产出语义节点（原生控件的最小语义契约）
interface SemanticProvider
{
    public function semantics(): SemanticsNode;
}
```

### 4.4 事件系统旁路与类型化（P1）

`EmitsEvents` 目前 `emit()` 只调 handlers、事件即弃。需为自动化层预留零侵入订阅能力：

```php
// src/EmitsEvents.php —— 新增
protected function beforeEmit(string $event, mixed $data): void;  // 可覆盖
protected function afterEmit(string $event, mixed $data): void;   // 可覆盖
public function listeners(): array;                               // 公开枚举已注册处理器
// 建议：事件名从裸字符串逐步收敛为事件类（Event object），便于结构化路由
```

### 4.5 状态变更可观测（P1）

`AppRuntime::dispatch()` 是纯净状态转换，不广播、不记日志、Effect 只存不执行（`src/State/`）。最理想的"可观察状态变更点"当前完全封闭：

```php
// src/State/AppRuntime.php —— 新增（不破坏纯函数语义）
// dispatch 后 emit 一个可观测事件，供自动化层旁路订阅
emit('state.changed', new StateChangedEvent(
    msg: $msg, oldModel: $prev, newModel: $next,
));
// 并允许导出 state 快照
public function snapshot(): array;   // Model → array（readonly 反射）
```

### 4.6 统一遥测通道（P2）

把 UI 事件 / 状态 / 快照汇入 `Log`，并预留"转发到外部观察者"的出口：

```php
// src/Logging/Log.php —— 结构化 context 已支持；新增
Log::event(string $name, array $context);   // 事件遥测
Log::snapshot(array $tree);                  // 快照遥测
// 预留可插拔 Sink（file / stderr / socket），默认 file，未来可接 server
```

> 同时把 `FocusManager` 等处的 `fwrite(STDERR, ...)` 散落输出统一进 `Log`，集中可消费。

---

## 5. 内嵌自动化服务器的接入点（确认可行，本期不实现）

> 全仓库搜索 `stream_socket_*` / `socket_create` / `React` / `Ratchet` / `proc_open` 均为 **0 命中**——这是绿地，反而干净。

### 5.1 启动位置（已就位）

在 `App::afterInit(callable)` 钩子里启动 server（`App.php`）。此钩子在 `Ffi::init()` 之后、`Ffi::main()` 之前运行——libui 已初始化、原生库可用、尚未进入阻塞循环。`examples/all-components.php` 已用 `afterInit` 做启动期任务，是既有范式。

### 5.2 与 GUI 循环交织（已就位，无需新机制）

- `Libui\Loop::repeat(ms, fn)` —— 底层 `Ffi::timer` → libui `uiTimer`，运行在 GUI 主线程。**用来每 10~50ms `stream_select` 非阻塞 socket**。`GlobalHotkey::startPolling()` 与 `Audio` 的播放结束轮询就是此范式。
- `Libui\Loop::defer(fn)` —— 底层 `Ffi::queueMain`（`uiQueueMain`）。**用于把 server 请求的处理（读/驱动控件）安全回射到 GUI 主线程**，避免在非 GUI 线程触碰 libui 控件导致重入问题。

### 5.3 扩展模式（风格统一）

遵循 `src/System` 的 `Capability`/`CapabilityRegistry` 模式，新增 `AutomationCapability`（探测端口可用性 / 依赖）+ `AutomationServer`（实现类），与 Audio/Tray/Hotkey 子系统风格统一。

### 5.4 安全约束（来自 WebView 教训）

- server 所有回调必须 `try/catch` 包裹一切——异常穿过 FFI/事件循环边界会直接崩溃进程（`src/WebView.php` 经验教训）。
- server 资源释放放在 `Ffi::main()` 返回之后（即 `App::run()` 的 `finally` 之后或 `Window::run()` 的 `$afterClose`）。
- 不要在首个 `Window` 构造之后才创建需要菜单的 server 调试 UI（菜单已锁，`Window.php` 的 `$menusLocked`）。

---

## 6. 分阶段路线图

| 阶段 | 范围 | 是否本期 |
|---|---|---|
| **P0 接口预留** | `SemanticsNode::toArray()/toJson()`、`UiSnapshot::capture()/fromLayout()`、原生控件桥接（`Control::parent()/children()/childAt()` + `SemanticProvider`、`Box/Grid/Group/Tab/Window::semantics()`） | ✅ 已实现（见 §9） |
| **P1 事件/状态可观测** | `EmitsEvents` 旁路 hook、`AppRuntime::dispatch` 广播 `StateChanged`、state 快照导出 | ✅ 已实现（见 §10） |
| **P2 遥测** | `Log` 统一事件/快照通道（`Log::event` / `Log::snapshot`） | ✅ 已实现（见 §10） |
| **S1 服务器** | `AutomationCapability` + `AutomationServer`（localhost HTTP），`afterInit` 启动 + `Loop::repeat` 轮询 | ✅ 已实现（见 §10） |
| **S2 协议适配** | 在 S1 之上包一层 MCP（JSON-RPC 2.0）协议；`McpServer` 只消费 §4 的契约（`/mcp` 挂载） | ✅ 已实现（见 §11） |

---

## 7. 验收与不破坏约束

- **不破坏现有契约**：`SemanticsNode` 现有字段与 `fromLayout()` 行为不变，仅新增 `toArray()`/`toJson()`。
- **不引入新依赖**：服务器 I/O 用 PHP 原生 `stream_socket_*` + `stream_select`（与 `Loop::repeat` 契合），无需 ReactPHP/AMP。
- **原生控件桥接向后兼容**：`parent()`/`children()` 返回 `?Control`/`array`，对未维护列表的控件返回空，不影响现有 `numChildren()` 调用方。
- **Event/State 旁路默认无副作用**：`beforeEmit`/`afterEmit` 空实现；`AppRuntime::dispatch` 的 `emit` 仅在监听方存在时产生成本（或经 `Log` 门控）。

---

## 8. 结论

本库已具备"可观测性"的雏形（`SemanticsNode` + `LayoutNode`），其核心缺口是：**运行期序列化出口缺失** + **原生控件未纳入语义树** + **事件/状态无旁路**。

本期只需把 §4 的接口契约固定下来（语义树序列化、运行期快照入口、原生控件桥接、事件/状态旁路），未来无论以何种协议挂载 AI 自动化服务器，都无需改动控件或业务代码——这正是"前瞻性预留"的目标。

## 9. 实现状态（2026-07-14）

P0 接口已落地，可无头运行（不依赖 FFI/显示）：

- `src/Semantics/SemanticProvider.php` —— 统一语义契约接口 `semantics(): ?SemanticsNode`。
- `src/Semantics/SemanticsNode.php` —— 新增 `toArray()` / `toJson()` 与 `fromControls()` 容器构建器。
- `src/Observability/UiSnapshot.php` —— 运行期快照入口 `capture(Window|Surface)` / `fromLayout(LayoutNode)` / `toArray()` / `toJson()`。
- `patches/helgesverre/libui/src/Control.php` —— 基类实现 `SemanticProvider`，新增 `parent()` / `children()` / `childAt()` / `registerChild()` / `setParentInternal()` / `clearChildren()`，以及默认 `semantics()` 返回 null。
- `patches/helgesverre/libui/src/{Box,Grid,Group,Tab,Window}.php` —— 在 `append`/`setChild` 时登记父子关系，并实现 `semantics()` 产出原生控件语义树（Tab 产出 tablist）。
- `src/Widgets/Surface.php` —— 实现 `SemanticProvider`，`semantics()` 委托 `SemanticsNode::fromLayout($this->rootLayout())`。

测试：`tests/ObservabilityTest.php`（5 项全过，无头）+ 既有 `tests/SemanticsTest.php` 回归通过。

**已知限制（未来 S 阶段处理）**：
- 原生叶控件（Button/Entry 等）默认 `semantics()` 返回 null，不出现在原生树中；自绘 `Surface` 树已由 `UiSnapshot::capture($surface)` 完整覆盖。
- `Surface` 嵌入 libui 原生容器时，原生桥接记录的是其底层 `Area` 控件（默认语义为 null），故自绘树需直接对 `Surface` 取快照。
- 原生叶控件的 `label`/`value` 反射（如 `uiButtonText`）未接入，属 P0 范围外。

## 10. P1 / P2 / S1 实现状态（2026-07-14）

### P1 — 事件 / 状态旁路可观测

- `src/EmitsEvents.php` —— `emit()` 现包裹 `beforeEmit()` / `afterEmit()` 钩子（空实现、可覆盖），新增 `listeners(): array` 公开枚举处理器；新增 `emitEvent(Event $e)` 把类型化事件包成 `(name, event)` 派发。
- `src/Events/Event.php` —— 类型化事件标记接口 `Event { name(): string }`。
- `src/State/AppRuntime.php` —— `use EmitsEvents`；`dispatch()` 在状态转换后广播 `state.changed`（携带 `StateChangedEvent`）；新增 `snapshot(): array` 与静态 `modelSnapshot()`（反射 readonly Model 为数组）。
- `src/State/StateChangedEvent.php` —— 携带 `msg / oldModel / newModel`，实现 `Event`。

外部观察者零侵入订阅：`$app->on('state.changed', fn (StateChangedEvent $e) => …)`；UI 组件子类覆盖 `beforeEmit/afterEmit` 即可旁路所有事件。

### P2 — 统一遥测通道

- `src/Logging/Log.php` —— 新增 `Log::event(string $name, array $context)` 与 `Log::snapshot(array $tree, array $context)`。UI 事件 / 状态 / 快照可汇入结构化日志，作为外部观察者出口。

### S1 — 内嵌自动化服务器

- `src/System/AutomationCapability.php` —— `Capability` 实现，探测 `stream_socket_server` 可用性；已注册进 `CapabilityRegistry`（`automation`）。
- `src/System/AutomationServer.php` —— 纯 PHP（`stream_socket_server` + `stream_select`）localhost HTTP 服务，由 `Loop::repeat`（libui 定时器，GUI 线程）驱动；所有请求 `try/catch` 包裹。端点：
  - `GET /snapshot` → 全部已注册根的语义树（消费 `SemanticsNode` / `SemanticProvider`）。
  - `GET /state` → 应用状态快照（需注入 `stateProvider`）。
  - `POST /drive` → `{nodeId, ...}` 转发给注入的 `driveHandler`，由调用方决定如何驱动控件（server 不侵入 widget 内部）。
- `patches/helgesverre/libui/src/App.php` —— 新增 `windows(): array` 访问器与 `enableAutomation(port, stateProvider, driveHandler)`：在 `afterInit` 钩子里启动服务器（libui 已初始化、尚未进入阻塞循环），与 WebView/全局热键范式一致。
- `examples/automation-server.php` —— 可运行示例（需显示）：`curl http://127.0.0.1:18765/snapshot`、`POST /drive` 点按自绘按钮。

测试：`tests/AutomationTest.php`（11 项全过，无头：EmitsEvents 钩子 / AppRuntime 状态广播 / AutomationServer 路由）。回归：`tests/EmitsEventsTest.php`、`tests/State/CounterModelTest.php`、`tests/ObservabilityTest.php`、`tests/SemanticsTest.php` 通过。

**安全与限制**
- 仅绑定 `127.0.0.1`，不暴露到网络；进程退出时 `register_shutdown_function` / `__destruct` 释放 socket。
- `/drive` 默认实现只记录事件并返回 `{ok:true}`；真实驱动（如点按自绘按钮）由调用方的 `driveHandler` 调用 `$surface->handlerFor($id)()` 完成——server 只消费 §4 契约。
- 读取快照在 `Loop::repeat` 回调内（GUI 线程）执行，避免从其他线程触碰 libui。
- 原生叶控件驱动（由 id 反查 `Control` 并调用其原生方法）未实现，属 S2 范围；当前 `/drive` 对原生树节点不生效。

## 11. S2 实现状态（2026-07-14）

### 目标与定位

在 S1 的 `AutomationServer` 之上包一层**协议适配器**，把 §4 契约（语义树 / 状态 / drive）以标准 MCP（Model Context Protocol）形式暴露给 AI 代理 / LLM 客户端。原则不变：**适配器只消费 §4 契约，不触碰任何 widget 内部**；原生还是自绘控件对代理透明。

### 新增文件

- `src/System/Mcp/Tool.php` —— MCP 工具定义值对象：`name / description / inputSchema(JSON-Schema) / handler(callable)`。
- `src/System/Mcp/McpServer.php` —— 纯 PHP（零新依赖）的 MCP 服务端。核心 `handle(string $rawJson): string` 解析 JSON-RPC 2.0 请求（支持单条与 batch），返回 JSON-RPC 2.0 响应。
  - 实现的 JSON-RPC 方法：`initialize`（回报 `protocolVersion=2024-11-05` + `capabilities{tools,resources}` + `serverInfo`）、`ping`、`tools/list`、`tools/call`、`resources/list`、`resources/read`，以及通知 `notifications/initialized`（无响应）。
  - 内置三个工具，全部映射到 §4 契约：
    - `ui_snapshot` → 全部根的语义树（`rootsProvider` → `SemanticsNode::toArray()`）。
    - `ui_get_state` → 应用状态快照（`stateProvider`）。
    - `ui_drive` → 转发 `{nodeId, payload}` 给 `driveHandler`。
  - 内置一个资源 `ui://snapshot`（mimeType `application/json`，`resources/read` 返回实时语义树）。
  - 错误码遵循 JSON-RPC：`-32700` 解析错误、`-32600` 非法请求、`-32601` 方法不存在；工具级错误通过 `isError:true` 的 `content` 返回（约定：handler 返回含 `error` 键的 payload 即视为工具错误）。
  - 传输采用 MCP "Streamable HTTP" 的请求/响应子集：单条 `POST` 携带 JSON-RPC 信封、返回 JSON-RPC 信封；并通过 `GET /mcp` 提供 SSE 单向服务器推送（见下）。
  - 两个 SSE 通知构造方法（消费 §4 契约）：`resourceUpdatedNotification()`（`notifications/resources/updated`，uri=`ui://snapshot`）与 `stateChangedNotification(array $state)`（`notifications/state_changed`，携带新状态）。

### SSE 实时推送（2026-07-14 追加）

在 S2 基础上补足 MCP 服务器主动推送能力：代理不需轮询，状态变化即被实时推送到所有订阅的 SSE 客户端。

- **SSE 端点**：`GET /mcp`（`Accept: text/event-stream`，HTTP/1.1 keep-alive）打开一条 SSE 流。`AutomationServer::poll()` 在收到该请求时调用 `openSse()`：发送 `text/event-stream` 头 + 首帧 `event: endpoint`（data=`/mcp`），并立即 `notifyStateChanged()` 推送当前状态作为基线；连接保留在 `$sseConns` 中不再关闭。
- **推送触发**：`AutomationServer::notifyStateChanged(): void` 把 `notifications/resources/updated` 与（若有 `stateProvider`）`notifications/state_changed` 两帧入队。每个 `poll()` tick 顶部的 `flushSse()` 把队列帧写给所有 `$sseConns`。`notifyStateChanged()` 在「MCP 未启用」或「无 SSE 订阅者」时为 no-op，可在每次状态转换安全调用。
- **接线**：`App::enableAutomation()` 新增 `?callable $stateChangedHandler = null`（签名 `fn(AutomationServer $server): void`），在 server `start()` 后调用，用于把 server 订阅到状态源。典型用法：

```php
$app = new AppRuntime(new CounterModel(0), 'counterUpdate');
App::new()->window($w)->enableAutomation(
    port: 18765, mcp: true,
    stateProvider: fn () => $app->snapshot(),
    stateChangedHandler: fn (\Yangweijie\Ui2\System\AutomationServer $s) =>
        $app->on('state.changed', fn () => $s->notifyStateChanged()),
);
// 代理 / curl 监听： curl -N http://127.0.0.1:18765/mcp
```

- 推送帧格式：`event: message\ndata: <JSON-RPC 通知>\n\n`（MCP Streamable HTTP 规范约定 `message` 事件承载 JSON-RPC 信封）；`data` 用 `JSON_UNESCAPED_SLASHES` 编码（如 `ui://snapshot` 不被转义为 `ui:\/\/snapshot`）。客户端断线由 `flushSse()`（写失败）与读循环（`fread` 返回空）双重清理。
- **同步修复**：`poll()` 原在「接受连接」的同一 tick 未把新连接放入 `$read` 集合，导致首请求（含 SSE 升级）需等下一 tick 才被读取——已修复（接受后 `$read[] = $conn`，本 tick 即可读取），该修复同样惠及 S1 的 `POST /drive` 等首请求。

### 接入方式（S1 + S2 同端口）

- `src/System/AutomationServer.php` —— 构造器新增 `bool $mcp = false`；为 `true` 时内部构建 `McpServer`（复用同一组 `rootsProvider/driveHandler/stateProvider`）。`handleRequest()` 新增路由 `POST /mcp`：未启用时返回 `404`；启用时把请求体交给 `McpServer::handle()`，通知（空响应）映射为 `HTTP 202`，其余为 `200 application/json`。`poll()` 识别 `GET /mcp` 并升级为 SSE 流（`openSse()`），每个 tick 经 `flushSse()` 推送；提供 `notifyStateChanged()` / `sseClientCount()` / `drainSseQueue()`。
- `patches/helgesverre/libui/src/App.php` —— `enableAutomation()` 新增 `bool $mcp = false` 与 `?callable $stateChangedHandler = null` 并透传。用法：

```php
App::new()->window($w)->enableAutomation(port: 18765, mcp: true, /* stateProvider / driveHandler / stateChangedHandler */);
// 代理指向 http://127.0.0.1:18765/mcp ；实时推送用 curl -N http://127.0.0.1:18765/mcp
```

### 测试与示例

- `tests/McpTest.php`（19 项全过，无头）：`initialize` 能力声明、`tools/list` 三个工具、`tools/call` 三个工具正向、缺 `nodeId` 报错、`unknown method` → `-32601`、`parse error` → `-32700`、`ping`、通知无响应、`resources/list`+`read`、`batch`（请求+通知混合）、`POST /mcp` 路由集成；以及 5 项 SSE 测试：`notifyStateChanged` 入队 `resources/updated`+`state_changed` 帧、无 `stateProvider` 时仅前者、`no-op`（无订阅者 / MCP 关闭）、以及真实 loopback 套接字下 `GET /mcp` 建立 keep-alive 流并在状态变化时实时推送 `state_changed`（无需轮询）。
- `examples/automation-server.php` —— 改用 `AppRuntime` + `CounterModel`，启用 `mcp: true` 并通过 `stateChangedHandler` 把计数器 `state.changed` 接到 `notifyStateChanged()`；补充 `curl -N .../mcp` 的 SSE 监听说明。配合 `examples/mcp-client.php`（无依赖 PHP MCP 客户端）可端到端验证：客户端 `tools/call ui_drive` 驱动 UI 后，SSE 实时推送 `state_changed` 反映新值，无需轮询。
- `examples/mcp-client.php`（2026-07-14 新增，纯 PHP、零依赖、无需 libui/显示）—— 演示完整 MCP 往返：`POST /mcp` 做 `initialize`→`notifications/initialized`→`tools/list`→`resources/read` 握手；`GET /mcp` 消费 SSE 流（`event: endpoint` 确认连接、`event: message` 承载 `notifications/state_changed` 与 `notifications/resources/updated`）；并在循环中周期性 `tools/call ui_drive {nodeId:"inc"}` 驱动 UI，实时看到 SSE 推送的状态变化。可作为 Claude Desktop / LLM agent 之外的最小化协议参考实现。

### 安全与限制（与 S1 一致）

- `McpServer::handle()` 与 SSE 帧构造均为纯函数（无 socket / 无 FFI），所有网络层 `try/catch` 已在 `AutomationServer::poll()` 包裹，异常不会穿过 FFI / 事件循环边界。
- 仅绑定 `127.0.0.1`；读快照与推送在 `Loop::repeat` 回调（GUI 线程）内执行。
- **未覆盖（S2 之外）**：原生叶控件的 `/drive` 真实反查驱动尚未实现（与 S1 同限制）；把原生 `label/value` 反射进语义树（P0 范围外遗留）仍未处理。SSE 推送采用单向 `GET /mcp` 流（每客户端一条长连接），未实现多客户端会话隔离或 SSE 重连时的增量补偿（客户端断线后重连将重新收到基线全量状态）。
