<?php

return [
    /*
     * Folder where the VPS defense agent (ops/defense-agent) publishes latest.json and history.json for the
     * Server security page. On the VPS the agent runs with --publish /home/scharge.sharet.africa/security.
     */
    'report_dir' => env('SERVER_SECURITY_DIR', '/home/scharge.sharet.africa/security'),
];
