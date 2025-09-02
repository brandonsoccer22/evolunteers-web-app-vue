<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbstractFormRequest extends FormRequest
{
    public function all($keys = null)
    {
        $input = parent::all($keys);
        //Laravel should have handled this, but I was having trouble with patch requests in Bruno
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = urldecode($value);
            }
        }
        return $input;
    }
}
