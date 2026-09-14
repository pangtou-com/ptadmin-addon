# 命令行 API

## 初始化插件

```bash
php artisan addon:init demo-addon --title="Demo Addon"
```

同步拉取前端模板：

```bash
php artisan addon:init demo-addon --title="Demo Addon" --frontend
php artisan addon:init demo-addon --frontend --frontend-template=micro-app --frontend-ref=main
```

可选参数：

- `--title`
- `--frontend`
- `--frontend-template`
- `--frontend-ref`
- `--force`

## 本地安装

```bash
php artisan addon:install-local /path/to/demo-addon.zip
```

覆盖安装：

```bash
php artisan addon:install-local /path/to/demo-addon.zip --force
```

## 初始化已有插件目录

插件源码已经位于 `addons/` 目录时，使用 `addon:setup` 执行 `Installer::install()`、`init()`、前端运行资源发布和后台资源同步。该命令不会下载插件，也不会覆盖插件目录：

```bash
php artisan addon:setup demo-addon
```

已经完成初始化时不会重复执行生命周期。确认安装器支持重复执行后，可以显式强制初始化：

```bash
php artisan addon:setup demo-addon --force
```

插件目录只表示代码已经部署，成功执行安装生命周期后，宿主会在 `storage/app/ptadmin/addon/installations/{addon_code}.json` 记录安装状态。插件卸载后会删除对应记录。

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

升级成功后，宿主会在独立 Artisan 进程中重新加载新版插件并同步后台资源定义，因此不需要再手动执行 `addon:setup`。升级入口同时支持命令行和管理后台接口；两者共用同一套升级流程。

升级期间会使用插件级文件锁，避免同一插件被并发覆盖。插件原本处于禁用状态时，资源定义仍会同步，但同步后的资源保持禁用。

宿主可以恢复插件目录、前端运行资源、安装记录和后台资源，但无法统一撤销插件在 `Installer::upgrade()` 中执行的任意外部操作。插件自己的数据库变更应使用事务或可重复执行的迁移。

资源同步子进程可以通过环境变量调整：

```dotenv
PTADMIN_ADDON_PHP_BINARY=/usr/bin/php
PTADMIN_ADDON_RESOURCE_SYNC_TIMEOUT=120
```

`addon:resources:sync` 默认只同步已启用插件。`--include-disabled` 用于升级流程在隔离进程中读取禁用插件的新定义，普通运维场景不需要手动使用：

```bash
php artisan addon:resources:sync demo-addon --include-disabled
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
php artisan addon:upload demo-addon --ver=2.0
php artisan addon:upload demo-addon --skip-build
php artisan addon:upload demo-addon --title="2.0 稳定版" --description="本次版本摘要" --changelog-file=CHANGELOG.md --major
```

上传命令接收插件 `code`，会从本地已安装插件清单解析真实插件目录，例如 `base_path('addons/DemoAddon')`。如果插件包含 `Frontend/package.json`，默认先执行前端构建，构建成功后才会检查版本、打包和上传；构建失败会停止流程。确认现有构建产物有效时，可以显式使用 `--skip-build` 跳过构建。上传包为单个 zip，内部按发布内容分区：

命令会先读取 `manifest.json` 的版本号并向平台预检查版本占用，检查通过后才开始打包和上传。版本冲突时会从版本末尾自动递增，直到平台返回可用版本；上传成功后，命令会把最终版本号回写到源码插件的 `manifest.json`，上传失败则不会修改源码版本。

`--title` 、`--description` 、`--changelog-file` 和 `--major` 只描述本次版本，不会修改插件固定编码、版本号或 ZIP 内容。未指定标题时默认使用 `版本 {version}`；`release.json.name` 仍保持插件名称，`release.json.title` 承载版本标题。

使用 `--ver=2.0` 可以指定发布版本。指定版本会严格进行平台预检查，已存在时直接报错，不会自动改成其它版本。`--version` 和 `-v` 是 Artisan 全局参数，不能用于指定插件版本。

- `manifest.json`：插件基础声明。
- `release.json`：发布包结构声明，标记后端、前端源码、前端构建物是否包含。
- `backend/`：后端运行代码，排除前端源码和构建物。
- `frontend-source/`：`Frontend/` 下的前端源码，排除 `dist/`。
- `frontend-dist/`：前端运行构建物，包含 `frontend.json` 和 `dist/`。

上传包必须至少包含 `backend/` 或 `frontend-dist/` 其中之一。只有前端源码但没有构建物时不会生成有效发布包。

打包会排除开发目录和本地依赖目录，例如 `.git`、`.github`、`.idea`、`.vscode`、`node_modules`、`vendor`、`.vite`、`.turbo`、`.cache`、`coverage`。

云端下载、云端升级、本地安装均按上述分区发布包解析，不再兼容旧式的“zip 内直接包含插件目录”结构。安装时默认只保留运行内容：`backend/` 合并到插件根目录，`frontend-dist/` 发布到 `storage/app/addons/{addon_code}` 作为前端运行资源目录。`frontend-source/` 默认不合并到项目中；只有执行 `addon:install --with-source` 时才会尝试保留前端源码。如果发布包中没有 `frontend-source/`，即使指定 `--with-source` 也会跳过，不影响安装。
