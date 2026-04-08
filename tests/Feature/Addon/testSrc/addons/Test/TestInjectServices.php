<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test;

use PTAdmin\Addon\Contracts\Auth\AuthInterface;
use PTAdmin\Addon\Contracts\Notify\NotifyInterface;
use PTAdmin\Addon\Contracts\Storage\StorageInterface;
use PTAdmin\Addon\Service\InjectPayload;

class TestInjectServices implements AuthInterface, NotifyInterface, StorageInterface
{
    public function supports(string $operation): bool
    {
        return \in_array($operation, [
            'getAuthorizeUrl',
            'handleCallback',
            'getUser',
            'refreshToken',
            'send',
            'sendBatch',
            'parseCallback',
            'upload',
            'delete',
            'exists',
            'temporaryUrl',
        ], true);
    }

    public function getAuthorizeUrl(InjectPayload $payload): array
    {
        return [
            'group' => 'auth',
            'action' => 'getAuthorizeUrl',
            'scene' => $payload->get('scene'),
            'url' => 'https://example.test/oauth',
        ];
    }

    public function handleCallback(InjectPayload $payload): array
    {
        return [
            'group' => 'auth',
            'action' => 'handleCallback',
            'code' => $payload->get('code'),
            'token' => 'login-token',
        ];
    }

    public function getUser(InjectPayload $payload): array
    {
        return [
            'group' => 'auth',
            'action' => 'getUser',
            'openid' => $payload->get('openid'),
        ];
    }

    public function refreshToken(InjectPayload $payload): array
    {
        return [
            'group' => 'auth',
            'action' => 'refreshToken',
            'refresh_token' => $payload->get('refresh_token'),
        ];
    }

    public function send(InjectPayload $payload): array
    {
        return [
            'group' => 'notify',
            'action' => 'send',
            'channel' => $payload->get('channel'),
            'message' => $payload->get('message'),
        ];
    }

    public function sendBatch(InjectPayload $payload): array
    {
        return [
            'group' => 'notify',
            'action' => 'sendBatch',
            'count' => count($payload->get('receivers', [])),
        ];
    }

    public function query(InjectPayload $payload): array
    {
        return [
            'group' => 'notify',
            'action' => 'query',
            'message_id' => $payload->get('message_id'),
            'status' => 'delivered',
        ];
    }

    public function parseCallback(InjectPayload $payload): array
    {
        return [
            'group' => 'notify',
            'action' => 'parseCallback',
            'message_id' => $payload->get('message_id'),
        ];
    }

    public function upload(InjectPayload $payload): array
    {
        return [
            'group' => 'storage',
            'action' => 'upload',
            'disk' => $payload->get('disk', 'oss'),
            'path' => $payload->get('path'),
        ];
    }

    public function delete(InjectPayload $payload): bool
    {
        return !blank($payload->get('path'));
    }

    public function exists(InjectPayload $payload): bool
    {
        return 'uploads/demo.png' === $payload->get('path');
    }

    public function temporaryUrl(InjectPayload $payload): array
    {
        return [
            'group' => 'storage',
            'action' => 'temporaryUrl',
            'url' => 'https://example.test/temp/'.ltrim((string) $payload->get('path'), '/'),
        ];
    }
}
