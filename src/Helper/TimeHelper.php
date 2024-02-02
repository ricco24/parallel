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

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
        }

        if ($minutes > 0) {
            return $minutes . 'm ' . $seconds . 's';
        }

        return $seconds . 's';
    }
}
