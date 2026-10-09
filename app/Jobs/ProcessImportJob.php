<?php

namespace App\Jobs;

use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public Import $import
    ) {}

    public function handle(): void
    {
        $startedAt = now();
        $startTime = hrtime(true);
        // hrtime() предназначен для измерения интервалов времени 
        //и не зависит от изменения системных часов во время выполнения.
        
        $this->import->update([
            'status' => 'processing',
            'started_at' => $startedAt,
            'finished_at' => null,
            'duration_ms' => null,
        ]);

        try {
            /*
             * Здесь позже разместим:
             *
             * 1. Чтение XLSX пакетами.
             * 2. Проверку телефонов.
             * 3. Пакетную запись валидных строк.
             * 4. Формирование XLSX с ошибочными строками.
             */

            // Временно имитируем обработку.
            // После реализации импорта этот код удалим.
            usleep(500000);
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            /*$durationMs = (int) (
                (hrtime(true) - $startedAt) / 1_000_000
            );*/

            $this->import->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_ms' => $durationMs,
            ]);

            Log::info('Import completed', [
                'import_id' => $this->import->id,
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable $exception) {
            $durationMs = (int) (
                (hrtime(true) - $startedAt) / 1_000_000
            );

            $this->import->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}