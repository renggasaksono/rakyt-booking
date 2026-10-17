<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit events are captured but not shown to sellers in P0 (F-009 Q7) and never edited.
 */
class AuditEventPolicy
{
    public function viewAny(User $user): Response
    {
        return Response::denyAsNotFound();
    }

    public function view(User $user, Model $model): Response
    {
        return Response::denyAsNotFound();
    }

    public function update(User $user, Model $model): Response
    {
        return Response::denyAsNotFound();
    }

    public function delete(User $user, Model $model): Response
    {
        return Response::denyAsNotFound();
    }
}
