<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name_pt_br ?: $this->name,
            'code' => $this->code,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'logo_url' => $this->logo_url,
            'provider_team_id' => $this->provider_team_id,
        ];
    }
}
