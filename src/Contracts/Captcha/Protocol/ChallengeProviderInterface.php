<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Captcha\Protocol;

use PTAdmin\Addon\Contracts\Captcha\Data\ChallengeCreateRequest;
use PTAdmin\Addon\Contracts\Captcha\Data\ChallengeCreateResult;
use PTAdmin\Addon\Contracts\Captcha\Data\ChallengeRefreshRequest;
use PTAdmin\Addon\Contracts\Captcha\Data\ChallengeVerifyRequest;
use PTAdmin\Addon\Contracts\Captcha\Data\ChallengeVerifyResult;

interface ChallengeProviderInterface
{
    public function definition(): CaptchaDefinition;

    public function create(ChallengeCreateRequest $request): ChallengeCreateResult;

    public function verify(ChallengeVerifyRequest $request): ChallengeVerifyResult;

    public function refresh(ChallengeRefreshRequest $request): ChallengeCreateResult;
}
