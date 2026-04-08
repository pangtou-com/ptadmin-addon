# 插件包协议草案

## 设计目标

插件包协议拆分为 3 层：

- `manifest`：静态元数据和声明信息，供安装前校验、依赖分析、市场展示使用
- `installer`：安装生命周期入口，负责安装、初始化、升级、卸载
- `bootstrap`：运行期启用入口，负责注册能力、inject、hooks、指令、路由等

这样拆分的原因：

- 插件管理器在不执行插件代码的情况下，也能完成校验、展示、依赖判断
- 运行期注册行为交给代码入口处理，避免把复杂逻辑塞进配置
- 符合当前项目规则：数据库访问仅允许发生在安装功能阶段

## 结构原则

### 建议放在 `manifest` 中的内容

- 插件标识、名称、版本、描述、作者
- 插件类型，如 `module`、`capability`
- 平台兼容性、运行环境兼容性
- 市场信息，如是否官方、商品 ID、签名、校验和
- 静态依赖声明，如依赖哪些插件或能力
- 生命周期入口声明，如 `installer`、`bootstrap`
- 资源目录声明，如路由、视图、语言包、静态资源目录

### 不建议直接放在 `manifest` 中的内容

下面这些内容更适合在 `bootstrap` 中注册：

- hooks 监听器实现
- inject 能力声明与实现注册
- 能力实现注册
- 指令定义与实现注册
- 容器绑定
- 路由动态注册
- 菜单、权限、事件订阅等运行期行为

可以在 `manifest` 中保留“声明”，但不建议把完整实现细节全部写入配置。

例如：

- 可声明：插件提供 `payment.pay` 能力
- 不建议声明：能力类的全部运行参数和复杂调用逻辑
- 不建议声明：支付、登录、短信、存储等 inject 的实现细节
- 不建议声明：模板指令的 `class`、`method`、`type`、`cache` 等实现细节

## 推荐目录职责

- `manifest.json`：插件静态描述文件
- `Installer.php`：安装、初始化、升级、卸载入口
- `Bootstrap.php`：插件启用时的注册入口

## manifest 推荐结构

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

## 字段说明

### 基础信息

- `id`：插件包唯一标识，建议全局唯一且稳定，主要用于安装记录、目录定位、市场识别
- `code`：插件代码标识，主要用于代码引用、能力引用、配置引用等场景
- `name`：插件名称
- `version`：插件版本，建议使用语义化版本号
- `develop`：是否开发模式。开发中的本地插件可标记为 `true`，管理器在升级覆盖时应默认阻断，避免误覆盖本地开发代码
- `type`：插件类型，建议至少支持：
  - `module`：完整功能模块，如 CMS、商城
  - `capability`：能力型插件，如支付、登录、存储
- `description`：插件简介
- `keywords`：检索关键词
- `authors`：作者信息，建议使用数组，便于多人维护

建议：

- 如果当前系统历史上已经大量使用 `code` 做插件引用，则 `code` 应视为对外稳定字段
- `id` 与 `code` 在一期可以允许相同，降低实现复杂度
- 后续如果接入市场、多租户或多源安装，保留 `id` 和 `code` 两个字段会更稳

### 兼容性信息

- `compatibility`：描述宿主平台与运行环境要求
- 建议至少包含平台核心包版本要求
- 可按需要增加数据库版本、扩展版本等约束

### 市场信息

- `marketplace.official`：是否官方插件
- `marketplace.product_id`：云端商品 ID，用于购买状态校验
- `marketplace.download_id`：下载资源标识
- `marketplace.checksum`：安装包摘要，用于完整性校验
- `marketplace.signature`：签名，用于官方包验证

说明：

- 本地上传插件若未在云端登记，可允许安装，但应提示“非官方或未登记插件”
- 是否阻断安装由管理器策略决定，不建议由插件自行控制

### 入口声明

- `entry.installer`：安装生命周期入口类
- `entry.bootstrap`：运行期启用入口类

管理器可通过这两个入口分别调用：

- 安装阶段：`install` / `init` / `upgrade` / `uninstall`
- 运行阶段：`boot` / `shutdown`

