<?php

namespace App\Support;

use Illuminate\Support\Str;

class OpenLigaDbTranslationService
{
    public function stageCode(?string $value): string
    {
        $normalized = $this->normalize($value ?? '');

        if ($normalized === '') {
            return 'unknown_stage';
        }

        return match (true) {
            str_contains($normalized, 'group'),
            str_contains($normalized, 'gruppe'),
            str_contains($normalized, 'gruppen'),
            str_contains($normalized, 'vorrunde') => 'group_stage',
            str_contains($normalized, '32') => 'round_of_32',
            str_contains($normalized, 'sechzehntel'),
            str_contains($normalized, '16 avos') => 'round_of_32',
            str_contains($normalized, 'achtel'),
            str_contains($normalized, 'round of 16') => 'round_of_16',
            str_contains($normalized, 'viertel'),
            str_contains($normalized, 'quarter') => 'quarterfinal',
            str_contains($normalized, 'halbfinal'),
            str_contains($normalized, 'semi') => 'semifinal',
            str_contains($normalized, 'platz 3'),
            str_contains($normalized, 'third') => 'third_place',
            str_contains($normalized, 'final') => 'final',
            default => 'unknown_stage',
        };
    }

    public function translateStage(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $this->stageLabel('unknown_stage');
        }

        $fixed = config('openligadb_translations.stages', []);

        if (isset($fixed[$value])) {
            return $fixed[$value];
        }

        if (preg_match('/^Gruppenphase\s+(\d+)$/i', $value, $matches)) {
            return 'Fase de grupos - rodada '.$matches[1];
        }

        if (preg_match('/^(\d+)\.\s*Spieltag$/i', $value, $matches)) {
            return 'Rodada '.$matches[1];
        }

        if (preg_match('/^Group\s+([A-Z])$/i', $value, $matches)) {
            return 'Grupo '.Str::upper($matches[1]);
        }

        return $this->stageLabel($this->stageCode($value));
    }

    public function stageLabel(string $code): string
    {
        return config("openligadb_translations.stage_labels.{$code}", 'Fase nao identificada');
    }

    public function translateStatus(string $status): string
    {
        return config("openligadb_translations.statuses.{$status}", 'Status desconhecido');
    }

    public function translateScoreReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        return config("openligadb_translations.score_reasons.{$reason}", $reason);
    }

    public function translateTeam(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'Selecao indefinida';
        }

        return config("openligadb_translations.teams.{$name}", $name);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }
}
