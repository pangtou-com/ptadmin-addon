# manifest 字段规范

## 目标

本文件用于定义插件 `manifest.json` 的正式字段规范，服务于：

- 安装前校验
- 插件包解析
- 市场信息展示
- 依赖分析
- 插件管理器实现

当前规范优先支持一期需求，不追求一次性覆盖所有高级场景。

## 设计原则

- 区分必填字段与选填字段
- 优先保证机器可校验
- 静态声明与运行期注册分离
- 不在 `manifest` 中放置复杂执行逻辑
- 不在 `manifest` 中放置模板指令定义
- 不在 `manifest` 中放置 inject 与 hooks 的处理器映射

## 顶层结构

```json
{
  "id": "cms",
  "code": "cms",
  "name": "CMS 内容管理",
  "version": "1.0.0",
  "develop": false,
  "type": "module",
  "description": "为后台提供内容管理能力",
  "keywords": ["cms", "content"],
  "authors": [
    {
      "name": "Zane",
      "email": "873934580@qq.com"
    }
  ],
  "compatibility": {
    "ptadmin/admin": ">=1.0.0",
    "ptadmin/base": ">=1.0.0",
    "php": ">=8.1"
  },
  "marketplace": {
    "official": true,
    "product_id": "cms_pro",
    "download_id": "cms_pro_latest",
    "checksum": "sha256:xxxxxxxx",
    "signature": "base64-signature"
  },
  "entry": {
    "installer": "Addon\\Cms\\Installer",
    "bootstrap": "Addon\\Cms\\Bootstrap"
  },
  "dependencies": {
    "plugins": ["login"],
    "capabilities": ["payment.pay"]
  },
  "capabilities": {
    "provides": ["cms.content"],
    "consumes": ["payment.pay", "auth.login"]
  },
  "resources": {
    "assets": "./Assets",
    "routes": "./Routes",
    "views": "./Response/views",
    "lang": "./Response/Lang",
    "config": "./Config",
    "functions": "./functions.php"
  }
}
```

## 必填字段

以下字段建议作为一期必填字段：

- `id`
- `code`
- `name`
- `version`
- `type`
- `entry.installer`
- `entry.bootstrap`

说明：

- 如果某些极简插件不需要 `bootstrap`，后续可以放宽，但一期建议统一要求
- `manifest` 的必填字段越统一，安装器实现越简单

## 选填字段

以下字段建议作为选填字段：

- `description`
- `keywords`
- `authors`
- `develop`
- `compatibility`
- `marketplace`
- `dependencies`
- `capabilities`
- `resources`

## 字段定义

### `id`

- 类型：`string`
- 必填：是
- 说明：插件包唯一标识
- 用途：
  - 安装目录定位
  - 安装记录识别
  - 市场包唯一识别

约束建议：

- 仅允许小写字母、数字、中划线、下划线
- 长度建议 `2-64`
- 一旦发布后不应随意变更

示例：

```json
{
  "id": "cms"
}
```

### `code`

- 类型：`string`
- 必填：是
- 说明：插件代码标识，供宿主系统和业务代码引用
- 用途：
  - 代码引用
  - 插件查找
  - 配置绑定
  - 能力或业务模块映射

约束建议：

- 一期可与 `id` 保持相同
- 如果历史代码大量使用 `code`，则其应视为对外稳定字段
- 不建议在运行期动态修改

示例：

```json
{
  "code": "cms"
}
```

### `name`

- 类型：`string`
- 必填：是
- 说明：插件显示名称

### `version`

- 类型：`string`
- 必填：是
- 说明：插件版本

约束建议：

- 建议遵循语义化版本格式，如 `1.0.0`

### `develop`

- 类型：`boolean`
- 必填：否
- 默认值：`false`
- 说明：插件是否处于开发模式

建议：

- 本地开发中的插件标记为 `true`
- 处于开发模式的插件，在执行升级覆盖时应默认阻断
- 若确需覆盖，应由管理器要求显式传入强制参数

### `type`

- 类型：`string`
- 必填：是
- 说明：插件类型

一期建议支持：

- `module`：业务模块型插件
- `capability`：基础能力型插件

后续可扩展：

- `theme`
- `integration`

### `description`

- 类型：`string`
- 必填：否
- 说明：插件简介

