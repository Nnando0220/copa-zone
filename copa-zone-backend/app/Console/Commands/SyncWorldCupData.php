<?php

namespace App\Console\Commands;

use App\Application\Actions\WorldCup\SyncWorldCupDataAction;
use Illuminate\Console\Command;
use Throwable;

class SyncWorldCupData extends Command
{
    protected $signature = 'world-cup:sync {--shortcut=} {--season=} {--force} {--essential} {--matches-only}';

    protected $aliases = ['football:openligadb:sync-matches'];

    protected $description = 'Sincroniza selecoes e partidas da Copa pela OpenLigaDB.';

    public function handle(SyncWorldCupDataAction $action): int
    {
        try {
            $result = $action->execute(
                shortcut: $this->option('shortcut') ?: null,
                season: $this->option('season') ? (int) $this->option('season') : null,
                force: (bool) $this->option('force'),
                priority: $this->option('essential') ? 'essential' : 'normal',
                matchesOnly: (bool) $this->option('matches-only'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Status da sincronizacao da Copa: '.$result['status'].'.');
        $this->line('Edicao: '.($result['edition'] ? $result['edition']->name.' '.$result['edition']->season : 'nenhuma edicao sincronizada'));
        $this->line('Selecoes: '.$result['teams']);
        $this->line('Grupos/fases: '.$result['groups']);
        $this->line('Partidas: '.$result['matches']);

        return self::SUCCESS;
    }
}
