<?php

return [
    'first_after_hours' => (int) env('STATUS_REMINDER_FIRST_HOURS', 3),
    'repeat_every_hours' => (int) env('STATUS_REMINDER_REPEAT_HOURS', 3),
    'max_attempts' => (int) env('STATUS_REMINDER_MAX_ATTEMPTS', 3),
];