### `keywords`

- 类型：`string[]`
- 必填：否
- 说明：搜索关键词

### `authors`

- 类型：`array<object>`
- 必填：否
- 说明：作者信息

对象结构建议：

```json
{
  "name": "Zane",
  "email": "873934580@qq.com"
}
```

### `compatibility`

- 类型：`object`
- 必填：否
- 说明：宿主平台与运行环境兼容性要求

示例：

```json
{
  "compatibility": {
    "ptadmin/admin": ">=1.0.0",
    "ptadmin/base": ">=1.0.0",
    "php": ">=8.1"
  }
}
```

建议支持：

- 平台核心包版本
- PHP 版本
- 后续按需增加扩展版本、数据库版本

### `marketplace`

- 类型：`object`
- 必填：否
- 说明：云端市场相关信息

字段建议：

- `official`：`boolean`
- `product_id`：`string`
- `download_id`：`string`
- `checksum`：`string`
- `signature`：`string`

说明：

- 本地上传插件可以没有该字段
- 若缺少该字段，管理器应将其视为“非官方或未登记插件”

### `entry`

- 类型：`object`
- 必填：是
- 说明：插件入口声明

字段要求：

- `installer`：`string`，必填
- `bootstrap`：`string`，必填

示例：

```json
{
  "entry": {
    "installer": "Addon\\Cms\\Installer",
    "bootstrap": "Addon\\Cms\\Bootstrap"
  }
}
```

### `dependencies`

- 类型：`object`
- 必填：否
- 说明：静态依赖声明

字段建议：

- `plugins`：`string[]`
- `capabilities`：`string[]`

说明：

- `plugins` 用于声明插件级依赖
- `capabilities` 用于声明能力级依赖
- 安装器可在安装前进行提示或阻断

### `capabilities`

- 类型：`object`
- 必填：否
- 说明：能力声明

字段建议：

- `provides`：`string[]`
- `consumes`：`string[]`

说明：

- 这里只描述“声明”
- 真正的能力注册应由 `bootstrap` 完成

### 模板指令说明

模板指令不建议写入 `manifest`。

原因：

- 指令属于代码级扩展点，适合用代码注册
- 指令通常需要声明处理器、方法、类型、缓存策略等信息
- 这些信息写在配置里不利于 IDE 跳转、重构和静态分析

建议做法：

- `manifest` 不包含 `directives` 字段
- 指令由插件 `bootstrap` 或专用指令注册类统一注册
- 若后台需要展示指令信息，应从运行期注册中心或编译器注册表中提取

### Inject 与 Hooks 说明

`inject` 与 `hooks` 的处理器映射不建议写入 `manifest`。

建议做法：

- `manifest` 不包含 `inject` 处理器定义
- `manifest` 不包含 `hooks` 监听器定义
- 相关能力统一由插件 `bootstrap` 在运行期注册

### `resources`

- 类型：`object`
- 必填：否
- 说明：插件资源目录映射

字段建议：

- `assets`
- `routes`
- `views`
- `lang`
- `config`
- `functions`

路径约束建议：

- 使用相对插件根目录的相对路径
- 不允许越级路径，如 `../`

## 一期校验建议

安装器读取 `manifest` 后，至少执行以下校验：

1. JSON 格式合法
2. 必填字段存在
3. `id` 与 `code` 格式合法
4. `version` 格式合法
5. `type` 在允许范围内
6. `entry.installer` 与 `entry.bootstrap` 非空
7. `resources` 路径不越界
8. `marketplace.checksum` 格式合法
9. `dependencies` 与 `capabilities` 字段结构合法

## 一期实现建议

为了降低复杂度，一期可以先采用以下策略：

- `id` 与 `code` 要求一致
- `type` 仅支持 `module`、`capability`
- `entry.installer` 与 `entry.bootstrap` 必填
- `marketplace` 非必填
- `dependencies` 先只做校验和提示，不做复杂依赖解算

## 不建议放入 manifest 的内容

以下内容不建议直接写入 `manifest`：

- hooks 的处理器类
- inject 的处理器类
- 指令的定义信息与处理器类
- 能力实现类
- 路由注册逻辑
- 容器绑定逻辑
- 运行期菜单与权限注入逻辑

这些内容更适合在 `bootstrap` 中处理。
