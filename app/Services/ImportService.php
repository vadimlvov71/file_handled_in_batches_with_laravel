<?php

namespace App\Services;

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportService
{
    public function create(UploadedFile $file): Import
    {
        $disk = Storage::disk('local');

        try {
            $path = $file->store('imports/source', 'local');

            if ($path === false || ! $disk->exists($path)) {
                throw new RuntimeException(
                    'Не удалось сохранить загруженный файл.'
                );
            }

            $import = DB::transaction(function () use ($path) {
                return Import::create([
                    'file_path' => $path,
                    'status' => 'pending',
                ]);
            });

            ProcessImportJob::dispatch($import);

            return $import;
        } catch (Throwable $exception) {
            if (isset($path) && is_string($path)) {
                try {
                    $disk->delete($path);
                } catch (Throwable $deleteException) {
                    report($deleteException);
                }
            }

            report($exception);

            throw $exception;
        }
    }
}