# 模板上下文协议

`PTAdmin/Addon` 为模板指令定义统一的运行时上下文协议。

这份协议的目标只有一个：宿主负责提供页面上下文，插件按统一结构读取，不再由各个插件各自定义一套临时字段。

## 协议定位

统一规则如下：

- `__pt_context` 是模板运行时的内部上下文字段
- 上下文由宿主显式注入到运行时容器
- 插件只能读取上下文，不能写入或扩展私有结构
- 指令通过 `DirectiveDefinition::context(DirectiveDefinition::CONTEXT_PAGE)` 声明自己需要页面上下文
- 未显式声明 `id` 时，循环指令默认变量统一为 `$field`

这意味着：

- CMS、商城、论坛等插件可以共享同一套页面协议
- SEO、分页、当前页识别、详情上下篇等能力可以跨插件复用
- 模板作者不需要记忆“不同插件有不同上下文格式”

## 宿主职责

宿主不直接手写 `__pt_context`，而是先将标准上下文显式注入运行时容器：

```php
runtime_context_replace(runtime_context_page([
    'route' => $route,
    'resolved' => [
        'type' => 'archive',
    ],
    'page' => $page,
]));
```

编译器在遇到声明了 `context(DirectiveDefinition::CONTEXT_PAGE)` 的指令时，会自动读取当前运行时上下文，并收敛为统一的 `__pt_context`。

最小示例：

```php
runtime_context_replace(runtime_context_page([
    'route' => $route,
    'resolved' => [
        'type' => 'archive',
    ],
    'page' => $page,
]));

return view('some-theme.article-detail', [
    'route' => $route,
    'resolved' => [
        'type' => 'archive',
    ],
    'page' => $page,
]);
```

## 标准结构

当前协议版本为 `v1`。

```php
[
    'version' => 1,

    'route' => '',

    'resolved' => [
        'type' => '', // home/category/archive/tag/special/search/...
    ],

    'page' => [
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
    ],

    'seo' => [
        'title' => '',
        'keywords' => '',
        'description' => '',
        'canonical' => '',
        'robots' => '',
        'open_graph' => [],
        'twitter' => [],
        'structured_data' => [],
    ],
]
```

## 字段约束

- `version` 为必填，用于后续协议升级
- `resolved.type` 为页面识别主入口，插件应优先用它判断当前场景
- `page.pagination` 用于分页类指令，不建议插件再读取散落字段
- `page.prev` / `page.next` 用于详情相邻内容
- `seo` 由宿主输出层消费，也允许插件做只读判断

约束要求：

- 所有字段都必须允许为空
- 插件必须容错，不能假设某个字段一定存在
- 新版本协议只能由宿主扩展，插件侧不得私自追加根字段

## 指令声明

需要页面上下文的指令，应在注册时显式声明：

```php
use PTAdmin\Addon\Service\DirectiveDefinition;

$manager->register(
    'demo-addon',
    DirectiveDefinition::make('lists')
        ->title('列表示例')
        ->handler(ListsDirective::class)
        ->method('handle')
        ->type('loop')
        ->context(DirectiveDefinition::CONTEXT_PAGE)
        ->cacheable(true)
);
```

如果某个指令不依赖页面上下文，就不要声明 `context(DirectiveDefinition::CONTEXT_PAGE)`。

## 模板变量规则

插件指令默认使用短语法：

```blade
@pt:lists
```

编译器会按指令名查找唯一的插件定义；如果多个插件声明了同名指令，需要重命名指令，或通过 `addon.directives.default_addon` 固定默认插件。

循环指令的模板变量规则固定如下：

- 显式写了 `id`，使用显式变量名
- 没写 `id`，统一使用默认 `$field`
- 不再根据指令名自动推导 `$archive`、`$tag`、`$pager` 这类变量

示例一，使用默认变量：

```blade
@pt:lists
    {{ data_get($field, 'title') }}
@pt:end
```

示例二，使用显式变量：

```blade
@pt:lists(id=item)
    {{ data_get($item, 'title') }}
@pt:end
```

推荐规则：

- 公共协议示例优先使用默认 `$field`
- 业务模板为了可读性，可以显式写 `id`
- 不要依赖编译器推导出的隐式变量名

## 调试输出指令

模板开发时可以使用通用调试指令查看当前上下文字段：

```blade
@pt:dump()
```

`@pt:dump()` 会根据当前运行时上下文自动推导最近的循环项；如果不在循环内，则尝试读取 `page.archive`、`page.category`、`page.tag`、`page.special`、`page.pagination` 或 `page`。

输出内容包括：

- 字段数
- 当前上下文层级
- 字段路径
- 字段说明
- 简易模板输出写法，例如 `{$archive.title}`
- 官网帮助文档链接，并自动带入当前推导出的 `directive`

循环中默认只输出一次，避免列表中重复刷屏：

```blade
@pt:lists(id=item)
    @pt:dump()
@pt:end
```

需要重复输出时可以显式关闭一次性输出：

```blade
@pt:dump(once=false)
```

也可以手动指定上下文路径和模板变量名：

```blade
@pt:dump(context="page",as="page")
```

默认帮助链接使用 `https://docs.pangtou.com`，可以通过 `docs_url` 覆盖：

```blade
@pt:dump(docs_url="https://docs.pangtou.com/cms/directives")
```

## 插件读取建议

插件内部读取上下文时，建议优先读取标准结构：

```php
$context = runtime_context_from_dto($dto);

$type = (string) data_get($context, 'resolved.type', '');
$pageId = (int) data_get($context, 'page.id', 0);
$currentPage = (int) data_get($context, 'page.pagination.current_page', 1);
$categoryId = (int) data_get($context, 'page.category.id', 0);
```

不建议：

- 直接依赖插件私有命名
- 在不同插件里发明不同的上下文字段名
- 继续把 `$page`、`$resolved` 之类模板变量当成上下文事实源

## 宿主注入与读取

宿主与插件建议统一遵守下面的使用方式：

1. 宿主在页面入口注入标准上下文
2. 编译器在运行时自动把当前上下文注入 `__pt_context`
3. 指令内部统一通过 `runtime_context_from_dto($dto)` 读取
4. 非指令场景统一通过 `runtime_context()` 或 `runtime_context_current()` 读取

常用辅助方法：

```php
runtime_context_put(array $context): void;
runtime_context_merge(array $context): void;
runtime_context_replace(array $context): void;
runtime_context_current(): array;
runtime_context_from_dto(?DirectivesDTO $dto = null): array;
runtime_context(?string $key = null, $default = null);
runtime_context_forget(): void;
runtime_context_normalize(array $context): array;
runtime_context_page(array $payload): array;
```

## 兼容策略

当前实现已以运行时上下文为唯一标准入口，新代码应只面向这份协议开发。

建议后续按这个顺序推进：

1. 页面入口统一注入 `runtime_context_page(...)`
2. 新增或重构指令统一声明 `context(DirectiveDefinition::CONTEXT_PAGE)`
3. 插件内部读取逻辑统一切到 `runtime_context_from_dto($dto)`
4. 模板层只把 `$page` 等变量当成展示数据，不再当成上下文事实源
