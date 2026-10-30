<?php

declare(strict_types=1);

$milliseconds = filter_input(INPUT_GET, 'ms', FILTER_VALIDATE_INT);
usleep(1000 * (is_int($milliseconds) ? $milliseconds : 800));
header('Content-Type: text/plain');
header('Cache-Control: no-store');
echo 'done';
