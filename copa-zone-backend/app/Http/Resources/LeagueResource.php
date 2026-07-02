<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $request->user()?->id)
            : null;
        $membersCount = $this->active_members_count
            ?? $this->members_count
            ?? ($this->relationLoaded('members') ? $this->members->count() : null);
        $isOwner = $request->user()?->id === $this->owner_user_id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'visibility' => $this->visibility,
            'join_policy' => $this->join_policy,
            'owner_name' => $this->whenLoaded('owner', fn () => $this->owner?->name),
            'invite_code' => $isOwner ? $this->invite_code : null,
            'status' => $this->status,
            'max_members' => $this->max_members,
            'members_count' => $membersCount,
            'available_slots' => $membersCount === null ? null : max(0, $this->max_members - $membersCount),
            'is_owner' => $isOwner,
            'membership' => $membership ? [
                'role' => $membership->role,
                'status' => $membership->status,
                'joined_at' => $membership->joined_at,
            ] : null,
            'settings' => $this->whenLoaded('settings', fn () => [
                'points_correct_outcome' => $this->settings?->points_correct_outcome,
                'points_wrong_outcome' => $this->settings?->points_wrong_outcome,
                'points_exact_score' => $this->settings?->points_exact_score,
                'points_correct_goal_difference' => $this->settings?->points_correct_goal_difference,
                'points_correct_outcome_scoreline' => $this->settings?->points_correct_outcome_scoreline,
                'prediction_lock_minutes_before_start' => $this->settings?->prediction_lock_minutes_before_start,
                'allow_prediction_cancellation' => $this->settings?->allow_prediction_cancellation,
                'late_join_enabled' => $this->settings?->late_join_enabled,
                'ranking_visibility' => $this->settings?->ranking_visibility,
            ]),
            'summary' => $this->summary,
            'created_at' => $this->created_at,
        ];
    }
}
