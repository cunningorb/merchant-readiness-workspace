<?php

namespace App\Http\Requests;

use App\Services\Imports\ImportCoordinator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_type' => ['required', 'string', Rule::in([
                ImportCoordinator::DATA_TYPE_CATALOG,
                ImportCoordinator::DATA_TYPE_ORDERS_RETURNS,
                ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS,
            ])],
            // This is a framework-proving upload path, not a bulk-data
            // pipeline, hence the conservative 5MB cap.
            'file' => ['required', 'file', 'extensions:csv', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
