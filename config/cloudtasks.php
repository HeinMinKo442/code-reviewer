<?php

return [

    'project_id' => env('GOOGLE_CLOUD_PROJECT'),

    'location' => env('GOOGLE_CLOUD_LOCATION', 'asia-southeast1'),

    'queue' => env('CLOUD_TASKS_QUEUE', 'codereviewer-jobs'),

    'handler_url' => env('CLOUD_TASKS_HANDLER_URL'),

    'handler_secret' => env('CLOUD_TASKS_HANDLER_SECRET'),

    'oidc_service_account' => env('CLOUD_TASKS_OIDC_SERVICE_ACCOUNT'),

];
