<?php

return [

    'project_id' => env('GOOGLE_CLOUD_PROJECT'),

    'location' => env('GOOGLE_CLOUD_LOCATION', 'asia-southeast1'),

    'model' => env('VERTEX_AI_GEMINI_MODEL', 'gemini-2.5-flash'),

    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),

];
