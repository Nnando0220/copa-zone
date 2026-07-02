import { formatBrazilDateTime } from '../../../utils/date-format';
import {
  localizedTeamName,
  matchStateLabelFor,
  normalizeMatchState,
  roundLabelFor,
} from './world-cup-contracts';

function formatDate(value) {
  return formatBrazilDateTime(value, 'Data indefinida');
}

function scoreLabel(match) {
  if (match.home_score === null || match.home_score === undefined || match.away_score === null || match.away_score === undefined) {
    return 'x';
  }

  const penalties = match.home_penalty_score !== null
    && match.home_penalty_score !== undefined
    && match.away_penalty_score !== null
    && match.away_penalty_score !== undefined
    ? ` (Pen. ${match.home_penalty_score} x ${match.away_penalty_score})`
    : '';

  return `${match.home_score} x ${match.away_score}${penalties}`;
}

function teamName(team, fallback) {
  return localizedTeamName(team?.display_name || team?.name || fallback);
}

function matchStageLabel(match) {
  return match.group?.display_name
    || match.group?.name
    || roundLabelFor(match.bracket_stage || match.round, 'Copa do Mundo');
}

function matchStatus(match) {
  const state = normalizeMatchState(match.match_state || match.status);

  return {
    state,
    label: matchStateLabelFor(state, match.match_state_label || match.status_label),
  };
}

export function MatchList({ matches }) {
  if (!matches.length) {
    return (
      <div className="empty-state compact">
        <h2>Nenhuma partida sincronizada</h2>
        <p>Rode o comando de sincronizacao da Copa para popular esta lista.</p>
      </div>
    );
  }

  return (
    <div className="match-list">
      {matches.map((match) => {
        const status = matchStatus(match);

        return (
          <article key={match.id} className="match-card">
            <div className="match-card-meta">
              <span>{matchStageLabel(match)}</span>
              <strong>{formatDate(match.starts_at_br || match.starts_at)}</strong>
            </div>

            <div className="match-scoreline">
              <span>{teamName(match.home_team, 'Mandante')}</span>
              <strong>{scoreLabel(match)}</strong>
              <span>{teamName(match.away_team, 'Visitante')}</span>
            </div>

            <div className="match-card-footer">
              <span className={`match-status ${status.state}`}>{status.label}</span>
              {match.venue_name && <span>{match.venue_name}</span>}
            </div>
          </article>
        );
      })}
    </div>
  );
}
