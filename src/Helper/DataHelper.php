<?php

namespace Parallel\Helper;

class DataHelper
{
    /**
     * @param int $bytes
     * @return string
     */
    public static function convertBytes(int $bytes): string
    {
        $megabytes = $bytes / 1024 / 1024;
        if ($megabytes < 1024) {
            return number_format($megabytes, 1) . ' MB';
        }

        $gigabytes = $megabytes / 1024;
        if ($megabytes < 1024) {
            return number_format($gigabytes, 1) . ' GB';
        }

        $terabytes = $gigabytes / 1024;
        return number_format($terabytes, 1) . ' TB';
    }
}
