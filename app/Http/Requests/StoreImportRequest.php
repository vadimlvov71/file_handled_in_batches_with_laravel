<?php
// app/Http/Requests/StoreImportRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreImportRequest extends FormRequest
{
   
    public function rules(): array
{
    return [
        'file' => [
            'required',
            'file',
            'mimes:xlsx',
            'max:20480',
        ],
    ];
}
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors()
            ], 422)
        );
    }
}