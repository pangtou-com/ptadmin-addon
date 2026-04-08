<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

if (!interface_exists(PromiseInterface::class)) {
    interface PromiseInterface
    {
        public function then(callable $onFulfilled = null, callable $onRejected = null);

        public function otherwise(callable $onRejected);

        public function wait($unwrap = true);

        public function getState();

        public function resolve($value);

        public function reject($reason);

        public function cancel();
    }

    final class FulfilledPromise implements PromiseInterface
    {
        /** @var mixed */
        private $value;

        public function __construct($value)
        {
            $this->value = $value;
        }

        public function then(callable $onFulfilled = null, callable $onRejected = null)
        {
            if (null === $onFulfilled) {
                return new self($this->value);
            }

            return new self($onFulfilled($this->value));
        }

        public function otherwise(callable $onRejected)
        {
            return $this;
        }

        public function wait($unwrap = true)
        {
            return $this->value;
        }

        public function getState()
        {
            return 'fulfilled';
        }

        public function resolve($value)
        {
            $this->value = $value;
        }

        public function reject($reason)
        {
            throw $reason instanceof \Throwable ? $reason : new \RuntimeException((string) $reason);
        }

        public function cancel()
        {
        }
    }

    final class Create
    {
        public static function promiseFor($value): PromiseInterface
        {
            return new FulfilledPromise($value);
        }
    }

    function promise_for($value): PromiseInterface
    {
        return Create::promiseFor($value);
    }
}
