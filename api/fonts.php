<?php
require_once __DIR__ . '/../lib/bootstrap.php';
json_out(['ok' => true, 'fonts' => google_font_list()]);
