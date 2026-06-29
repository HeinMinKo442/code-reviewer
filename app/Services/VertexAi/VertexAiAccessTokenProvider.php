<?php

namespace App\Services\VertexAi;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use RuntimeException;

class VertexAiAccessTokenProvider
{
    private const CLOUD_PLATFORM_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /**
     * Resolve a short-lived OAuth access token for Vertex AI requests.
     */
    public function getAccessToken(): string
    {
        $credentials = $this->resolveCredentials();
        $token = $credentials->fetchAuthToken();

        if (! is_array($token) || ! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new RuntimeException('Unable to obtain Google Cloud access token.');
        }

        return $token['access_token'];
    }

    /**
     * Resolve Google Cloud credentials from a service account file or Cloud Run metadata.
     */
    private function resolveCredentials(): object
    {
        $credentialsPath = config('vertex.credentials_path');

        if (is_string($credentialsPath) && $credentialsPath !== '' && is_readable($credentialsPath)) {
            $serviceAccount = json_decode((string) file_get_contents($credentialsPath), true);

            if (! is_array($serviceAccount)) {
                throw new RuntimeException('Google service account credentials file is invalid.');
            }

            return new ServiceAccountCredentials(self::CLOUD_PLATFORM_SCOPE, $serviceAccount);
        }

        return ApplicationDefaultCredentials::getCredentials(self::CLOUD_PLATFORM_SCOPE);
    }
}
