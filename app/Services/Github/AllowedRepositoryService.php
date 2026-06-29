<?php

namespace App\Services\Github;

class AllowedRepositoryService
{
    /**
     * Determine whether AI review is permitted for the given repository.
     */
    public function isAllowed(string $repositoryFullName): bool
    {
        $allowedRepositories = config('services.github.allowed_repositories', []);

        if ($allowedRepositories === []) {
            return false;
        }

        return in_array($repositoryFullName, $allowedRepositories, true);
    }
}
