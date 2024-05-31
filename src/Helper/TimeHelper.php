<?php

namespace Parallel\Helper;

class TimeHelper
{
    /**
     * @param float|int $seconds
     * @return string
     */
    public static function formatTime($seconds): string
    {
        $hours = 0;
        $minutes = 0;
        $seconds = floor($seconds);

        if ($seconds > 3600) {
            $hours = floor($seconds / 3600);
            $seconds -= $hours * 3600;
        }

        if ($seconds > 60) {
            $minutes = floor($seconds / 60);
            $seconds -= $minutes * 60;
        }

        $str = '';
        if ($hours > 0) {
            $str .= str_pad($hours, 2, 0, STR_PAD_LEFT) . ':';
        }

        return $str . str_pad($minutes, 2, 0, STR_PAD_LEFT) . ':' .
            str_pad($seconds, 2, 0, STR_PAD_LEFT);
    }
}
