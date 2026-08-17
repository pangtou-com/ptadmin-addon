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
    "license_required": false,
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

如果插件源码已经直接放在 `addons/` 目录中，应执行已有目录初始化命令，不要使用云端安装命令或 `--force` 覆盖源码：

```bash
php artisan addon:setup plugin-code
```

插件状态分为：

- 已部署：插件目录和有效 `manifest.json` 已存在。
- 已安装：安装生命周期执行成功，并在 `storage/app/ptadmin/addon/installations/{addon_code}.json` 留有宿主记录。
- 已启用：插件未被 `disable` 标记禁用，可以参与运行时注册。

当前规则：

- 本地包必须包含 `manifest.json`
- 若声明了 `marketplace.checksum`，安装前会做完整性校验
- 若未声明 `marketplace` 或无法完成云端登记校验，则只提示，不阻断本地安装

## 应用实例授权

新版云市场授权以 PTAdmin 应用实例为绑定对象，不以域名或服务器硬件作为主身份。安装或升级 `ptadmin/admin` 后，宿主提供稳定的 `application_instance_id` 和实例公钥；`ptadmin/addon` 负责查询当前平台账号的 License、激活、限额迁移和周期签名验证。

激活凭证默认保存在：

```text
storage/app/ptadmin/addon/licenses/{addon_code}.json
```

可通过 `PTADMIN_ADDON_LICENSE_STORAGE_PATH` 调整目录。凭证文件不随插件目录升级或覆盖，不应放入插件包、前端资源或代码仓库。

运行规则：

- 未生成实例激活凭证的历史插件继续使用旧购买校验，升级客户端不会立即阻断旧站点。
- 新激活插件按平台返回的验证间隔周期校验，签名原文包含激活凭证、插件编码、应用实例 ID 和时间戳。
- 平台暂时不可访问时，只在最近成功验证返回的离线宽限期内继续运行。
- License 已属于其他应用时，必须在迁移额度内显式迁移；不能迁移时应重新购买。
- 域名只作为运行观察信息上报，不参与 License 归属判断。

需要强制应用实例授权的插件必须在 `manifest.json` 中显式声明协议：

```json
{
    "license_required": true,
    "license_protocol": "ptadmin-addon-license@1"
}
```

未声明 `license_required` 的历史插件仍按旧购买校验运行；声明强制授权但缺少协议版本的安装包会被拒绝。
