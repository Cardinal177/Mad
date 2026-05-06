<?php

declare(strict_types=1);

$livePath = __DIR__ . '/live.php';
if (!is_file($livePath)) {
	$livePath = __DIR__ . '/public/live.php';
}

require $livePath;
