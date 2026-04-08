# 快速开始

## 1. 生成插件骨架

```bash
php artisan addon:init demo-addon --title="Demo Addon"
```

命令会生成：

- `addons/DemoAddon/manifest.json`
- `addons/DemoAddon/Installer.php`
- `addons/DemoAddon/Bootstrap.php`
- `addons/DemoAddon/Routes/web.php`
- `addons/DemoAddon/Config/config.php`
- `addons/DemoAddon/Response/Views/index.blade.php`

## 2. 编写安装周期

在 `Installer.php` 中处理：

- `install()`
- `init()`
- `upgrade()`
- `uninstall()`

数据库初始化、默认数据写入、权限初始化建议都放这里。

## 3. 编写运行期注册

在 `Bootstrap.php` 中处理：

- `registerDirectives()`
- `registerInjects()`
- `registerHooks()`
- `enable()`
- `disable()`

## 4. 本地安装 zip 包

```bash
php artisan addon:install-local /path/to/demo-addon.zip
```

覆盖已安装插件时：

```bash
php artisan addon:install-local /path/to/demo-addon.zip --force
```

## 5. 常见安装前校验

安装器当前会自动校验：

- `manifest.compatibility`
- `manifest.dependencies.plugins`
- `manifest.marketplace.checksum`
- 开发模式升级保护 `develop: true`

如果本地包未声明 `marketplace`，系统只提示，不阻断安装。
