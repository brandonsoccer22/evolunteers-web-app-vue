<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpportunitySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filters' => ['array'],
            'filters.*.field' => ['required', 'string', 'in:name,description,organization,tag,tags,start_date'],
            'filters.*.value' => ['nullable'],
            'filters.*.operator' => ['nullable', 'string', 'in:eq,gt,gte,lt,lte,neq'],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:50'],
        ];
    }
}
