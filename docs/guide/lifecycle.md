# 生命周期

当前协议分成两层：

- `Installer`
- `Bootstrap`

## Installer

`Installer` 负责安装周期：

```php
use PTAdmin\Addon\Service\BaseInstaller;

class Installer extends BaseInstaller
{
    public function beforeInstall(): bool {}
    public function install(): void {}
    public function init(): void {}
    public function upgrade(?string $fromVersion = null, ?string $toVersion = null): void {}
    public function uninstall(): void {}
}
```

建议放在这里的内容：

- 数据库结构初始化
- 默认数据写入
- 权限和菜单初始化
- 升级迁移
- 卸载清理

## Bootstrap

`Bootstrap` 负责运行期注册：

```php
use PTAdmin\Addon\Service\BaseBootstrap;

class Bootstrap extends BaseBootstrap
{
    public function enable(): void {}
    public function disable(): void {}
    public function registerDirectives($manager): void {}
    public function registerInjects($manager): void {}
    public function registerHooks($manager): void {}
}
```

建议放在这里的内容：

- 模板指令注册
- 支付、登录、通知、存储等 inject 注册
- hooks 监听器注册
- 启停相关的运行期动作

## 回滚

覆盖安装时，系统会先备份旧插件目录。

如果以下任一步失败：

- `install.sql`
- `installer.install()`
- `installer.init()`

系统会删除新目录并恢复旧目录。
