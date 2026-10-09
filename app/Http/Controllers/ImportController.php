<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Services\ImportService;

class ImportController extends Controller
{
    public function create()
    {
        return view('imports.create');
    }

    public function store(
        StoreImportRequest $request,
        ImportService $importService
    ) {
        $import = $importService->create(
            $request->file('file')
        );

        return response()->json([
            'message' => 'Import started',
            'import_id' => $import->id,
            'status' => $import->status,
        ]);
    }
}