<?php

if (!function_exists('sar_to_points')) {
    function sar_to_points($sar)
    {
        return round($sar / 500, 2);
    }
}

if (!function_exists('points_to_sar')) {
    function points_to_sar($points)
    {
        return round($points * 500, 2);
    }
}
