<?php

namespace App\Policies;

use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Access\AccessManager;

class KnowledgeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return app(AccessManager::class)->allows($user, AccessManager::KnowledgeRead);
    }

    public function view(User $user, KnowledgeDocument $document): bool
    {
        $access = app(AccessManager::class);

        return $access->allows($user, AccessManager::KnowledgeRead)
            && $access->canAccessDocument($user, $document, AccessManager::KnowledgeRead);
    }

    public function create(User $user): bool
    {
        return app(AccessManager::class)->allows($user, AccessManager::KnowledgeCreate);
    }

    public function update(User $user, KnowledgeDocument $document): bool
    {
        $access = app(AccessManager::class);

        return $access->allows($user, AccessManager::KnowledgeUpdate)
            && $access->canAccessDocument($user, $document, AccessManager::KnowledgeUpdate);
    }

    public function delete(User $user, KnowledgeDocument $document): bool
    {
        $access = app(AccessManager::class);

        return $access->allows($user, AccessManager::KnowledgeDelete)
            && $access->canAccessDocument($user, $document, AccessManager::KnowledgeDelete);
    }
}
