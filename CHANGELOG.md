# ChangeLog

-   [更新日志](https://www.panagtou.com/ptadmin/addon/changelog.html)

## 发布日志

-   [Github](https://github.com/pangtou/addon)
-   [Gitee](https://gitee.com/pangtou/addon)

## 更新日志
[更新] 2024-12-19
-【BUG】修复addon安装bug
-【BUG】修复addon上传bug
-【BUG】修复addon下载bug
-【新增】安装命令


[更新] 2024-12-05
-【新增】将应用安装合并到插件管理包中，取消独立的composer包
-【新增】新增插件控制台命令执行方法【待完善】
-【新增】调整addonManager调用方式，使用 facade 方式调用
-【新增】增加添加指令增加out参数时表示赋值 赋值运算则无需结尾 @pt:end
-【重构】重构插件配置文件加载规则

[更新] 2024-11-11
-【新增】输出格式化方法，支持使用`{}`方式输出数据
-【新增】开发文档
-【新增】支持使用@pt前缀的方式调用原laravel指令方法
-【bug】修复指令约束数据类型的情况
-【bug】修复组件编译时输出语句的错误处理
-【bug】修复应用解析错误
-【bug】修复 @pt:End  大小写问题
-【bug】应用解析配置信息失败后抛出异常
-【bug】修复参数解析器bug，将解析器支持多种解析格式

[更新] 2024-07-23
- 将原管理模块中插件管理模块迁移独立打包