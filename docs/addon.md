# 应用配置

## 配置文件
当使用命令方式 `php artisan addon:init` 初始化插件后PTAdmin会自动在插件目录下创建名为：`ptadmin.config.json` 文件。
基础配置信息：
```json
{
    "title": "插件标题",
    "addon_code": "插件编码",
    "addon_path": "插件路径",
    "type": "插件类型",
    "keywords": [],
    "description": "CMS内容管理系统"
}
```