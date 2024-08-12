<?php

namespace ReactphpX\Limiter\Tests;

use ReactphpX\Limiter\TokenBucket;
use function ReactphpX\Limiter\getMilliseconds;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Async\delay;
use ReactphpX\Concurrent\Concurrent;

class TokenBucketConcurrentTest extends TestCase
{

    public function testConcurrentBucket()
    {
        $bucket = new TokenBucket(20, 10, 1000);

        $concurrent = new Concurrent(1);

        $promises = [];
        $values = [];

        for ($i = 0; $i < 24; $i++) {
            $promise = async(function () use ($concurrent, $bucket, $i) {
                await($concurrent->concurrent(function() use ($bucket) {
                    return $bucket->removeTokens(1);
                }));
                return $i;
            })();
            $promises[] = $promise->then(function ($i) use (&$values) {
                $values[] = $i;
                return $i;
            });
        }

        await(all($promises));

        $this->assertCount(24, $values);
        $this->assertEquals([
            0,
            1,
            2,
            3,
            4,
            5,
            6,
            7,
            8,
            9,
            10,
            11,
            12,
            13,
            14,
            15,
            16,
            17,
            18,
            19,
            20,
            21,
            22,
            23
        ], $values);
    }

}