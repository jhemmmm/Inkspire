<?php

namespace App\Http\Requests\Artist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartDesignRequest extends FormRequest
{
    /**
     * Body-less — the only transition this request supports
     * (in_consultation -> in_design) is implied entirely by which route
     * was hit, not by any client-supplied value.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
