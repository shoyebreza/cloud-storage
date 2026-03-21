<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\PhysicalFile;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->formatUser($user));

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'] ?? Str::password(12),
            'used_storage_bytes' => 0,
        ]);

        return response()->json([
            'data' => $this->formatUser($user),
        ], Response::HTTP_CREATED);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->formatUser($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('password', $payload) && $payload['password'] === null) {
            unset($payload['password']);
        }

        $user->fill($payload);
        $user->save();

        return response()->json([
            'data' => $this->formatUser($user->fresh()),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeFiles = StoredFile::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get(['physical_file_id']);

            $referenceCounts = $activeFiles
                ->countBy('physical_file_id');

            foreach ($referenceCounts as $physicalFileId => $count) {
                $physicalFile = PhysicalFile::query()
                    ->whereKey($physicalFileId)
                    ->lockForUpdate()
                    ->first();

                if ($physicalFile !== null) {
                    $physicalFile->forceFill([
                        'reference_count' => max(0, $physicalFile->reference_count - $count),
                    ])->save();
                }
            }

            $lockedUser->delete();
        });

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'used_storage_bytes' => $user->used_storage_bytes,
            'storage_limit_bytes' => User::STORAGE_LIMIT_BYTES,
            'remaining_storage_bytes' => User::STORAGE_LIMIT_BYTES - $user->used_storage_bytes,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
