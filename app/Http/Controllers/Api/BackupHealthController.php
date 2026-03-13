<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BackupHealthService;
use Illuminate\Http\Request;
use Throwable;

class BackupHealthController extends Controller
{
    public function __construct(
        private readonly BackupHealthService $service
    ) {
    }

    public function health()
    {
        try {
            return response()->json($this->service->collect());
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Nao foi possivel consultar os backups.',
                'error' => $e->getMessage(),
                'meta' => [
                    'source' => 'laravel-backup-health',
                    'partial' => true,
                ],
            ], 500);
        }
    }

    public function forceBackup(Request $request)
    {
        $validated = $request->validate([
            'name_database' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:daily,weekly,monthly'],
            'pending' => ['nullable', 'array'],
            'pending.*' => ['string'],
        ]);

        try {
            $result = $this->service->forceBackup(
                $validated['name_database'],
                $validated['type'] ?? 'daily',
                $validated['pending'] ?? []
            );

            return response()->json($result);
        } catch (Throwable $e) {
            $status = str_contains(strtolower($e->getMessage()), 'desabilitado') ? 501 : 422;

            return response()->json([
                'ok' => false,
                'message' => 'Nao foi possivel executar o backup.',
                'error' => $e->getMessage(),
            ], $status);
        }
    }
}
