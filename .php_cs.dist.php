<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Addon】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】，并保留所有权利。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

$date = date('Y');

$header = <<<EOF
 ============================================================================
 ******************************【PTAdmin/Addon】******************************
 ============================================================================
 Copyright (c) 2022-{$date} 【重庆胖头网络技术有限公司】，并保留所有权利。
 ============================================================================
 站点首页:  https://www.pangtou.com
 文档地址:  https://docs.pangtou.com
 联系邮箱:  vip@pangtou.com
EOF;

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('vendor') //排除
    ->exclude('tests') // 排除
    ->in(__DIR__)
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

$rule = [
    '@PHP71Migration:risky' => true,
    '@PHPUnit75Migration:risky' => true,
    '@PhpCsFixer' => true,
    '@PhpCsFixer:risky' => true,
    'general_phpdoc_annotation_remove' => ['annotations' => ['expectedDeprecation']],
    'phpdoc_add_missing_param_annotation' => true,    // 添加缺少的 Phpdoc @param参数
    'no_empty_statement' => true,    // 删除多余的分号
    'no_superfluous_phpdoc_tags' => false,   // return 参数需要
    'header_comment' => ['header' => $header, 'comment_type' => 'PHPDoc'],
];

$config = new PhpCsFixer\Config();
$config->setRiskyAllowed(true)
    ->setRules($rule)
    ->setFinder($finder)
;

return $config;
