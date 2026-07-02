<?php

namespace Tests\Feature;

use App\Support\OpenLigaDbTranslationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenLigaDbTranslationTest extends TestCase
{
    #[DataProvider('stageProvider')]
    public function test_stage_translation_and_internal_code(string $externalValue, string $expectedCode, string $expectedLabel): void
    {
        $translator = app(OpenLigaDbTranslationService::class);

        $this->assertSame($expectedCode, $translator->stageCode($externalValue));
        $this->assertSame($expectedLabel, $translator->translateStage($externalValue));
    }

    #[DataProvider('teamProvider')]
    public function test_team_translation_to_pt_br(string $externalValue, string $expectedLabel): void
    {
        $translator = app(OpenLigaDbTranslationService::class);

        $this->assertSame($expectedLabel, $translator->translateTeam($externalValue));
    }

    #[DataProvider('statusProvider')]
    public function test_status_translation_to_pt_br(string $status, string $expectedLabel): void
    {
        $translator = app(OpenLigaDbTranslationService::class);

        $this->assertSame($expectedLabel, $translator->translateStatus($status));
    }

    public function test_unknown_stage_uses_safe_fallback(): void
    {
        $translator = app(OpenLigaDbTranslationService::class);

        $this->assertSame('unknown_stage', $translator->stageCode('Zwischenrunde Misteriosa'));
        $this->assertSame('Fase nao identificada', $translator->translateStage('Zwischenrunde Misteriosa'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function stageProvider(): array
    {
        return [
            'alemao grupo' => ['Gruppenphase', 'group_stage', 'Fase de grupos'],
            'alemao 16 avos' => ['Sechzehntelfinale', 'round_of_32', '16 avos de final'],
            'ingles round of 32' => ['Round of 32', 'round_of_32', '16 avos de final'],
            'alemao oitavas' => ['Achtelfinale', 'round_of_16', 'Oitavas de final'],
            'alemao quartas' => ['Viertelfinale', 'quarterfinal', 'Quartas de final'],
            'alemao semifinal' => ['Halbfinale', 'semifinal', 'Semifinal'],
            'final' => ['Finale', 'final', 'Final'],
            'rodada grupo numerica' => ['Gruppenphase 2', 'group_stage', 'Fase de grupos - rodada 2'],
            'spieltag' => ['3. Spieltag', 'unknown_stage', 'Rodada 3'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function teamProvider(): array
    {
        return [
            'brasil' => ['Brazil', 'Brasil'],
            'alemanha' => ['Germany', 'Alemanha'],
            'alemanha alemao' => ['Deutschland', 'Alemanha'],
            'brasil alemao' => ['Brasilien', 'Brasil'],
            'argentina alemao' => ['Argentinien', 'Argentina'],
            'mexico alemao' => ['Mexiko', 'Mexico'],
            'africa do sul alemao' => ['Südafrika', 'Africa do Sul'],
            'coreia do sul alemao' => ['Südkorea', 'Coreia do Sul'],
            'costa do marfim' => ['Ivory Coast', 'Costa do Marfim'],
            'costa do marfim alemao' => ['Elfenbeinküste', 'Costa do Marfim'],
            'coreia do sul' => ['South Korea', 'Coreia do Sul'],
            'paises baixos' => ['Netherlands', 'Paises Baixos'],
            'paises baixos alemao' => ['Niederlande', 'Paises Baixos'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statusProvider(): array
    {
        return [
            'aguardando selecoes' => ['awaiting_teams', 'Aguardando selecoes'],
            'palpite aberto' => ['open_for_prediction', 'Palpite aberto'],
            'bloqueada' => ['locked', 'Bloqueada'],
            'em andamento' => ['in_progress_unconfirmed', 'Em andamento'],
            'finalizada' => ['finished', 'Finalizada'],
            'dados atrasados' => ['data_delayed', 'Dados atrasados'],
        ];
    }
}
