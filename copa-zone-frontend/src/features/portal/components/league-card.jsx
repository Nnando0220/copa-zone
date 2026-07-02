import { LockKeyhole, Radio, Users } from 'lucide-react';
import { Link } from 'react-router';

function visibilityLabel(visibility) {
  return visibility === 'private' ? 'Privada' : 'Publica';
}

function statusLabel(status) {
  if (status === 'open') {
    return 'Aberta';
  }

  if (status === 'active') {
    return 'Em andamento';
  }

  return 'Configurando';
}

export function LeagueCard({ league, actionLabel = 'Abrir liga', onAction, to, isActionDisabled = false }) {
  const isPrivate = league.visibility === 'private';

  return (
    <article className="league-card">
      <div className="league-card-head">
        <span className={isPrivate ? 'league-badge private' : 'league-badge'}>
          {isPrivate ? <LockKeyhole size={14} /> : <Radio size={14} />}
          {visibilityLabel(league.visibility)}
        </span>
        <span className="league-status">{statusLabel(league.status)}</span>
      </div>

      <h2>{league.name}</h2>
      <p>Liga da Copa pronta para palpites, pontos e ranking.</p>

      <div className="league-card-meta">
        <span>
          <Users size={16} />
          {league.members_count ?? 0}/{league.max_members} participantes
        </span>
        {league.membership && <span>{league.membership.role === 'owner' ? 'Gestor' : 'Participante'}</span>}
      </div>

      {to ? (
        <Link className="league-card-action" to={to}>{actionLabel}</Link>
      ) : onAction ? (
        <button type="button" onClick={onAction} disabled={isActionDisabled}>
          {actionLabel}
        </button>
      ) : null}
    </article>
  );
}
