<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\RuntimeContextNormalizerInterface;

/**
 * 运行时上下文标准化器。
 *
 * 负责把业务层提供的页面数据收敛为统一协议，并补齐默认结构。
 */
class RuntimeContextNormalizer implements RuntimeContextNormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(array $context): array
    {
        $normalized = $this->empty();

        $normalized['version'] = (int) ($context['version'] ?? 1);
        $normalized['route'] = $context['route'] ?? null;
        $normalized['resolved'] = $this->normalizeResolved($context['resolved'] ?? []);
        $normalized['page'] = $this->normalizePage($context['page'] ?? []);
        $normalized['seo'] = $this->normalizeSeo($context['seo'] ?? []);
        $normalized['directives'] = \is_array($context['directives'] ?? null) ? $context['directives'] : [];

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function page(array $payload): array
    {
        $page = \is_array($payload['page'] ?? null) ? $payload['page'] : [];
        $resolved = \is_array($payload['resolved'] ?? null) ? $payload['resolved'] : [];

        return $this->normalize([
            'version' => $payload['version'] ?? 1,
            'route' => $payload['route'] ?? null,
            'resolved' => [
                'type' => data_get($resolved, 'type', ''),
            ],
            'page' => array_replace_recursive($page, [
                'id' => data_get($page, 'id'),
                'title' => data_get($page, 'title'),
                'subtitle' => data_get($page, 'subtitle'),
                'description' => data_get($page, 'description'),
                'keyword' => data_get($page, 'keyword'),
                'breadcrumb' => data_get($page, 'breadcrumb', []),
                'category' => data_get($page, 'category', []),
                'archive' => data_get($page, 'archive', []),
                'tag' => data_get($page, 'tag', []),
                'tags' => data_get($page, 'tags', []),
                'special' => data_get($page, 'special', []),
                'module' => data_get($page, 'module', []),
                'prev' => data_get($page, 'prev', []),
                'next' => data_get($page, 'next', []),
                'pagination' => [
                    'total' => data_get($page, 'total', data_get($page, 'pagination.total', 0)),
                    'last_page' => data_get($page, 'last_page', data_get($page, 'pagination.last_page', 0)),
                    'current_page' => data_get($page, 'current_page', data_get($page, 'pagination.current_page', 1)),
                    'per_page' => data_get($page, 'per_page', data_get($page, 'pagination.per_page', 0)),
                ],
            ]),
            'seo' => [
                'title' => data_get($page, 'seo.title', ''),
                'keywords' => data_get($page, 'seo.keywords', ''),
                'description' => data_get($page, 'seo.description', ''),
                'canonical' => data_get($page, 'canonical', ''),
                'robots' => data_get($page, 'robots', data_get($page, 'meta.robots', '')),
                'open_graph' => data_get($page, 'open_graph', []),
                'twitter' => data_get($page, 'twitter', []),
                'structured_data' => data_get($page, 'structured_data', []),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function empty(): array
    {
        return [
            'version' => 1,
            'route' => null,
            'resolved' => [
                'type' => '',
            ],
            'page' => [
                'id' => 0,
                'title' => '',
                'subtitle' => '',
                'description' => '',
                'keyword' => '',
                'breadcrumb' => [],
                'category' => [],
                'archive' => [],
                'tag' => [],
                'tags' => [],
                'special' => [],
                'module' => [],
                'prev' => [],
                'next' => [],
                'pagination' => [
                    'total' => 0,
                    'last_page' => 0,
                    'current_page' => 1,
                    'per_page' => 0,
                ],
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
            'directives' => [],
        ];
    }

    /**
     * @param mixed $resolved
     *
     * @return array<string, mixed>
     */
    private function normalizeResolved($resolved): array
    {
        $data = \is_array($resolved) ? $resolved : [];

        return [
            'type' => (string) ($data['type'] ?? ''),
        ];
    }

    /**
     * @param mixed $page
     *
     * @return array<string, mixed>
     */
    private function normalizePage($page): array
    {
        $current = $this->empty()['page'];
        $data = \is_array($page) ? $page : [];

        $current['id'] = (int) ($data['id'] ?? 0);
        $current['title'] = (string) ($data['title'] ?? '');
        $current['subtitle'] = (string) ($data['subtitle'] ?? '');
        $current['description'] = (string) ($data['description'] ?? '');
        $current['keyword'] = (string) ($data['keyword'] ?? '');
        $current['breadcrumb'] = \is_array($data['breadcrumb'] ?? null) ? array_values($data['breadcrumb']) : [];
        $current['category'] = \is_array($data['category'] ?? null) ? $data['category'] : [];
        $current['archive'] = \is_array($data['archive'] ?? null) ? $data['archive'] : [];
        $current['tag'] = \is_array($data['tag'] ?? null) ? $data['tag'] : [];
        $current['tags'] = \is_array($data['tags'] ?? null) ? array_values($data['tags']) : [];
        $current['special'] = \is_array($data['special'] ?? null) ? $data['special'] : [];
        $current['module'] = \is_array($data['module'] ?? null) ? $data['module'] : [];
        $current['prev'] = \is_array($data['prev'] ?? null) ? $data['prev'] : [];
        $current['next'] = \is_array($data['next'] ?? null) ? $data['next'] : [];
        $current['pagination'] = [
            'total' => max(0, (int) data_get($data, 'pagination.total', 0)),
            'last_page' => max(0, (int) data_get($data, 'pagination.last_page', 0)),
            'current_page' => max(1, (int) data_get($data, 'pagination.current_page', 1)),
            'per_page' => max(0, (int) data_get($data, 'pagination.per_page', 0)),
        ];

        return array_replace_recursive($data, $current);
    }

    /**
     * @param mixed $seo
     *
     * @return array<string, mixed>
     */
    private function normalizeSeo($seo): array
    {
        $current = $this->empty()['seo'];
        $data = \is_array($seo) ? $seo : [];

        $current['title'] = (string) ($data['title'] ?? '');
        $current['keywords'] = (string) ($data['keywords'] ?? '');
        $current['description'] = (string) ($data['description'] ?? '');
        $current['canonical'] = (string) ($data['canonical'] ?? '');
        $current['robots'] = (string) ($data['robots'] ?? '');
        $current['open_graph'] = \is_array($data['open_graph'] ?? null) ? $data['open_graph'] : [];
        $current['twitter'] = \is_array($data['twitter'] ?? null) ? $data['twitter'] : [];
        $current['structured_data'] = \is_array($data['structured_data'] ?? null) ? array_values($data['structured_data']) : [];

        return $current;
    }
}
