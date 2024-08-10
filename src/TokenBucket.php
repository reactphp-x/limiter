<?php

namespace ReactphpX\Limiter;

use React\Promise\PromiseInterface;
use function ReactphpX\Limiter\getMilliseconds;

class TokenBucket
{
    protected int $bucketSize;

    protected int $tokensPerInterval;

    protected int $interval;

    protected ?TokenBucket $parentBucket;

    protected int $content;

    protected int $lastDrip;

    public function __construct(int $bucketSize = 0, int $tokensPerInterval = 1024, int | string $interval = 1, ?TokenBucket $parentBucket = null)
    {

        $this->bucketSize = $bucketSize;
        $this->tokensPerInterval = $tokensPerInterval;

        if (is_string($interval)) {
            switch ($interval) {
                case "sec":
                case "second":
                    $this->interval = 1000;
                    break;
                case "min":
                case "minute":
                    $this->interval = 1000 * 60;
                    break;
                case "hr":
                case "hour":
                    $this->interval = 1000 * 60 * 60;
                    break;
                case "day":
                    $this->interval = 1000 * 60 * 60 * 24;
                    break;
                default:
                    throw new \Error("Invalid interval " + $interval);
            }
        } else {
            $this->interval = $interval;
        }

        $this->parentBucket = $parentBucket;
        $this->content = 0;
        $this->lastDrip = getMilliseconds();
    }

    public function removeTokens(int $count): PromiseInterface
    {

        $that = $this;
        return \React\Async\async(function () use ($count, $that) {

            if ($that->bucketSize === 0) {
                return \React\Promise\resolve(PHP_INT_MAX);
            }
    
            if ($count > $this->bucketSize) {
                throw new \Exception("Requested tokens {$count} exceeds bucket size {$this->bucketSize}");
            }
    
            $that->drip();
    
            $comeBackLater = function () use ($count, $that): PromiseInterface {
                return \React\Async\async(function () use ($that, $count): PromiseInterface {
                    $waitMs = ceil(($count - $that->content) * ($that->interval / $that->tokensPerInterval));
                    \React\Async\delay($waitMs/1000);
                    return $that->removeTokens($count);
                })();
            };
    
            if ($count > $that->content) {
                return $comeBackLater();
            }

            if ($that->parentBucket) {
                $remainingTokens = \React\Async\await($that->parentBucket->removeTokens($count));

                if ($count > $that->content) {
                    return $comeBackLater();
                }

                $that->content -= $count;

                return min($remainingTokens, $that->content);
            } else {
                $that->content -= $count;
                return $that->content;
            }
        })();
    }

    public function tryRemoveTokens(int $count): bool
    {
        if ($this->bucketSize === 0) return true;

        if ($count > $this->bucketSize) return false;

        $this->drip();

        if ($count > $this->content) return false;

        if ($this->parentBucket && !$this->parentBucket->tryRemoveTokens($count)) return false;
        
        $this->content -= $count;

        return true;
    }

    public function drip(): bool
    {
       if ($this->tokensPerInterval === 0) {
            $precContent = $this->content;
            $this->content = $this->bucketSize;
            return $this->content > $precContent;
       }

        $now = getMilliseconds();
        $deltaMS = max($now-$this->lastDrip, 0);
        $this->lastDrip = $now;


        $dripAmount = floor($deltaMS * ($this->tokensPerInterval / $this->interval));

        $precContent = $this->content;

        $this->content = min($this->bucketSize, $this->content + $dripAmount);

        return floor($this->content) > floor($precContent);
    }

    public function getTokensRemaining(): int
    {
        $this->drip();
        return $this->content;
    }


    public function getTokensPerInterval(): int
    {
        return $this->tokensPerInterval;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }
    public function getBucketSize(): int
    {
        return $this->bucketSize;
    }

    public function setContent(int $content): void
    {
        $this->content = $content;
    }
}