## Installer 约定

`installer` 负责安装相关周期，建议约定以下方法：

```php
interface AddonInstallerInterface
{
    public function install(): void;

    public function init(): void;

    public function upgrade(string $fromVersion, string $toVersion): void;

    public function uninstall(): void;
}
```

说明：

- `install`：拷贝完成后的安装动作
- `init`：初始化动作，如默认数据、权限、配置写入
- `upgrade`：升级处理
- `uninstall`：卸载处理

数据库访问建议只出现在这一层，符合当前项目规则。

## Bootstrap 约定

`bootstrap` 负责插件启用后的运行期注册，建议约定以下方法：

```php
interface AddonBootstrapInterface
{
    public function boot(): void;

    public function shutdown(): void;
}
```

说明：

- `boot`：插件启用时调用，用于注册能力、inject、hooks、指令、路由等
- `shutdown`：插件停用时调用，用于注销或清理运行期注册

## 能力、指令、Hooks 的建议做法

### Inject

建议：

- 支付、登录、短信、存储等可调用能力统一通过代码注册
- 不再通过 `manifest` 或 `json` 配置 `inject` 的处理器映射
- 如需后台展示支持能力，可从 inject 注册中心汇总

示意：

```php
$context->injects()->register(
    'payment',
    InjectDefinition::make('wechat_pay')
        ->title('微信支付')
        ->types(['jsapi', 'qrcode'])
        ->handler(Addon\Payment\WechatPay::class)
);
```

### 能力

建议：

- `manifest.capabilities.provides` 中仅声明能力名
- 真正的能力处理器在 `bootstrap` 中注册到能力中心

示意：

```php
$manager->capability()->register('payment.pay', Addon\Payment\Service\PayService::class);
```

### Hooks

建议：

- `manifest` 中可不写具体 hooks 细节
- 由 `bootstrap` 将监听器注册到事件或钩子系统

示意：

```php
$manager->hooks()->listen('payment.success', Addon\Cms\Listeners\PaymentSuccessListener::class);
```

### 指令

建议：

- 指令不要再通过 `manifest` 或 `json` 配置暴露实现
- 指令应统一通过代码注册，便于 IDE 跳转、重构和静态分析
- 指令的名称、类型、处理器、缓存策略都应在注册代码中显式声明
- 如需后台展示指令元信息，应由指令注册中心汇总，而不是回写到 `manifest`

示意：

```php
$context->directives()->register(
    DirectiveDefinition::make('lists')
        ->handler(Addon\Cms\Directive\ListsDirective::class)
        ->method('handle')
        ->type('loop')
        ->cache(true)
);
```

## 安装流程建议

统一安装流程建议如下：

1. 获取插件包
2. 解压到临时目录
3. 读取并校验 `manifest`
4. 校验完整性、签名、兼容性
5. 如果是云端插件，校验购买状态
6. 拷贝到 `addons/<id>`
7. 执行 `installer.install()`
8. 执行 `installer.init()`
9. 标记安装成功

失败时应至少具备：

- 安装失败状态标记
- 临时目录清理
- 已写入目录和初始化动作的回滚策略

本地上传补充建议：

- 本地 zip 包至少必须包含 `manifest.json`
- 若 `manifest.marketplace.checksum` 存在，可先执行安装包摘要校验
- 若插件未在云端登记，管理器不必阻断安装，但应明确提示“按本地插件安装，跳过购买校验”

## 当前建议结论

为了让协议更稳定：

- `manifest` 只保留静态声明和入口信息
- `installer` 只负责安装周期
- `bootstrap` 只负责启用后的运行期注册

因此：

- 生命周期的“管理状态”不建议写进配置文件
- inject、hooks、能力、指令的“实现细节”不建议写进配置文件
- hooks、能力的“静态声明”可以按需保留在 `manifest` 中
- inject 建议完全脱离 `manifest`，统一改为代码注册
- 模板指令建议完全脱离 `manifest`，统一改为代码注册

这更适合作为插件市场、安装器和运行期管理器共同使用的一套协议。
