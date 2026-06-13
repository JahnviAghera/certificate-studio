<?php
require_once __DIR__ . '/../lib/bootstrap.php';
// Pre-fill payload for the SMTP form. The password is intentionally NOT sent
// to the browser; if one is configured server-side the user can leave the
// password field blank and send.php will use it.
json_out(['ok' => true, 'smtp' => app_config()->publicDefaults()]);
