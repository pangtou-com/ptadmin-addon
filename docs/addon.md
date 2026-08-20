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

新版云市场授权以 PTAdmin 应用实例为绑定对象，不以域名或服务器硬件作为主身份。安装或升级 `ptadmin/admin` 后，宿主提供稳定的 `application_instance_id` 和实例公钥；`ptadmin/addon` 负责查询 License、使用 `license_code` 激活、周期验证和启动门禁。不再提供 License 迁移流程。

激活凭证默认保存在：

```text
storage/app/ptadmin/addon/licenses/{addon_code}.json
```

可通过 `PTADMIN_ADDON_LICENSE_STORAGE_PATH` 调整目录。凭证文件不随插件目录升级或覆盖，不应放入插件包、前端资源或代码仓库。

运行规则：

- 未生成实例激活凭证的历史插件继续使用旧购买校验，升级客户端不会立即阻断旧站点。
- 新激活插件由应用状态批量同步更新平台签名决策。插件业务请求只读取本地凭证和运行状态，不同步调用平台。
- 平台暂时不可访问时，只在最近成功验证返回的离线宽限期内继续运行。
- License 已属于其他应用时不能在当前应用激活，应使用当前应用可用的 `license_code`。
- 域名只作为运行观察信息上报，不参与 License 归属判断。
- 应用启动时只读取本地已验签决策，不逐个联网请求平台；状态同步在启动后批量完成。
- `free_perpetual`、`grace` 和 `blocked` 等运行结论必须来自平台签名决策，不能只相信插件清单或市场当前价格。
- 平台公钥通过 `PTADMIN_ADDON_PLATFORM_LICENSE_PUBLIC_KEY` 配置，可以填写 PEM 内容或宿主内可读的 PEM 文件路径。
- 平台尚未签发决策的插件进入 `unknown` 或 `legacy_review`，继续加载并提示；只有有效签名决策明确阻断或宽限期结束后才跳过加载。
- 插件安装记录使用 `management_scope` 区分平台插件、本地插件和历史来源未知插件。市场安装为 `platform`，本地包安装为 `local`，已有目录初始化为 `legacy_unknown`；平台授权不能仅根据插件 `code` 是否存在于市场中判定。
- `local` 插件不参与 PTAdmin 平台授权门禁，`legacy_unknown` 只在插件管理页提示确认来源。历史记录收到匹配当前版本和包哈希的有效签名决策后可提升为 `platform`。

需要强制应用实例授权的插件必须在 `manifest.json` 中显式声明协议：

```json
{
    "license_required": true,
    "license_protocol": "ptadmin-addon-license@1"
}
```

未声明 `license_required` 的历史插件仍按旧购买校验运行；声明强制授权但缺少协议版本的安装包会被拒绝。
