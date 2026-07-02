<?php

namespace App\Console\Commands;

use App\Application\Actions\Prediction\ScoreFinishedPredictionsAction;
use App\Application\Actions\WorldCup\SyncWorldCupDataAction;
use Illuminate\Console\Command;
use Throwable;

class ReconcileWorldCupData extends Command
{
    protected $signature = 'world-cup:reconcile {--shortcut=} {--season=}';

    protected $description = 'Reconciliação diária essencial dos dados da Copa e apuração de palpites.';

    public function handle(SyncWorldCupDataAction $sync, ScoreFinishedPredictionsAction $score): int
    {
        try {
            $result = $sync->execute(
                shortcut: $this->option('shortcut') ?: null,
                season: $this->option('season') ? (int) $this->option('season') : null,
                force: true,
                priority: 'essential',
                matchesOnly: true,
            );

            $scored = $score->execute();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Reconciliação concluída com status '.$result['status'].'.');
        $this->line('Partidas: '.$result['matches']);
        $this->line('Palpites apurados: '.$scored);

        return self::SUCCESS;
    }
}
