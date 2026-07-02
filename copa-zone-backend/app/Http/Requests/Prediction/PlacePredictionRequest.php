<?php

namespace App\Http\Requests\Prediction;

use Illuminate\Foundation\Http\FormRequest;

class PlacePredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'predicted_home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'predicted_away_score' => ['required', 'integer', 'min:0', 'max:99'],
            'predicted_winner_side' => ['nullable', 'string', 'in:home,away'],
        ];
    }
}
