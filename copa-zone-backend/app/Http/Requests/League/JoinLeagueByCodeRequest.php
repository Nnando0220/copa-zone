<?php

namespace App\Http\Requests\League;

use Illuminate\Foundation\Http\FormRequest;

class JoinLeagueByCodeRequest extends FormRequest
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
            'invite_code' => ['required', 'string', 'size:8'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('invite_code')) {
            $this->merge([
                'invite_code' => strtoupper(trim((string) $this->input('invite_code'))),
            ]);
        }
    }
}
