<?php

namespace Wpjscc\Tests\React\Limiter;

use Wpjscc\React\Limiter\TokenBucket;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Async\delay;

class TokenBucketTest extends TestCase
{
    public function testHello()
    {
        // var_dump(hrtime());
        $this->assertEquals('hello', 'hello');
    }

    public function testEvertSecond()
    {

        $bucket = new TokenBucket(20, 10, 1000);

        delay(0.9);

        $this->assertEquals(9, $bucket->getTokensRemaining());
        await($bucket->removeTokens(9));

        delay(0.1);
        $this->assertEquals(1, $bucket->getTokensRemaining());

        await($bucket->removeTokens(1));
        $this->assertEquals(0, $bucket->getTokensRemaining());
        delay(1);
        $this->assertEquals(10, $bucket->getTokensRemaining());
        
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
}