<?php
require __DIR__ . '/../backend/api/logging.php';
log_info('unit', 'smoke test legacy', ['x' => 1]);

require __DIR__ . '/../backend/lib/api_helpers.php';
log_info('a message', ['component' => 'unit', 'x' => 2]);

echo "done\n";
