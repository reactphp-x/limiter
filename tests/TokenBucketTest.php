<?php

namespace Reactphp\Framework\Limiter\Tests;

use Reactphp\Framework\Limiter\TokenBucket;
use function Reactphp\Framework\Limiter\getMilliseconds;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Async\delay;

class TokenBucketTest extends TestCase
{
    const TIMING_EPSILON = 10;

    public function testHello()
    {
        // var_dump(hrtime());
        $this->assertEquals('hello', 'hello');
    }

    public function testAsyncBucket()
    {
        $bucket = new TokenBucket(20, 10, 1000);

        $promise = async(function () use ($bucket) {
            delay(1);
            return $bucket->removeTokens(9);
        })();

        $value = await($promise);
        $this->assertEquals(1, $value);


    }

    // https://github.com/jhurliman/node-rate-limiter/blob/main/src/TokenBucket.test.ts
    //removing 10 tokens takes 1 second 
    public function testRemoving10TokendsTakes1Second()
    {
        $bucket = new TokenBucket(10, 1, 100);

        $start = getMilliseconds();
        $remainingTokens = await($bucket->removeTokens(10));
        $end = getMilliseconds();

        $this->assertTrue(true, $end-$start >= self::TIMING_EPSILON);
        $this->assertEquals(0, $remainingTokens);
        $this->assertEquals(0, $bucket->getTokensRemaining());
    }

    // removing another 10 tokens takes 1 second
    public function testRemovingAnother10TokensTakes1Second()
    {
        $bucket = new TokenBucket(10, 1, 100);

        await($bucket->removeTokens(10));

        $start = getMilliseconds();
        $remainingTokens = await($bucket->removeTokens(10));
        $end = getMilliseconds();

        $this->assertTrue(true, $end-$start >= self::TIMING_EPSILON);
        $this->assertEquals(0, $remainingTokens);
        $this->assertEquals(0, $bucket->getTokensRemaining());
    }

    // removing 20 tokens takes 2 seconds
    public function testRemoving20TokensTakes2Seconds()
    {
        $bucket = new TokenBucket(10, 1, 100);
        delay(2);

        $start = getMilliseconds();
        $remainingTokens = await($bucket->removeTokens(10));
        $end = getMilliseconds();

        $this->assertTrue(true, $end-$start >= self::TIMING_EPSILON);
        $this->assertEquals(0, $remainingTokens);
        $this->assertEquals(0, $bucket->getTokensRemaining());
    }

    // removing 1 token takes 100ms
    public function testRemoving1TokenTakes100ms()
    {
        $bucket = new TokenBucket(10, 1, 100);

        $start = getMilliseconds();
        $remainingTokens = await($bucket->removeTokens(1));
        $end = getMilliseconds();

        $this->assertTrue(true, $end-$start >= self::TIMING_EPSILON);
        $this->assertEquals(0, $remainingTokens);
        $this->assertEquals(0, $bucket->getTokensRemaining());
    }


}