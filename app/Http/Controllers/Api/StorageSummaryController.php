<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StorageSummaryController extends Controller
{
    public function __invoke(User $user): JsonResponse
    {
        $activeFiles = $user->storedFiles()
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'storage_limit_bytes' => User::STORAGE_LIMIT_BYTES,
                'total_storage_used_bytes' => $user->used_storage_bytes,
                'remaining_storage_bytes' => User::STORAGE_LIMIT_BYTES - $user->used_storage_bytes,
                'total_active_files' => $activeFiles,
            ],
        ]);
    }
}
