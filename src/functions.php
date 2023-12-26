<?php

namespace Reactphp\Framework\Limiter;

function getMilliseconds(): int {

    list($seconds, $nanoseconds) = hrtime();

    $seconds =  $seconds * 1e3 + floor($nanoseconds / 1e6);

    return $seconds;

}