<?php

namespace Parallel\Helper;

class StringHelper
{
    /**
     * @param string $string
     * @return string
     */
    public static function sanitize(string $string): string
    {
        return trim(preg_replace('/\s+/', ' ', $string));
    }
}
