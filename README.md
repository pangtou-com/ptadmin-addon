# ptadmin/addon
> PTAdmin 插件管理包,需基于[PTAdmin](https://www.pangtou.com)使用

[![Version](https://img.shields.io/packagist/v/ptadmin/addon?label=version)](https://packagist.org/packages/ptadmin/addon)
[![Downloads](https://img.shields.io/packagist/dt/ptadmin/addon)](https://packagist.org/packages/ptadmin/addon)
[![License](https://img.shields.io/packagist/l/ptadmin/addon)](https://packagist.org/packages/ptadmin/addon)
[![Sponsor](https://img.shields.io/static/v1?label=Sponsor&message=%E2%9D%A4)](https://www.pangtou.com)
[![Sponsor](https://img.shields.io/static/v1?label=Docs&message=PTAdmin&logo=readthedocs)](https://www.pangtou.com)

## 介绍
> PTAdmin 插件管理包，用于插件指令调用、事件管理等


## 使用方法
### (一)、插件中事件处理
> 待完善
### (一)、插件中指令导出
> 待完善
### (二)、模版中指令调用

```php
# 1、默认调用
# 模版写法为：
# @PT:demo
# @PTend
# 这种方法会访问 DemoService 类中的 handle 方法
protected $export = DemoService::class;

# 2、自定义名称调用
# 模版写法为：
# @PT:demo::mouth

# @PTend
# 这种方法会访问 DemoService 类中的 mouth 方法
protected $export = [
        'mouth' => [DemoService::class, 'mouth', true],
        'week' => [DemoService::class, 'week'],
        'week1' => [
            'class' => DemoService::class, // 
            'method' => 'week', // 调用方法
            'allow_caching' => false, // 是否缓存，默认情况下指令数据会缓存2个小时，当插件显示设置为false时调用不缓存
            'return_type' => false, // 返回结果类型，会影响到指令生成的PHP语法类型，默认情况下指令会生成为for循环语句，
            // 当设置为true时指令，指令生成为if语句，
            // 注意：当设置true后 则结束标记不能使用简约写法需要完整填写如：
            // @PT:demo::week
            // @PTend:demo::week            
        ],
    ];

```
### (三)、API中指令调用（待完成）


#### 系统配置读取

@pt:setting.site_title
@pt:setting.site_keyword
@pt:setting.site_doc(limit=100, placeholder="...", default="...")

@pt:auth()

// 关闭指令的支持写法
@pt:end // 简介默认为foreach关闭
@pt:endarc // 默认为foreach关闭
@pt:demo::endarc    // 根据配置关闭

@pt:if(field.is_show==1)
@pt:else
@pt:endif



#### 输出内容（打印内容）
// 打印内容
// 默认值，可嵌套读取子数组内容， 指定字段名称
// id 指定读取的字段名称 默认为 field 表示当前循环或者当前环境中变量名称
// key 嵌套读取的字段名称
// default 默认值，默认为 null, 支持变量写法
// encode 是否转义， 默认为 true
// limit 输出字符长度，默认不限制， 超过长度的字符使用 。。。替换
// placeholder 占位符， 默认为 `...` 当limit为空时无效
@pt:echo(id=field, key=title.title, default = "default", encode=false)

@pt:echo.title
