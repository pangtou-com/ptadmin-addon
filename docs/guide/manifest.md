# Manifest

插件清单文件统一使用 `manifest.json`。

最小示例：

```json
{
  "id": "demo-addon",
  "code": "demo-addon",
  "name": "Demo Addon",
  "version": "1.0.0",
  "develop": true,
  "type": "module",
  "description": "Demo 插件",
  "entry": {
    "installer": "Addon\\DemoAddon\\Installer",
    "bootstrap": "Addon\\DemoAddon\\Bootstrap"
  },
  "compatibility": {
    "php": ">=8.0"
  },
  "resources": {
    "assets": "./Assets",
    "routes": "./Routes",
    "views": "./Response/Views",
    "lang": "./Response/Lang",
    "config": "./Config",
    "functions": "./functions.php"
  }
}
```

## 常用字段

- `id`
  插件包唯一标识。
- `code`
  插件对外稳定代码标识，代码引用统一使用它。
- `develop`
  是否处于开发模式。开发模式插件升级时默认不允许直接覆盖。
- `entry.installer`
  安装周期入口。
- `entry.bootstrap`
  运行期入口。
- `compatibility`
  宿主和运行环境兼容性要求。
- `dependencies.plugins`
  插件依赖。
- `marketplace.checksum`
  安装包完整性校验摘要。

## 宿主版本来源

兼容性校验优先读取宿主配置：

```php
// config/addon.php
return [
    'host_versions' => [
        'ptadmin/admin' => '1.0.0',
        'ptadmin/base' => '1.0.0',
    ],
];
```

如果没有配置，再回退到 Composer 版本解析。
