# 应用配置

## 配置文件
当使用命令方式 `php artisan addon:init` 初始化插件后，插件目录下应包含名为 `manifest.json` 的清单文件。
基础配置信息：
```json
{
    "id": "插件唯一标识",
    "name": "插件标题",
    "code": "插件编码",
    "type": "插件类型",
    "keywords": [],
    "description": "CMS内容管理系统",
    "version": "1.0.0",
    "develop": false,
    "entry": {
        "installer": "Addon\\Demo\\Installer",
        "bootstrap": "Addon\\Demo\\Bootstrap"
    }
}
```

补充说明：

- `code` 是插件的稳定代码标识
- `develop` 用于标记当前插件是否属于开发模式
- `entry.installer` 负责安装、初始化、升级、卸载
- `entry.bootstrap` 负责启用后的指令、inject、hooks 等运行期注册
- 开发模式插件升级时默认不直接覆盖，需要显式强制升级

本地插件安装命令：

```bash
php artisan addon:install-local /path/to/plugin.zip
```

当前规则：

- 本地包必须包含 `manifest.json`
- 若声明了 `marketplace.checksum`，安装前会做完整性校验
- 若未声明 `marketplace` 或无法完成云端登记校验，则只提示，不阻断本地安装
