<?php

namespace App\Http\Requests\League;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'max_members' => ['required', 'integer', 'min:2', 'max:128'],
        ];
    }
}
