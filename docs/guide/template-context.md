# 模板上下文协议

`PTAdmin/Addon` 为模板指令定义统一的运行时上下文协议。

这份协议的目标只有一个：宿主负责提供页面上下文，插件按统一结构读取，不再由各个插件各自定义一套临时字段。

## 协议定位

统一规则如下：

- `__pt_context` 是模板运行时的内部上下文字段
- 上下文由宿主负责构建
- 插件只能读取上下文，不能写入或扩展私有结构
- 指令通过 `DirectiveDefinition::context(DirectiveDefinition::CONTEXT_PAGE)` 声明自己需要页面上下文
- 未显式声明 `id` 时，循环指令默认变量统一为 `$field`

这意味着：

- CMS、商城、论坛等插件可以共享同一套页面协议
- SEO、分页、当前页识别、详情上下篇等能力可以跨插件复用
- 模板作者不需要记忆“不同插件有不同上下文格式”

## 宿主职责

宿主不直接手写 `__pt_context`，而是按约定提供模板变量：

- `$route`
- `$resolved`
- `$page`

编译器在遇到声明了 `context(DirectiveDefinition::CONTEXT_PAGE)` 的指令时，会自动将这三个变量收敛为统一的 `__pt_context`。

最小示例：

```php
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

循环指令的模板变量规则固定如下：

- 显式写了 `id`，使用显式变量名
- 没写 `id`，统一使用默认 `$field`
- 不再根据指令名自动推导 `$archive`、`$tag`、`$pager` 这类变量

示例一，使用默认变量：

```blade
@pt:demo::lists
    {{ data_get($field, 'title') }}
@pt:end
```

示例二，使用显式变量：

```blade
@pt:demo::lists(id=item)
    {{ data_get($item, 'title') }}
@pt:end
```

推荐规则：

- 公共协议示例优先使用默认 `$field`
- 业务模板为了可读性，可以显式写 `id`
- 不要依赖编译器推导出的隐式变量名

## 插件读取建议

插件内部读取上下文时，建议优先读取标准结构：

```php
$context = (array) $dto->getAttribute('__pt_context', []);

$type = (string) data_get($context, 'resolved.type', '');
$pageId = (int) data_get($context, 'page.id', 0);
$currentPage = (int) data_get($context, 'page.pagination.current_page', 1);
$categoryId = (int) data_get($context, 'page.category.id', 0);
```

不建议：

- 直接依赖插件私有命名
- 在不同插件里发明不同的上下文字段名
- 要求模板作者显式传递本可由宿主识别的当前页信息

## 兼容策略

当前实现允许旧结构向新结构过渡，但新代码应只面向这份标准协议开发。

建议后续按这个顺序推进：

1. 新增或重构指令时统一声明 `context(DirectiveDefinition::CONTEXT_PAGE)`
2. 新模板默认使用 `$field` 或显式 `id`
3. 插件内部读取逻辑逐步切到 `resolved/page/seo` 标准结构
4. 完成迁移后，再移除旧字段兼容
