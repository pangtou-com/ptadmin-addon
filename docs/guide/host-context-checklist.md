# 宿主接入清单

这份清单用于指导宿主系统接入统一模板上下文协议。

如果你已经确认插件统一使用 `__pt_context` 协议，那么宿主层至少要完成下面这些动作。

## 目标

宿主负责两件事：

1. 注入统一运行时上下文
2. 输出最终页面壳层

插件负责两件事：

1. 提供数据
2. 按统一协议读取上下文

职责边界不要反过来。

## 接入步骤

### 1. 统一注入模板运行时上下文

宿主在渲染模板前，至少应注入：

```php
runtime_context_replace(runtime_context_page([
    'route' => $route,
    'resolved' => [
        'type' => 'archive',
    ],
    'page' => $page,
]));
```

同时仍然可以继续把这些变量传给视图作为展示数据：

```php
return view($template, [
    'route' => $route,
    'resolved' => [
        'type' => 'archive',
    ],
    'page' => $page,
]);
```

编译器在运行时会自动把当前上下文收敛为统一的 `__pt_context`。

### 2. 统一定义页面类型

运行时上下文里的 `resolved.type` 必须稳定，建议只允许有限集合，例如：

- `home`
- `category`
- `archive`
- `tag`
- `special`
- `search`

不要在不同宿主或不同插件里随意改名，否则插件无法稳定识别当前页面。

### 3. 统一整理页面数据结构

注入前的页面数据建议按标准结构整理：

```php
[
    'id' => 0,
    'title' => '',
    'subtitle' => '',
    'description' => '',
    'keyword' => '',

    'breadcrumb' => [],

    'pagination' => [
        'total' => 0,
        'last_page' => 0,
        'current_page' => 1,
        'per_page' => 0,
    ],

    'category' => [],
    'archive' => [],
    'tag' => [],
    'tags' => [],
    'special' => [],
    'prev' => [],
    'next' => [],

    'seo' => [
        'title' => '',
        'keywords' => '',
        'description' => '',
    ],
]
```

要求：

- 可以为空
- 字段名保持稳定
- 不要混入宿主私有命名

### 4. 统一由宿主输出 SEO

宿主负责页面最终输出，所以：

- `<title>`
- `<meta>`
- `<link rel="canonical">`
- `Open Graph`
- `Twitter Card`
- `JSON-LD`

这些都应由宿主指令或宿主布局负责输出。

插件只负责提供数据，不负责最终壳层输出。

### 5. 插件只声明需要上下文

插件侧如果需要读取当前页信息，只应声明：

```php
DirectiveDefinition::make('lists')
    ->context(DirectiveDefinition::CONTEXT_PAGE)
```

不应要求插件自行拼装 `__pt_context`。

### 6. 模板变量规则保持一致

循环指令统一遵守这条规则：

- 有 `id` 就用显式变量
- 没有 `id` 就统一使用 `$field`

不要再依赖按指令名自动推导变量。

### 7. 统一读取入口

插件与宿主建议统一使用下面的读取方式：

- 指令内部：`runtime_context_from_dto($dto)`
- 普通运行时：`runtime_context_current()` 或 `runtime_context('page.id')`

不要再把 `$page`、`$resolved` 这类模板变量当成上下文事实源。

## 宿主自检

接入完成后，建议至少自检这几项：

- 分类页下列表指令是否能正确识别当前分页
- 详情页下上一篇/下一篇是否能直接读取当前上下文
- 标签/专题聚合页是否能自动识别当前聚合对象
- 宿主 SEO 指令是否能直接消费运行时上下文里的 `seo`
- 不写 `id` 的循环指令是否统一使用 `$field`

## 推荐落地顺序

1. 宿主先统一 `runtime_context_page(...)` 注入
2. 宿主再统一 SEO 输出协议
3. 插件注册逐步切换到 `DirectiveDefinition::CONTEXT_PAGE`
4. 插件内部读取统一切到 `runtime_context_from_dto($dto)`
5. 模板清理掉把 `$page` 当上下文来源的旧习惯
