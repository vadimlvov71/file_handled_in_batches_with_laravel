<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\Lead;
use App\Services\PhoneNormalizer;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    private const BATCH_SIZE = 1000;

    public function __construct(
        public Import $import
    ) {}

    public function handle(PhoneNormalizer $phoneNormalizer): void
    {
        $startTime = hrtime(true);
        $startedAt = now();

        $disk = Storage::disk('local');

        $reader = null;
        $errorWriter = null;
        $errorFilePath = null;

        $batch = [];

        $total = 0;
        $processed = 0;
        $valid = 0;
        $invalid = 0;

        $readDurationMs = 0;
        $validationDurationMs = 0;
        $dbDurationMs = 0;
        $errorFileDurationMs = 0;

        $errorFileStartedAt = null;

        $this->import->update([
            'status' => 'processing',
            'started_at' => $startedAt,
            'finished_at' => null,
            'duration_ms' => null,
            'total' => 0,
            'processed' => 0,
            'valid' => 0,
            'invalid' => 0,
            'error_file_path' => null,
            'error_message' => null,
        ]);

        try {
            $sourcePath = $disk->path($this->import->file_path);

            if (! is_file($sourcePath)) {
                throw new \RuntimeException(
                    "Исходный файл не найден: {$this->import->file_path}"
                );
            }

            $errorFilePath = sprintf(
                'imports/errors/import_%d_errors.xlsx',
                $this->import->id
            );

            $disk->makeDirectory('imports/errors');

            $errorWriter = new Writer();
            $errorWriter->openToFile(
                $disk->path($errorFilePath)
            );

            // В отчёте первая строка — заголовок.
            $errorWriter->addRow(Row::fromValues([
                'source_row',
                'error',
                'external_id',
                'registered_at',
                'first_name',
                'last_name',
                'phone_original',
                'email',
                'city',
                'source',
                'campaign',
                'service',
                'budget',
                'status',
                'manager',
                'comment',
                'last_contact_at',
            ]));

            $reader = new Reader();
            $reader->open($sourcePath);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $readStartedAt = hrtime(true);

                    $values = $row->toArray();

                    // Пропускаем полностью пустые строки.
                    if ($this->isEmptyRow($values)) {
                        continue;
                    }

                    $total++;

                    $readDurationMs += (int) (
                        (hrtime(true) - $readStartedAt) / 1_000_000
                    );

                    $validationStartedAt = hrtime(true);

                    /*
                     * Заголовков в исходном файле нет.
                     *
                     * Индексы:
                     * 0  external_id
                     * 1  registered_at
                     * 2  first_name
                     * 3  last_name
                     * 4  phone
                     * 5  email
                     * 6  city
                     * 7  source
                     * 8  campaign
                     * 9  service
                     * 10 budget
                     * 11 status
                     * 12 manager
                     * 13 comment
                     * 14 last_contact_at
                     */

                    $originalPhone = $values[4] ?? null;

                    $phone = $phoneNormalizer->normalize(
                        $originalPhone
                    );

                    $reason = null;

                    if ($phone === null) {
                        $reason = 'Некорректный телефон';
                    }

                    if (count($values) < 15) {
                        $reason = 'В строке меньше 15 столбцов';
                    }

                    $validationDurationMs += (int) (
                        (hrtime(true) - $validationStartedAt) / 1_000_000
                    );

                    $sourceRowNumber = $total;

                    if ($reason !== null) {
                        $errorFileStartedAt ??= hrtime(true);

                        $errorWriter->addRow(
                            Row::fromValues([
                                $sourceRowNumber,
                                $reason,
                                ...$this->formatOriginalRow($values),
                            ])
                        );

                        $invalid++;
                        $processed++;

                        continue;
                    }

                    $batch[] = [
                        'import_id' => $this->import->id,
                        'external_id' => (string) ($values[0] ?? ''),
                        'registered_at' => $this->formatDate(
                            $values[1] ?? null
                        ),
                        'first_name' => $this->stringOrNull(
                            $values[2] ?? null
                        ),
                        'last_name' => $this->stringOrNull(
                            $values[3] ?? null
                        ),
                        'phone' => $phone,
                        'email' => $this->stringOrNull(
                            $values[5] ?? null
                        ),
                        'city' => $this->stringOrNull(
                            $values[6] ?? null
                        ),
                        'source' => $this->stringOrNull(
                            $values[7] ?? null
                        ),
                        'campaign' => $this->stringOrNull(
                            $values[8] ?? null
                        ),
                        'service' => $this->stringOrNull(
                            $values[9] ?? null
                        ),
                        'budget' => $this->normalizeBudget(
                            $values[10] ?? null
                        ),
                        'status' => $this->stringOrNull(
                            $values[11] ?? null
                        ),
                        'manager' => $this->stringOrNull(
                            $values[12] ?? null
                        ),
                        'comment' => $this->stringOrNull(
                            $values[13] ?? null
                        ),
                        'last_contact_at' => $this->formatDate(
                            $values[14] ?? null
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= self::BATCH_SIZE) {
                        $dbStartedAt = hrtime(true);

                        Lead::query()->insert($batch);

                        $dbDurationMs += (int) (
                            (hrtime(true) - $dbStartedAt) / 1_000_000
                        );

                        $inserted = count($batch);

                        $valid += $inserted;
                        $processed += $inserted;

                        $batch = [];

                        $this->updateProgress(
                            $total,
                            $processed,
                            $valid,
                            $invalid
                        );
                    }
                }

                // В этом проекте обрабатываем первый лист XLSX.
                break;
            }

            // Сохраняем остаток последнего пакета.
            if ($batch !== []) {
                $dbStartedAt = hrtime(true);

                Lead::query()->insert($batch);

                $dbDurationMs += (int) (
                    (hrtime(true) - $dbStartedAt) / 1_000_000
                );

                $inserted = count($batch);

                $valid += $inserted;
                $processed += $inserted;

                $batch = [];
            }

            $reader->close();
            $reader = null;

            $errorWriter->close();
            $errorWriter = null;

            if ($errorFileStartedAt !== null) {
                $errorFileDurationMs = (int) (
                    (hrtime(true) - $errorFileStartedAt) / 1_000_000
                );
            } else {
                // Все строки валидны — отдельный отчёт не нужен.
                $disk->delete($errorFilePath);
                $errorFilePath = null;
            }

            $durationMs = (int) (
                (hrtime(true) - $startTime) / 1_000_000
            );

            $this->import->update([
                'status' => 'completed',
                'total' => $total,
                'processed' => $processed,
                'valid' => $valid,
                'invalid' => $invalid,
                'error_file_path' => $errorFilePath,
                'error_message' => null,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'read_duration_ms' => $readDurationMs,
                'validation_duration_ms' => $validationDurationMs,
                'db_duration_ms' => $dbDurationMs,
                'error_file_duration_ms' => $errorFileDurationMs,
            ]);

            Log::info('Import completed', [
                'import_id' => $this->import->id,
                'total' => $total,
                'processed' => $processed,
                'valid' => $valid,
                'invalid' => $invalid,
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable $exception) {
            $durationMs = (int) (
                (hrtime(true) - $startTime) / 1_000_000
            );

            report($exception);

            $this->import->update([
                'status' => 'failed',
                'total' => $total,
                'processed' => $processed,
                'valid' => $valid,
                'invalid' => $invalid,
                'error_file_path' => $errorFilePath,
                'error_message' => mb_substr(
                    $exception->getMessage(),
                    0,
                    60000
                ),
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'read_duration_ms' => $readDurationMs,
                'validation_duration_ms' => $validationDurationMs,
                'db_duration_ms' => $dbDurationMs,
                'error_file_duration_ms' => $errorFileDurationMs,
            ]);

            throw $exception;
        } finally {
            if ($reader !== null) {
                try {
                    $reader->close();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            if ($errorWriter !== null) {
                try {
                    $errorWriter->close();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeBudget(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function formatOriginalRow(array $values): array
    {
        $result = [];

        for ($i = 0; $i < 15; $i++) {
            $value = $values[$i] ?? null;

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $result[] = $value === null ? '' : (string) $value;
        }

        return $result;
    }

    private function updateProgress(
        int $total,
        int $processed,
        int $valid,
        int $invalid
    ): void {
        $this->import->update([
            'total' => $total,
            'processed' => $processed,
            'valid' => $valid,
            'invalid' => $invalid,
        ]);
    }
}