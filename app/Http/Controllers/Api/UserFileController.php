<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserFileRequest;
use App\Models\PhysicalFile;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UserFileController extends Controller
{
    public function index(User $user): JsonResponse
    {
        $files = $user->storedFiles()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get(['id', 'file_name', 'file_size', 'created_at'])
            ->map(fn (StoredFile $file): array => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_size_bytes' => $file->file_size,
                'upload_time' => $file->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $files,
        ]);
    }

    public function store(StoreUserFileRequest $request, User $user): JsonResponse
    {
        $payload = $request->validated();

        return DB::transaction(function () use ($payload, $user): JsonResponse {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $duplicateFile = StoredFile::query()
                ->where('user_id', $lockedUser->id)
                ->where('file_name', $payload['file_name'])
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicateFile) {
                return response()->json([
                    'message' => 'An active file with this name already exists for the user.',
                ], Response::HTTP_CONFLICT);
            }

            $newUsage = $lockedUser->used_storage_bytes + $payload['file_size'];

            if ($newUsage > User::STORAGE_LIMIT_BYTES) {
                return response()->json([
                    'message' => 'Storage limit exceeded for this user.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $physicalFile = PhysicalFile::query()
                ->where('file_hash', $payload['file_hash'])
                ->lockForUpdate()
                ->first();

            if ($physicalFile !== null && $physicalFile->file_size_bytes !== $payload['file_size']) {
                return response()->json([
                    'message' => 'The provided file hash already exists with a different file size.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($physicalFile === null) {
                $physicalFile = PhysicalFile::query()->create([
                    'file_hash' => $payload['file_hash'],
                    'file_size' => $payload['file_size'],
                    'reference_count' => 0,
                ]);
            }

            $file = StoredFile::query()->create([
                'user_id' => $lockedUser->id,
                'physical_file_id' => $physicalFile->id,
                'file_name' => $payload['file_name'],
                'file_size' => $payload['file_size'],
            ]);

            $lockedUser->forceFill([
                'used_storage_bytes' => $newUsage,
            ])->save();

            $physicalFile->increment('reference_count');

            return response()->json([
                'data' => $this->formatFile($file->fresh(['physicalFile'])),
            ], Response::HTTP_CREATED);
        });
    }

    public function destroy(User $user, int $file): JsonResponse
    {
        return DB::transaction(function () use ($user, $file): JsonResponse {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $storedFile = StoredFile::query()
                ->where('id', $file)
                ->where('user_id', $lockedUser->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($storedFile === null) {
                return response()->json([
                    'message' => 'File not found for this user.',
                ], Response::HTTP_NOT_FOUND);
            }

            $storedFile->forceFill([
                'deleted_at' => Carbon::now(),
            ])->save();

            $lockedUser->decrement('used_storage_bytes', $storedFile->file_size);

            $physicalFile = PhysicalFile::query()
                ->whereKey($storedFile->physical_file_id)
                ->lockForUpdate()
                ->first();

            if ($physicalFile !== null) {
                $physicalFile->forceFill([
                    'reference_count' => max(0, $physicalFile->reference_count - 1),
                ])->save();
            }

            return response()->json([
                'message' => 'File deleted successfully.',
            ]);
        });
    }

    private function formatFile(StoredFile $file): array
    {
        return [
            'id' => $file->id,
            'user_id' => $file->user_id,
            'file_name' => $file->file_name,
            'file_size' => $file->file_size,
            'file_hash' => $file->physicalFile?->file_hash,
            'upload_time' => $file->created_at?->toIso8601String(),
        ];
    }
}
