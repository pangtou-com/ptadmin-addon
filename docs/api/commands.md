# 命令行 API

## 初始化插件

```bash
php artisan addon:init demo-addon --title="Demo Addon"
```

可选参数：

- `--title`
- `--force`

## 本地安装

```bash
php artisan addon:install-local /path/to/demo-addon.zip
```

覆盖安装：

```bash
php artisan addon:install-local /path/to/demo-addon.zip --force
```

## 云端安装

```bash
php artisan addon:install demo-addon
```

指定版本：

```bash
php artisan addon:install demo-addon 12
```

保留前端源码：

```bash
php artisan addon:install demo-addon --with-source
```

## 升级插件

```bash
php artisan addon:upgrade demo-addon
php artisan addon:upgrade demo-addon 12 --force
```

## 启用与禁用

```bash
php artisan addon:enable demo-addon
php artisan addon:disable demo-addon
```

## 卸载插件

```bash
php artisan addon:uninstall demo-addon --force
```

## 上传插件

```bash
php artisan addon:upload demo-addon
```

上传命令接收插件 `code`，会从本地已安装插件清单解析真实插件目录，例如 `base_path('addons/DemoAddon')`。上传包为单个 zip，内部按发布内容分区：

- `manifest.json`：插件基础声明。
- `release.json`：发布包结构声明，标记后端、前端源码、前端构建物是否包含。
- `backend/`：后端运行代码，排除前端源码和构建物。
- `frontend-source/`：`Frontend/` 下的前端源码，排除 `dist/`。
- `frontend-dist/`：前端运行构建物，包含 `frontend.json` 和 `dist/`。

上传包必须至少包含 `backend/` 或 `frontend-dist/` 其中之一。只有前端源码但没有构建物时不会生成有效发布包。

打包会排除开发目录和本地依赖目录，例如 `.git`、`.github`、`.idea`、`.vscode`、`node_modules`、`vendor`、`.vite`、`.turbo`、`.cache`、`coverage`。

云端下载、云端升级、本地安装均按上述分区发布包解析，不再兼容旧式的“zip 内直接包含插件目录”结构。安装时默认只保留运行内容：`backend/` 合并到插件根目录，`frontend-dist/` 发布到 `storage/app/addons/{addon_code}` 作为前端运行资源目录。`frontend-source/` 默认不合并到项目中；只有执行 `addon:install --with-source` 时才会尝试保留前端源码。如果发布包中没有 `frontend-source/`，即使指定 `--with-source` 也会跳过，不影响安装。
