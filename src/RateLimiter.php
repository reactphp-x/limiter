<?php

namespace ReactphpX\Limiter;

use function ReactphpX\Limiter\getMilliseconds;
use React\Promise\PromiseInterface;

class RateLimiter
{

    protected TokenBucket $tokenBucket;

    protected int $curIntervalStart;

    protected int $tokensThisInterval;

    protected bool $fireImmediately;


    public function __construct(int $tokensPerInterval = 1024, int | string $interval = 1, bool $fireImmediately = false)
    {
        $this->tokenBucket = new TokenBucket($tokensPerInterval, $tokensPerInterval, $interval);

        $this->tokenBucket->setContent($tokensPerInterval);

        $this->curIntervalStart = getMilliseconds();

        $this->tokensThisInterval = 0;

        $this->fireImmediately = $fireImmediately;
    }

    public function removeTokens(int $count): PromiseInterface
    {
        $that = $this;
        return \React\Async\async(function () use ($that,$count) {

            if ($count > $that->tokenBucket->getBucketSize()) {
                throw new \Error(
                    "Requested tokens {$count} exceeds maximum tokens per interval {$that->tokenBucket->getBucketSize()}"
                );
            }

            $now = getMilliseconds();

            // 重置计数
            if ($now < $that->curIntervalStart || $now - $that->curIntervalStart >= $that->tokenBucket->getInterval()) {
                $that->curIntervalStart = $now;
                $that->tokensThisInterval = 0;
            }

            // 不够了要等下一个周期
            if ($count > $that->tokenBucket->getTokensPerInterval() - $that->tokensThisInterval) {
                if ($that->fireImmediately) {
                    return -1;
                } else {
                    $waitMs = ceil($that->curIntervalStart + $that->tokenBucket->getInterval() - $now);
                    \React\Async\delay($waitMs / 1000);
                    $remianingTokens = \React\Async\await($that->tokenBucket->removeTokens($count));
                    $that->tokensThisInterval += $count;
                    return $remianingTokens;
                }
            }
            $remianingTokens = \React\Async\await($that->tokenBucket->removeTokens($count));
            $that->tokensThisInterval += $count;
            return $remianingTokens;
        })();
    }

    public function tryRemoveTokens(int $count): bool
    {
        if ($count > $this->tokenBucket->getBucketSize()) return false;

        $now = getMilliseconds();

        // 重置计数
        if ($now < $this->curIntervalStart || $now - $this->curIntervalStart >= $this->tokenBucket->getInterval()) {
            $this->curIntervalStart = $now;
            $this->tokensThisInterval = 0;
        }

        // 不够了要等下一个周期
        if ($count > $this->tokenBucket->getTokensPerInterval() - $this->tokensThisInterval) return false;

        $removed = $this->tokenBucket->tryRemoveTokens($count);

        if ($removed) {
            $this->tokensThisInterval += $count;
        }

        return $removed;
    }

    public function getTokensRemaining(): int
    {
        return $this->tokenBucket->getTokensRemaining();
    }

    public function addTokens(int $count): void
    {
        $drip = (int) (($count * $this->tokenBucket->getInterval()) / $this->tokenBucket->getTokensPerInterval());
        $this->curIntervalStart = max(0, $this->curIntervalStart - $drip);
        $this->tokensThisInterval = max(0, $this->tokensThisInterval - $count);
        $this->tokenBucket->addTokens($count);
    }
}
