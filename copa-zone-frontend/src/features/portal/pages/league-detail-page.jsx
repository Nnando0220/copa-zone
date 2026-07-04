import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { KeyRound, LogOut, Trophy, UserPlus, Users } from 'lucide-react';
import { toast } from 'sonner';
import { getRequestErrorMessage } from '../../../api/errors';
import { MatchList } from '../components/match-list';
import { MatchPeriodFilter } from '../components/match-period-filter';
import { LeagueCard } from '../components/league-card';
import { portalService } from '../services/portal-service';

export function LeagueDetailPage() {
  const { leagueId } = useParams();
  const navigate = useNavigate();
  const [league, setLeague] = useState(null);
  const [matches, setMatches] = useState([]);
  const [matchesTotal, setMatchesTotal] = useState(0);
  const [period, setPeriod] = useState('all');
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMatches, setIsLoadingMatches] = useState(false);
  const [isJoining, setIsJoining] = useState(false);
  const [isLeaving, setIsLeaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    portalService.leagueDetails(leagueId)
      .then((leaguePayload) => {
        setLeague(leaguePayload.data.league);
      })
      .catch((requestError) => setError(requestError.message || 'NÃ£o foi possÃ­vel carregar a liga.'))
      .finally(() => setIsLoading(false));
  }, [leagueId]);

  useEffect(() => {
    setIsLoadingMatches(true);
    portalService.leagueMatches(leagueId, { period })
      .then((matchesPayload) => {
        setMatches(matchesPayload.data.matches ?? []);
        setMatchesTotal(matchesPayload.meta?.total ?? 0);
      })
      .catch((requestError) => setError(requestError.message || 'NÃ£o foi possÃ­vel carregar as partidas da liga.'))
      .finally(() => setIsLoadingMatches(false));
  }, [leagueId, period]);

  async function joinLeague() {
    setIsJoining(true);
    setError('');

    try {
      const payload = await portalService.joinPublicLeague(leagueId);
      setLeague(payload.data.league);
      toast.success('Entrada realizada com sucesso.');
    } catch (requestError) {
      setError(requestError.message || 'NÃ£o foi possÃ­vel entrar nesta liga.');
    } finally {
      setIsJoining(false);
    }
  }

  async function leaveLeague() {
    setIsLeaving(true);
    setError('');

    try {
      const payload = await portalService.leaveLeague(leagueId);
      setLeague(payload.data.league);
      toast.success('VocÃª saiu da liga.');
      navigate('/ligas/publicas');
    } catch (requestError) {
      setError(requestError.message || 'NÃ£o foi possÃ­vel sair desta liga.');
    } finally {
      setIsLeaving(false);
    }
  }

  if (isLoading) {
    return <section className="content-loading">Carregando liga...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  return (
    <section className="league-detail-page">
      <LeagueCard league={league} actionLabel={league.membership ? 'Abrir modo Copa' : 'Ver dados da liga'} to={league.membership ? `/ligas/${leagueId}/copa` : undefined} />

      <div className="league-detail-actions">
        {league.membership ? (
          <>
            <Link className="primary-action" to={`/ligas/${leagueId}/copa`}>
              <Trophy size={18} />
              Abrir modo Copa
            </Link>
            {!league.is_owner && (
              <button type="button" className="secondary-action danger" onClick={leaveLeague} disabled={isLeaving}>
                <LogOut size={18} />
                {isLeaving ? 'Saindo...' : 'Sair da liga'}
              </button>
            )}
          </>
        ) : (
          <button type="button" className="primary-action" onClick={joinLeague} disabled={isJoining}>
            <UserPlus size={18} />
            {isJoining ? 'Entrando...' : 'Entrar na liga'}
          </button>
        )}
      </div>

      <div className="league-detail-grid">
        <article>
          <Users size={22} />
          <span>Participantes</span>
          <strong>
            {league.members_count ?? 0}/{league.max_members}
          </strong>
        </article>

        {league.invite_code && (
          <article>
            <KeyRound size={22} />
            <span>CÃ³digo privado</span>
            <strong>{league.invite_code}</strong>
          </article>
        )}

        <article>
          <span>Pontos por acerto</span>
          <strong>{league.settings?.points_correct_outcome ?? 3}</strong>
        </article>
      </div>

      <div className="content-section league-matches-section">
        <div className="section-header">
          <div>
            <p className="eyebrow">Partidas da Copa</p>
            <h2>CalendÃ¡rio da Copa</h2>
          </div>
          <span className="diagnostic-pill">Palpites em breve</span>
        </div>

        <MatchPeriodFilter value={period} onChange={setPeriod} total={matchesTotal} />
        {isLoadingMatches ? <section className="content-loading compact">Atualizando partidas...</section> : <MatchList matches={matches} />}
      </div>

      <div className="empty-state compact">
        <h2>PrÃ³xima etapa da liga</h2>
        <p>Em breve vocÃª poderÃ¡ acompanhar todos os palpites diretamente pela pÃ¡gina da Copa.</p>
        <Link to="/ligas/minhas">Voltar para minhas ligas</Link>
      </div>
    </section>
  );
}





