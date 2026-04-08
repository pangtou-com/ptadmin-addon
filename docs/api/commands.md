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
