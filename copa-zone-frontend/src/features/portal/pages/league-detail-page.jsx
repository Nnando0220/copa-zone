import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { Copy, Eye, EyeOff, KeyRound, Link2, LogOut, UserPlus, Users } from 'lucide-react';
import { toast } from 'sonner';
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
  const [period, setPeriod] = useState('today');
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMatches, setIsLoadingMatches] = useState(false);
  const [isJoining, setIsJoining] = useState(false);
  const [isLeaving, setIsLeaving] = useState(false);
  const [isInviteVisible, setIsInviteVisible] = useState(false);
  const [error, setError] = useState('');
  const inviteLink = league?.invite_code ? `${window.location.origin}/ligas/entrar?codigo=${league.invite_code}` : '';

  useEffect(() => {
    portalService.leagueDetails(leagueId)
      .then((leaguePayload) => {
        setLeague(leaguePayload.data.league);
      })
      .catch((requestError) => setError(requestError.message || 'Nao foi possivel carregar a liga.'))
      .finally(() => setIsLoading(false));
  }, [leagueId]);

  useEffect(() => {
    setIsLoadingMatches(true);
    portalService.leagueMatches(leagueId, { period })
      .then((matchesPayload) => {
        setMatches(matchesPayload.data.matches ?? []);
        setMatchesTotal(matchesPayload.meta?.total ?? 0);
      })
      .catch((requestError) => setError(requestError.message || 'Nao foi possivel carregar as partidas da liga.'))
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
      setError(requestError.message || 'Nao foi possivel entrar nesta liga.');
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
      toast.success('Voce saiu da liga.');
      navigate('/ligas/publicas');
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel sair desta liga.');
    } finally {
      setIsLeaving(false);
    }
  }

  async function copyInvite(value, label) {
    try {
      await navigator.clipboard.writeText(value);
      toast.success(`${label} copiado.`);
    } catch {
      toast.error('Nao foi possivel copiar automaticamente.');
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

      {(!league.membership || !league.is_owner) && (
        <div className="league-detail-actions">
          {league.membership ? (
            <button type="button" className="secondary-action danger" onClick={leaveLeague} disabled={isLeaving}>
              <LogOut size={18} />
              {isLeaving ? 'Saindo...' : 'Sair da liga'}
            </button>
          ) : (
          <button type="button" className="primary-action" onClick={joinLeague} disabled={isJoining}>
            <UserPlus size={18} />
            {isJoining ? 'Entrando...' : 'Entrar na liga'}
          </button>
          )}
        </div>
      )}

      <div className="league-detail-grid">
        <article>
          <Users size={22} />
          <span>Participantes</span>
          <strong>
            {league.members_count ?? 0}/{league.max_members}
          </strong>
        </article>

        <article>
          <span>Pontos por acerto</span>
          <strong>{league.settings?.points_correct_outcome ?? 3}</strong>
        </article>
      </div>

      {league.is_owner && league.invite_code && (
        <section className="owner-invite-panel">
          <div className="owner-invite-header">
            <KeyRound size={22} />
            <div>
              <p className="eyebrow">Convite privado</p>
              <h2>Compartilhe sua liga com seguranca</h2>
              <span>Somente o dono da liga consegue ver este codigo e o link de convite.</span>
            </div>
            <button type="button" className="secondary-action" onClick={() => setIsInviteVisible((current) => !current)}>
              {isInviteVisible ? <EyeOff size={17} /> : <Eye size={17} />}
              {isInviteVisible ? 'Ocultar' : 'Mostrar'}
            </button>
          </div>

          <div className="owner-invite-grid">
            <article>
              <span><KeyRound size={16} /> Codigo da liga</span>
              <strong>{isInviteVisible ? league.invite_code : '********'}</strong>
              <button type="button" onClick={() => copyInvite(league.invite_code, 'Codigo')} disabled={!isInviteVisible}>
                <Copy size={16} />
                Copiar codigo
              </button>
            </article>

            <article>
              <span><Link2 size={16} /> Link para compartilhar</span>
              <strong>{isInviteVisible ? inviteLink : '************************'}</strong>
              <button type="button" onClick={() => copyInvite(inviteLink, 'Link')} disabled={!isInviteVisible}>
                <Copy size={16} />
                Copiar link
              </button>
            </article>
          </div>
        </section>
      )}

      <div className="content-section league-matches-section">
        <div className="section-header">
          <div>
            <p className="eyebrow">Partidas da Copa</p>
            <h2>Calendario da Copa</h2>
          </div>
          <span className="diagnostic-pill">Palpites em breve</span>
        </div>

        <MatchPeriodFilter value={period} onChange={setPeriod} total={matchesTotal} />
        {isLoadingMatches ? <section className="content-loading compact">Atualizando partidas...</section> : <MatchList matches={matches} />}
      </div>

      <div className="empty-state compact">
        <h2>Proxima etapa da liga</h2>
        <p>Em breve voce podera acompanhar todos os palpites diretamente pela pagina da Copa.</p>
        <Link to="/ligas/minhas">Voltar para minhas ligas</Link>
      </div>
    </section>
  );
}
