<?php

return [

    'project_id' => env('GOOGLE_CLOUD_PROJECT'),

    'location' => env('GOOGLE_CLOUD_LOCATION', 'us-central1'),

    'model' => env('VERTEX_AI_GEMINI_MODEL', 'gemini-1.5-flash'),

    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),

];
