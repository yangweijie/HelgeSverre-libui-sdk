# 项目长期记忆（HelgeSverre-libui-sdk）

## 补丁（patch）约定（关键）
- `vendor/` 在本仓库被 git 跟踪，补丁采用 **整文件镜像** 方式，不是 unified diff。
- 补丁源目录：`patches/<vendor子路径>`，与 `vendor/` 相对路径一致，例如
  `patches/helgesverre/libui/src/Color.php` 对应 `vendor/helgesverre/libui/src/Color.php`。
- 应用脚本：`patch.php`（composer `post-autoload-dump` 自动跑），把 `patches/` 下所有文件
  `copy()` 覆盖到 `vendor/`，并写 `.patches_applied` 时间戳标记。
- **生成新补丁流程**：编辑 `vendor/.../X.php` → `cp vendor/.../X.php patches/.../X.php`
  → 运行 `php85 patch.php` 同步并校验两者 `diff -q` 一致。
- 改 vendor 后务必同步 patches 副本，否则 `composer dump-autoload` 会把补丁覆盖掉。

## 配色约定
- 图表调色板已由字面 hex 改为 **命名色驱动**：`ChartConfig::PALETTE_NAMES`（10 个 CSS 命名色名，如 `slateblue`/`crimson`/`teal`），运行时经 `Libui\Color::named()` 解析为 `0xRRGGBB`（`palette()` 懒加载缓存）。`colorAt($i)` 走 `palette()`。
- 自定义调色板：`$config->colors(0x.., 0x..)` 按实例覆盖（`$customPalette`）。
- 主题预设：`ChartConfig::THEMES`（`light`/`dark`，含 tooltip 配色）；`applyTheme($name)` 套用，未知回退 `light`。

## Libui\Color API（已打补丁，vendor + patches 同步）
- 命名色：`Color::NAMED`（115 色 const）、`Color::named($name,$a=1)`、`Color::{name}()` 魔法静态（如 `Color::tomato()`）、`normalizeName()`。
- 构造：`rgb/rgba/rgb255/hex/from`；实例：`withAlpha/toArray/toHex`。
- **新增工具方法**：`hsl($h°,$s,$l,$a)`、`withHue/withSaturation/withLightness`、`toHsl()`、`lerp($other,$t)`（= `mix()`）、`luminance()`、`isLight()`、`contrastColor()`。私有 `rgbToHsl()`/`hslToRgb()`（h 度数，s/l 0..1）。
- 底层事实：`drawString` 的 y 是**文本顶边**（非基线）；tooltip 文字尺寸用 `TextLayout::extents()` 实测，避免基线猜测。
