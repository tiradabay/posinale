<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
// Unused legacy route: cannot mutate stock or recipes outside supported workflows.
http_response_code(410);
echo json_encode(['success' => false, 'error' => 'This obsolete inventory endpoint is disabled. Use the inventory and recipe management screens.']);