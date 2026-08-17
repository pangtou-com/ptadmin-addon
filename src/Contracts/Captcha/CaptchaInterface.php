<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Captcha;

use PTAdmin\Addon\Contracts\Captcha\Protocol\ChallengeProviderInterface;

/**
 * 反自动化挑战提供者接口。
 *
 * 适用于图形、行为和第三方 widget/token 挑战；协议类型和渲染器由
 * CaptchaDefinition 声明，提供者私有校验数据通过 ChallengeCreateResult
 * 的 private_state 返回给宿主保存，不应直接发送到前端。
 */
interface CaptchaInterface extends ChallengeProviderInterface
{
}
