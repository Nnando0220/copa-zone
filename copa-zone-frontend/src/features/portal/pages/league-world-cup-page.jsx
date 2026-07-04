import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router';
import { CalendarDays, ChevronLeft, ClipboardList, Radio, ShieldCheck, Trophy, Users } from 'lucide-react';
import { toast } from 'sonner';
import { getEcho, leaveChannel } from '../../../realtime/echo';
import { teamLabel, WorldCupBracketView, WorldCupGroupPanel, WorldCupStatsPanel } from '../components/world-cup-structure';
import { portalService } from '../services/portal-service';

const tabs = [
  { id: 'groups', label: 'Fase de grupos' },
  { id: 'bracket', label: 'Chaveamento' },
  { id: 'stats', label: 'Estatísticas' },
  { id: 'predictions', label: 'Meus palpites' },
  { id: 'ranking', label: 'Ranking' },
  { id: 'rules', label: 'Regras' },
];

export function LeagueWorldCupPage() {
  const { leagueId } = useParams();
  const [league, setLeague] = useState(null);
  const [overview, setOverview] = useState(null);
  const [groups, setGroups] = useState([]);
  const [bracketStages, setBracketStages] = useState([]);
  const [predictions, setPredictions] = useState([]);
  const [ranking, setRanking] = useState([]);
  const [activeTab, setActiveTab] = useState('groups');
  const [activeGroupCode, setActiveGroupCode] = useState('');
  const [drafts, setDrafts] = useState({});
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshingStructure, setIsRefreshingStructure] = useState(false);
  const [error, setError] = useState('');
  const [socketConnected, setSocketConnected] = useState(null);

  const predictionsByMatch = useMemo(
    () => Object.fromEntries(predictions.map((prediction) => [prediction.match_id, prediction])),
    [predictions],
  );
  const selectedGroup = useMemo(
    () => groups.find((group) => group.code === activeGroupCode) ?? groups[0] ?? null,
    [activeGroupCode, groups],
  );

  const loadCompetition = useCallback(async () => {
    const [leaguePayload, overviewPayload] = await Promise.all([
      portalService.leagueDetails(leagueId),
      portalService.leagueWorldCup(leagueId),
    ]);

    setLeague(leaguePayload.data.league);
    setOverview(overviewPayload);
  }, [leagueId]);

  const loadGroups = useCallback(async () => {
    const payload = await portalService.leagueGroups(leagueId);
    setGroups(payload.data.groups ?? []);
  }, [leagueId]);

  const loadBracket = useCallback(async () => {
    const payload = await portalService.leagueBracket(leagueId);
    setBracketStages(payload.data.bracket?.stages ?? []);
  }, [leagueId]);

  const loadPredictions = useCallback(async () => {
    const payload = await portalService.leaguePredictions(leagueId);
    setPredictions(payload.data.predictions ?? []);
  }, [leagueId]);

  const loadRanking = useCallback(async () => {
    const payload = await portalService.leagueRanking(leagueId);
    setRanking(payload.data.rankings ?? []);
  }, [leagueId]);

  const loadStructure = useCallback(async ({ silent = false } = {}) => {
    if (!silent) {
      setIsRefreshingStructure(true);
    }

    try {
      await Promise.all([loadGroups(), loadBracket()]);
    } finally {
      if (!silent) {
        setIsRefreshingStructure(false);
      }
    }
  }, [loadBracket, loadGroups]);

  const loadInitial = useCallback(async () => {
    setIsLoading(true);
    setError('');

    try {
      await Promise.all([
        loadCompetition(),
        loadStructure({ silent: true }),
        loadPredictions(),
        loadRanking(),
      ]);
    } catch (requestError) {
      setError(requestError.message || 'Não foi possível carregar a liga da Copa.');
    } finally {
      setIsLoading(false);
      setIsRefreshingStructure(false);
    }
  }, [loadCompetition, loadPredictions, loadRanking, loadStructure]);

  useEffect(() => {
    loadInitial();
  }, [loadInitial]);

  useEffect(() => {
    if (!groups.length) {
      return;
    }

    if (!activeGroupCode || !groups.some((group) => group.code === activeGroupCode)) {
      setActiveGroupCode(groups[0].code);
    }
  }, [activeGroupCode, groups]);

  useEffect(() => {
    const echo = getEcho();

    if (!echo) {
      setSocketConnected(false);
      return undefined;
    }

    setSocketConnected(true);

    const reloadStructure = () => {
      loadStructure({ silent: true }).catch(() => {});
    };
    const reloadRanking = () => {
      loadRanking().catch(() => {});
    };
    const reloadScoring = () => {
      Promise.all([loadPredictions(), loadRanking()]).catch(() => {});
    };

    echo.channel('world-cup')
      .listen('.world_cup.match.updated', reloadStructure)
      .listen('.world_cup.match.finished', reloadStructure)
      .listen('.world_cup.stage.updated', reloadStructure);

    echo.private(`league.${leagueId}`)
      .listen('.world_cup.predictions.locked', reloadStructure)
      .listen('.world_cup.prediction.scored', reloadScoring)
      .listen('.world_cup.ranking.updated', reloadRanking);

    const connection = echo.connector?.pusher?.connection;
    const handleConnected = () => setSocketConnected(true);
    const handleDisconnected = () => setSocketConnected(false);

    connection?.bind('connected', handleConnected);
    connection?.bind('disconnected', handleDisconnected);
    connection?.bind('unavailable', handleDisconnected);
    connection?.bind('failed', handleDisconnected);

    return () => {
      connection?.unbind('connected', handleConnected);
      connection?.unbind('disconnected', handleDisconnected);
      connection?.unbind('unavailable', handleDisconnected);
      connection?.unbind('failed', handleDisconnected);
      leaveChannel('world-cup');
      leaveChannel(`league.${leagueId}`);
    };
  }, [leagueId, loadPredictions, loadRanking, loadStructure]);

  function draftFor(match) {
    const savedPrediction = match?.id ? predictionsByMatch[match.id] : null;

    return drafts[match?.id] ?? {
      predicted_home_score: savedPrediction?.predicted_home_score ?? 0,
      predicted_away_score: savedPrediction?.predicted_away_score ?? 0,
      predicted_winner_side: savedPrediction?.predicted_winner_side ?? null,
    };
  }

  function updateDraft(matchId, field, value) {
    setDrafts((current) => ({
      ...current,
      [matchId]: {
        ...(current[matchId] ?? {
          predicted_home_score: predictionsByMatch[matchId]?.predicted_home_score ?? 0,
          predicted_away_score: predictionsByMatch[matchId]?.predicted_away_score ?? 0,
          predicted_winner_side: predictionsByMatch[matchId]?.predicted_winner_side ?? null,
        }),
        [field]: field === 'predicted_winner_side'
          ? value
          : Math.max(0, Number.parseInt(value, 10) || 0),
      },
    }));
  }

  function predictionWinnerLabel(prediction) {
    if (!['home', 'away'].includes(prediction?.predicted_winner_side)) {
      return null;
    }

    const team = prediction.predicted_winner_side === 'home'
      ? prediction.match?.home_team
      : prediction.match?.away_team;

    return teamLabel(team, prediction.predicted_winner_side === 'home' ? 'Mandante' : 'Visitante');
  }

  async function savePrediction(match) {
    if (!match?.id) {
      return;
    }

    try {
      const payload = await portalService.savePrediction(leagueId, match.id, draftFor(match));
      setPredictions((current) => {
        const others = current.filter((prediction) => prediction.match_id !== match.id);
        return [payload.data.prediction, ...others];
      });
      toast.success('Palpite salvo com sucesso.');
    } catch (requestError) {
      toast.error(requestError.message || 'Não foi possível salvar o palpite.');
    }
  }

  async function refreshRanking() {
    try {
      await loadRanking();
      toast.success('Ranking atualizado.');
    } catch (requestError) {
      toast.error(requestError.message || 'Não foi possível atualizar o ranking.');
    }
  }

  if (isLoading) {
    return <section className="content-loading">Carregando modo Copa...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  const meta = overview?.meta ?? {};

  return (
    <section className="league-copa-page">
      <div className="league-copa-hero">
        <div className="copa-topbar">
          <Link className="copa-icon-button" to={`/ligas/${leagueId}`} aria-label="Voltar para a liga">
            <ChevronLeft size={20} />
          </Link>
          <div>
            <p className="eyebrow">Copa do Mundo 2026</p>
            <h2>{league?.name}</h2>
            <p>Acompanhe grupos, chaveamento e faça seus palpites em cada confronto.</p>
          </div>
        </div>
        <div className="league-copa-stats">
          <article><Trophy size={20} /><span>Competição</span><strong>{overview?.data?.edition ? `${overview.data.edition.name} ${overview.data.edition.season}` : 'Copa 2026'}</strong></article>
          <article><CalendarDays size={20} /><span>Partidas</span><strong>{meta.matches_count ?? 0}</strong></article>
          <article><Users size={20} /><span>Participantes</span><strong>{league?.members_count ?? 0}/{league?.max_members}</strong></article>
          <article><Radio size={20} /><span>Atualização</span><strong>{socketConnected === false ? 'Ao vivo desconectado' : 'Ao vivo'}</strong></article>
        </div>
      </div>

      <nav className="league-copa-tabs" aria-label="Navegacao da liga">
        {tabs.map((tab) => (
          <button key={tab.id} type="button" className={activeTab === tab.id ? 'active' : ''} onClick={() => setActiveTab(tab.id)}>
            {tab.label}
          </button>
        ))}
      </nav>

      {activeTab === 'groups' && (
        <div className="content-section copa-phase-section">
          <div className="section-header">
            <div><p className="eyebrow">Primeira fase</p><h2>12 grupos oficiais</h2></div>
            <span className="diagnostic-pill">{groups.length} grupos</span>
          </div>
          {isRefreshingStructure ? <section className="content-loading compact">Atualizando grupos...</section> : null}
          <div className="copa-group-tabs group-submenu" role="tablist" aria-label="Grupos da Copa">
            {groups.map((group) => (
              <button key={group.code} type="button" className={group.code === selectedGroup?.code ? 'active' : ''} onClick={() => setActiveGroupCode(group.code)}>
                {group.code}
              </button>
            ))}
          </div>
          <div className="copa-groups-grid official single-group">
            {selectedGroup ? (
              <WorldCupGroupPanel
                group={selectedGroup}
                predictionsByMatch={predictionsByMatch}
                draftFor={draftFor}
                onDraftChange={updateDraft}
                onSave={savePrediction}
                allowPredictions
              />
            ) : (
              <section className="empty-state">Nenhum grupo disponível ainda.</section>
            )}
          </div>
        </div>
      )}

      {activeTab === 'bracket' && (
        <div className="content-section copa-phase-section">
          <div className="section-header">
            <div><p className="eyebrow">Mata-mata</p><h2>Chaveamento completo</h2></div>
            <span className="diagnostic-pill">{bracketStages.reduce((total, stage) => total + (stage.matches?.length ?? 0), 0)} confrontos</span>
          </div>
          {isRefreshingStructure ? <section className="content-loading compact">Atualizando chaveamento...</section> : null}
          <WorldCupBracketView
            stages={bracketStages}
            predictionsByMatch={predictionsByMatch}
            draftFor={draftFor}
            onDraftChange={updateDraft}
            onSave={savePrediction}
            allowPredictions
          />
        </div>
      )}

      {activeTab === 'stats' && (
        <div className="content-section copa-phase-section">
          <div className="section-header"><div><p className="eyebrow">Números</p><h2>Estatísticas da Copa</h2></div></div>
          <WorldCupStatsPanel groups={groups} bracketStages={bracketStages} />
        </div>
      )}

      {activeTab === 'predictions' && (
        <div className="content-section">
          <div className="section-header"><div><p className="eyebrow">Meus palpites</p><h2>{predictions.length ? 'Palpites enviados' : 'Nenhum palpite salvo ainda'}</h2></div></div>
          <div className="prediction-list">
            {predictions.map((prediction) => (
              <article key={prediction.id}>
                <ClipboardList size={20} />
                <div>
                  <strong>{teamLabel(prediction.match?.home_team, 'Mandante')} {prediction.predicted_home_score} x {prediction.predicted_away_score} {teamLabel(prediction.match?.away_team, 'Visitante')}</strong>
                  {predictionWinnerLabel(prediction) ? <small>Classificado: {predictionWinnerLabel(prediction)}</small> : null}
                  <span>{prediction.status === 'settled' ? `${prediction.points_awarded} pontos - ${prediction.score_reason_label}` : 'Aguardando resultado oficial'}</span>
                </div>
              </article>
            ))}
            {!predictions.length ? <section className="empty-state">Seus palpites vao aparecer aqui conforme forem salvos.</section> : null}
          </div>
        </div>
      )}

      {activeTab === 'ranking' && (
        <div className="content-section">
          <div className="section-header">
            <div><p className="eyebrow">Classificação</p><h2>Ranking por pontos</h2></div>
            <button type="button" className="text-action" onClick={refreshRanking}>Atualizar</button>
          </div>
          <div className="ranking-table">
            {ranking.map((row) => (
              <article key={row.id}>
                <strong>{row.position ?? '-'}</strong>
                <span>{row.member?.user?.name ?? 'Participante'}</span>
                <b>{row.total_points} pts</b>
                <small>{row.exact_scores} placares exatos</small>
              </article>
            ))}
          </div>
        </div>
      )}

      {activeTab === 'rules' && (
        <div className="content-section rules-panel">
          <article><ShieldCheck size={20} /><span>Placar exato</span><strong>{league?.settings?.points_exact_score ?? 5} pts</strong></article>
          <article><ShieldCheck size={20} /><span>Saldo de gols correto</span><strong>{league?.settings?.points_correct_goal_difference ?? 3} pts</strong></article>
          <article><ShieldCheck size={20} /><span>Resultado correto</span><strong>{league?.settings?.points_correct_outcome_scoreline ?? 2} pts</strong></article>
          <article className="rules-panel-note"><ShieldCheck size={20} /><span>No mata-mata, empate exige escolher o classificado. Pênaltis decidem o vencedor, mas não alteram o placar.</span></article>
          <Link to={`/ligas/${leagueId}`}>Voltar para a liga</Link>
        </div>
      )}
    </section>
  );
}
