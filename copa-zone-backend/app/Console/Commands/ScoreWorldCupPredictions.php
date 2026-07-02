<?php

namespace App\Console\Commands;

use App\Application\Actions\Prediction\ScoreFinishedPredictionsAction;
use Illuminate\Console\Command;

class ScoreWorldCupPredictions extends Command
{
    protected $signature = 'world-cup:score-predictions {--rescore : Reapurar tambem palpites ja apurados}';

    protected $description = 'Apura palpites de partidas finalizadas e recalcula rankings das ligas afetadas.';

    public function handle(ScoreFinishedPredictionsAction $action): int
    {
        $scored = $action->execute((bool) $this->option('rescore'));

        $this->info("Palpites apurados: {$scored}");

        return self::SUCCESS;
    }
}
