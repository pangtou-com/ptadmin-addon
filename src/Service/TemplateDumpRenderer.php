<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

final class TemplateDumpRenderer
{
    /**
     * @var array<string, bool>
     */
    private static array $renderedContexts = [];

    private const DEFAULT_DOCS_URL = 'https://docs.pangtou.com';

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes = []): string
    {
        [$label, $value, $contextKey, $depth] = $this->resolveValue($attributes);
        if ($this->shouldRenderOnce($attributes)) {
            $renderKey = $this->renderKey($label, $contextKey, $depth);
            if (isset(self::$renderedContexts[$renderKey])) {
                return '';
            }
            self::$renderedContexts[$renderKey] = true;
        }

        if ([] === $value) {
            return $this->wrap($label, '<p class="pt-dump__empty">暂无可输出的上下文数据。</p>');
        }

        return $this->wrap($label, $this->renderSummary($value, $depth, $label, $attributes).$this->renderFields($value, $label));
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array{0:string,1:array<string, mixed>,2:string,3:int|null}
     */
    private function resolveValue(array $attributes): array
    {
        $context = runtime_context_current();
        $contextKey = trim((string) ($attributes['context'] ?? ''));
        if ('' !== $contextKey) {
            return [$this->normalizeLabel((string) ($attributes['as'] ?? $contextKey)), $this->arrayValue(data_get($context, $contextKey, [])), $contextKey, null];
        }

        $directives = data_get($context, 'directives', []);
        if (\is_array($directives)) {
            $resolved = $this->resolveLatestDirectiveContext($directives, 'directives');
            if (null !== $resolved) {
                return $resolved;
            }
        }

        foreach ([
            'page.archive' => 'archive',
            'page.category' => 'category',
            'page.tag' => 'tag',
            'page.special' => 'special',
            'page.pagination' => 'pagination',
            'page' => 'page',
        ] as $key => $label) {
            $value = $this->arrayValue(data_get($context, $key, []));
            if ([] !== $value) {
                return [$label, $value, $key, null];
            }
        }

        return ['field', [], '', null];
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array{0:string,1:array<string, mixed>,2:string,3:int}|null
     */
    private function resolveLatestDirectiveContext(array $node, string $prefix): ?array
    {
        $latest = null;
        foreach ($node as $key => $value) {
            $path = $prefix.'.'.$key;
            if ($this->isDirectiveStack($value)) {
                $stack = array_values($value);
                $item = $stack[\count($stack) - 1] ?? [];
                $latest = [$this->labelFromContextKey($path), $this->arrayValue($item), $path, \count($stack)];

                continue;
            }

            if (\is_array($value)) {
                $nested = $this->resolveLatestDirectiveContext($value, $path);
                if (null !== $nested) {
                    $latest = $nested;
                }
            }
        }

        return $latest;
    }

    /**
     * @param mixed $value
     */
    private function isDirectiveStack($value): bool
    {
        if (!\is_array($value) || [] === $value) {
            return false;
        }
        if (array_keys($value) !== range(0, \count($value) - 1)) {
            return false;
        }

        $last = $value[\array_key_last($value)] ?? null;

        return \is_array($last);
    }

    private function labelFromContextKey(string $key): string
    {
        $segments = array_values(array_filter(explode('.', $key), static fn (string $segment): bool => '' !== $segment));
        $last = (string) end($segments);

        return $this->normalizeLabel($last);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function renderSummary(array $value, ?int $depth, string $label, array $attributes): string
    {
        $html = '<div class="pt-dump__summary">';
        $html .= '<div class="pt-dump__summary-main">';
        $html .= '<span>字段数：'.\count($this->flatten($value)).'</span>';
        if (null !== $depth) {
            $html .= '<span>当前上下文层级：'.$depth.'</span>';
        }
        $html .= '</div>';
        $html .= '<a class="pt-dump__help" href="'.$this->escape($this->docsUrl($label, $attributes)).'" target="_blank" rel="noopener noreferrer">查看帮助文档</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function renderFields(array $value, string $label): string
    {
        $rows = '';
        foreach ($this->fieldRows($value, $label) as $row) {
            $rows .= '<tr>'
                .'<td><code>'.$this->escape($row['field']).'</code></td>'
                .'<td>'.$this->escape($row['type']).'</td>'
                .'<td>'.$this->escape($this->describeField($label, $row['field'])).'</td>'
                .'<td><code>'.$this->escape($row['example']).'</code></td>'
                .'</tr>';
        }

        if ('' === $rows) {
            $rows = '<tr><td colspan="4">暂无字段。</td></tr>';
        }

        return '<div class="pt-dump__table-wrap"><table class="pt-dump__table">'
            .'<thead><tr><th>字段</th><th>类型</th><th>说明</th><th>模板输出</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table></div>';
    }

    private function wrap(string $label, string $body): string
    {
        return '<section class="pt-dump" style="'.$this->escape($this->style()).'">'
            .'<style>'.$this->css().'</style>'
            .'<div class="pt-dump__title">@pt:dump('.$this->escape($label).')</div>'
            .$body
            .'</section>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function shouldRenderOnce(array $attributes): bool
    {
        $value = $attributes['once'] ?? true;
        if (\is_bool($value)) {
            return $value;
        }

        return !\in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function docsUrl(string $label, array $attributes): string
    {
        $baseUrl = trim((string) ($attributes['docs_url'] ?? self::DEFAULT_DOCS_URL));
        if ('' === $baseUrl) {
            $baseUrl = self::DEFAULT_DOCS_URL;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'directive='.rawurlencode($label);
    }

    private function renderKey(string $label, string $contextKey, ?int $depth): string
    {
        return implode(':', [
            (string) runtime_context('route', request()->getPathInfo()),
            (string) spl_object_id(request()),
            $contextKey,
            $label,
            (string) ($depth ?? 0),
        ]);
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function arrayValue($value): array
    {
        return \is_array($value) ? $value : [];
    }

    private function normalizeLabel(string $label): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_]+/', '_', trim($label));
        $label = \is_string($normalized) && '' !== $normalized ? $normalized : 'field';
        $label = trim($label, '_');

        return '' === $label ? 'field' : $label;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function flatten(array $value, string $prefix = ''): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (\is_int($key)) {
                continue;
            }

            $field = '' === $prefix ? $key : $prefix.'.'.$key;
            if (\is_array($item) && $this->isAssoc($item)) {
                $result += $this->flatten($item, $field);

                continue;
            }

            $result[$field] = $item;
        }

        return $result;
    }

    private function isAssoc(array $value): bool
    {
        return [] !== $value && array_keys($value) !== range(0, \count($value) - 1);
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<int, array{field:string,type:string,example:string}>
     */
    private function fieldRows(array $value, string $label, string $prefix = ''): array
    {
        $rows = [];
        foreach ($value as $key => $item) {
            if (\is_int($key)) {
                continue;
            }

            $field = '' === $prefix ? $key : $prefix.'.'.$key;
            $rows[] = [
                'field' => $field,
                'type' => $this->describeType($item),
                'example' => $this->templateExample($label, $field, $item),
            ];

            if (\is_array($item) && $this->isAssoc($item)) {
                $rows = array_merge($rows, $this->fieldRows($item, $label, $field));
            }
        }

        return $rows;
    }

    /**
     * @param mixed $value
     */
    private function describeType($value): string
    {
        if (null === $value) {
            return 'null';
        }
        if (\is_bool($value)) {
            return 'boolean';
        }
        if (\is_int($value) || \is_float($value)) {
            return 'number';
        }
        if (\is_string($value)) {
            return 'string';
        }
        if (\is_object($value)) {
            return 'object';
        }
        if (!\is_array($value)) {
            return 'mixed';
        }
        if ($this->isAssoc($value)) {
            return 'object';
        }
        if ([] === $value) {
            return 'list';
        }

        $types = array_values(array_unique(array_map(function ($item): string {
            return $this->baseType($item);
        }, $value)));

        return 1 === \count($types) ? 'list<'.$types[0].'>' : 'list<mixed>';
    }

    /**
     * @param mixed $value
     */
    private function baseType($value): string
    {
        if (null === $value) {
            return 'null';
        }
        if (\is_bool($value)) {
            return 'boolean';
        }
        if (\is_int($value) || \is_float($value)) {
            return 'number';
        }
        if (\is_string($value)) {
            return 'string';
        }
        if (\is_array($value) && $this->isAssoc($value)) {
            return 'object';
        }
        if (\is_array($value)) {
            return 'array';
        }
        if (\is_object($value)) {
            return 'object';
        }

        return 'mixed';
    }

    /**
     * @param mixed $value
     */
    private function templateExample(string $label, string $field, $value): string
    {
        if (\is_array($value)) {
            if ($this->isAssoc($value)) {
                $childPath = $this->firstScalarPath($value);
                if (null !== $childPath) {
                    return '{$'.$label.'.'.$field.'.'.$childPath.'}';
                }

                return '{{ json_encode('.$this->bracketAccess($label, $field).' ?? [], JSON_UNESCAPED_UNICODE) }}';
            }

            if ([] === $value) {
                return '@foreach('.$this->bracketAccess($label, $field).' ?? [] as $item) ... @endforeach';
            }

            $first = $this->firstNonNull($value);
            if (\is_array($first) && $this->isAssoc($first)) {
                $variable = $this->loopVariable($field);
                $childPath = $this->firstScalarPath($first);
                $inner = null === $childPath ? '{{ json_encode('.$variable.', JSON_UNESCAPED_UNICODE) }}' : '{$'.ltrim($variable, '$').'.'.$childPath.'}';

                return '@foreach('.$this->bracketAccess($label, $field).' ?? [] as '.$variable.') '.$inner.' @endforeach';
            }

            if ($this->isScalarList($value)) {
                return '{{ implode(\',\', '.$this->bracketAccess($label, $field).' ?? []) }}';
            }

            return '{{ json_encode('.$this->bracketAccess($label, $field).' ?? [], JSON_UNESCAPED_UNICODE) }}';
        }

        if (\is_object($value)) {
            return '{{ json_encode('.$this->bracketAccess($label, $field).' ?? null, JSON_UNESCAPED_UNICODE) }}';
        }

        return '{$'.$label.'.'.$field.'}';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function firstScalarPath(array $value, string $prefix = ''): ?string
    {
        foreach ($value as $key => $item) {
            if (\is_int($key)) {
                continue;
            }

            $field = '' === $prefix ? $key : $prefix.'.'.$key;
            if (\is_array($item) && $this->isAssoc($item)) {
                $nested = $this->firstScalarPath($item, $field);
                if (null !== $nested) {
                    return $nested;
                }

                continue;
            }

            if (!\is_array($item) && !\is_object($item)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $value
     *
     * @return mixed
     */
    private function firstNonNull(array $value)
    {
        foreach ($value as $item) {
            if (null !== $item) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $value
     */
    private function isScalarList(array $value): bool
    {
        foreach ($value as $item) {
            if (null !== $item && !\is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    private function bracketAccess(string $label, string $field): string
    {
        $expression = '$'.$label;
        foreach (explode('.', $field) as $segment) {
            $expression .= "['".str_replace("'", "\\'", $segment)."']";
        }

        return $expression;
    }

    private function loopVariable(string $field): string
    {
        $segments = explode('.', $field);
        $name = end($segments);
        if (!\is_string($name)) {
            $name = 'item';
        }
        if (\strlen($name) > 3 && 'ies' === substr($name, -3)) {
            $name = substr($name, 0, -3).'y';
        } elseif (\strlen($name) > 1 && 's' === substr($name, -1)) {
            $name = substr($name, 0, -1);
        }
        $normalized = preg_replace('/[^A-Za-z0-9_]+/', '_', $name);
        $name = \is_string($normalized) && '' !== $normalized ? $normalized : 'item';
        $name = trim($name, '_');
        if ('' === $name || !preg_match('/^[A-Za-z_]/', $name)) {
            $name = 'item';
        }

        return '$'.$name;
    }

    private function describeField(string $label, string $field): string
    {
        $maps = $this->fieldDescriptions();
        $labelMap = $maps[$label] ?? [];
        if (isset($labelMap[$field])) {
            return $labelMap[$field];
        }

        $commonMap = $maps['common'] ?? [];
        if (isset($commonMap[$field])) {
            return $commonMap[$field];
        }

        $segments = explode('.', $field);
        $last = end($segments);
        if (\is_string($last) && isset($commonMap[$last])) {
            return $commonMap[$last];
        }

        return $field;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function fieldDescriptions(): array
    {
        return [
            'common' => [
                'id' => 'ID',
                'title' => '标题',
                'subtitle' => '副标题',
                'alias' => '别名',
                'name' => '名称',
                'url' => '访问地址',
                'route' => '路由地址',
                'description' => '描述',
                'summary' => '摘要',
                'cover' => '封面图',
                'image' => '图片',
                'icon' => '图标',
                'status' => '状态',
                'weight' => '排序值',
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'archive' => [
                'id' => '文档 ID',
                'category_id' => '所属栏目 ID',
                'mod_id' => '所属模型 ID',
                'content_id' => '内容 ID',
                'title' => '文档标题',
                'subtitle' => '文档副标题',
                'description' => '文档摘要',
                'summary' => '文档摘要',
                'cover' => '文档封面图',
                'author' => '文档作者',
                'source' => '文档来源',
                'views' => '浏览量',
                'publish_at' => '发布时间',
                'url' => '文档访问地址',
                'category.title' => '所属栏目名称',
                'category.alias' => '所属栏目别名',
                'content' => '内容扩展字段',
                'tags' => '关联标签列表',
            ],
            'category' => [
                'id' => '栏目 ID',
                'parent_id' => '上级栏目 ID',
                'title' => '栏目标题',
                'subtitle' => '栏目副标题',
                'alias' => '栏目别名',
                'type' => '栏目类型',
                'url' => '栏目访问地址',
                'cover' => '栏目封面图',
                'image' => '栏目图片',
                'icon' => '栏目图标',
                'description' => '栏目描述',
                'lang' => '语言标识',
                'mod_id' => '绑定模型 ID',
            ],
            'cat' => [
                'id' => '栏目 ID',
                'parent_id' => '上级栏目 ID',
                'title' => '栏目标题',
                'subtitle' => '栏目副标题',
                'alias' => '栏目别名',
                'type' => '栏目类型',
                'url' => '栏目访问地址',
                'current_url' => '栏目当前访问地址',
                'cover' => '栏目封面图',
                'image' => '栏目图片',
                'icon' => '栏目图标',
                'description' => '栏目描述',
                'lang' => '语言标识',
                'mod_id' => '绑定模型 ID',
            ],
            'nav' => [
                'id' => '导航 ID',
                'parent_id' => '父级导航 ID',
                'title' => '导航标题',
                'type' => '导航类型',
                'url' => '导航地址',
                'target' => '打开方式',
                'children' => '子导航列表',
                'has_children' => '是否存在子导航',
                'children_count' => '子导航数量',
                'classes' => '合并后的样式类名',
            ],
            'child' => [
                'id' => '子级 ID',
                'parent_id' => '父级 ID',
                'title' => '子级标题',
                'url' => '子级访问地址',
                'children' => '下级列表',
                'has_children' => '是否存在下级',
                'children_count' => '下级数量',
            ],
            'tag' => [
                'id' => '标签 ID',
                'name' => '标签名称',
                'alias' => '标签别名',
                'url' => '标签访问地址',
                'intro' => '标签介绍',
                'archive_count' => '关联文档数量',
                'count' => '关联文档数量',
            ],
            'special' => [
                'id' => '专题 ID',
                'title' => '专题标题',
                'alias' => '专题别名',
                'url' => '专题访问地址',
                'intro' => '专题介绍',
                'cover' => '专题封面图',
            ],
            'pager' => [
                'total' => '总条数',
                'current_page' => '当前页码',
                'per_page' => '每页条数',
                'last_page' => '总页数',
                'has_prev' => '是否有上一页',
                'has_next' => '是否有下一页',
            ],
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_SUBSTITUTE, 'UTF-8');
    }

    private function style(): string
    {
        return 'margin:12px 0;padding:12px;border:1px solid #d9dee8;border-radius:6px;background:#fff;color:#1f2937;font-size:13px;line-height:1.5;';
    }

    private function css(): string
    {
        return '.pt-dump__title{font-weight:600;margin-bottom:8px}.pt-dump__summary{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;color:#4b5563}.pt-dump__summary-main{display:flex;gap:12px;flex-wrap:wrap}.pt-dump__help{color:#2563eb;text-decoration:none;white-space:nowrap}.pt-dump__help:hover{text-decoration:underline}.pt-dump__table-wrap{overflow:auto}.pt-dump__table{width:100%;border-collapse:collapse}.pt-dump__table th,.pt-dump__table td{padding:6px 8px;border:1px solid #e5e7eb;text-align:left;vertical-align:top}.pt-dump__table th{background:#f8fafc;font-weight:600}.pt-dump code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px}.pt-dump__empty{margin:0;color:#6b7280}';
    }
}
