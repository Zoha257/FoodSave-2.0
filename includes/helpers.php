<?php
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return "Just now";
    }
    
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute'
    ];
    
    foreach ($intervals as $seconds => $label) {
        $quotient = floor($difference / $seconds);
        if ($quotient > 0) {
            if ($quotient == 1) {
                return "1 {$label} ago";
            }
            return "{$quotient} {$label}s ago";
        }
    }
}