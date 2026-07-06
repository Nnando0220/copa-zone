import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowRightLeft,
  Award,
  BarChart3,
  CalendarCheck,
  Check,
  Equal,
  List,
  Flame,
  Lightbulb,
  Shield,
  Target,
  TrendingUp,
  Trophy,
  Zap,
} from 'lucide-react';
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { formatBrazilDateTime } from '../../../utils/date-format';
import {
  STAGE_ORDER,
  localizedTeamName,
  matchStateLabelFor,
  normalizeMatchState,
  normalizeRoundKey,
  roundLabelFor,
  teamCodeFor,
} from './world-cup-contracts';

function formatDate(value) {
  return value ? formatBrazilDateTime(value) : 'Horário a definir';
}

export function teamLabel(team, fallback) {
  return localizedTeamName(team?.display_name || team?.name || fallback);
}

function teamFlagUrl(team) {
  return team?.flag_url || team?.flagUrl || team?.flag || team?.logo_url || team?.icon_url || team?.team_icon_url || null;
}

function shortTeamCode(team, label) {
  return teamCodeFor(team, label);
}

function teamCodeClassName(code) {
  return code === '???' ? 'missing-code' : '';
}

function scoreLabel(match) {
  if (match.home_score === null || match.home_score === undefined || match.away_score === null || match.away_score === undefined) {
    return 'x';
  }

  return `${match.home_score} x ${match.away_score}`;
}

function penaltyScoreLabel(match) {
  if (match.winner_source !== 'penalties') {
    return null;
  }

  if (Number(match.home_score) !== Number(match.away_score)) {
    return null;
  }

  if (
    match.home_penalty_score === null
    || match.home_penalty_score === undefined
    || match.away_penalty_score === null
    || match.away_penalty_score === undefined
  ) {
    return null;
  }

  return `Pen. ${match.home_penalty_score} x ${match.away_penalty_score}`;
}

function resultDetailLabel(match) {
  if (penaltyScoreLabel(match)) {
    return penaltyScoreLabel(match);
  }

  if (match.winner_source === 'extra_time') {
    return 'Vencedor na prorrogação';
  }

  return match.winner_source === 'tiebreaker' ? 'Vencedor definido no desempate' : null;
}

function bracketTeamLabel(match, side) {
  const team = side === 'home' ? match.home_team : match.away_team;
  const fallback = side === 'home' ? match.slot_home_label : match.slot_away_label;
  const label = teamLabel(team, fallback || 'Aguardando classificado');

  if (fallback && teamLooksUnresolved(team, label)) {
    return localizedTeamName(fallback);
  }

  return label;
}

function teamLooksUnresolved(team, label) {
  const value = [
    team?.display_name,
    team?.name,
    team?.code,
    label,
  ].filter(Boolean).join(' ').toLowerCase();

  return value.includes('/')
    || value.includes('\\')
    || value.includes('winner ')
    || value.includes('vencedor ')
    || value.includes('loser ')
    || value.includes('perdedor ');
}

function matchResultSides(match) {
  const hasScore = match.home_score !== null && match.home_score !== undefined
    && match.away_score !== null && match.away_score !== undefined;

  if (!hasScore) {
    return { hasScore: false, homeIsWinner: false, awayIsWinner: false };
  }

  if (match.winner_side === 'home') {
    return { hasScore: true, homeIsWinner: true, awayIsWinner: false };
  }

  if (match.winner_side === 'away') {
    return { hasScore: true, homeIsWinner: false, awayIsWinner: true };
  }

  const homeScore = Number(match.home_score);
  const awayScore = Number(match.away_score);

  return {
    hasScore: true,
    homeIsWinner: homeScore > awayScore,
    awayIsWinner: awayScore > homeScore,
  };
}

function bracketStageKey(stage, match = null) {
  return normalizeRoundKey(match?.bracket_stage)
    || normalizeRoundKey(stage?.code)
    || normalizeRoundKey(match?.round)
    || 'unknown_stage';
}

function bracketStageLabel(stage) {
  return roundLabelFor(stage?.code || stage?.display_name, stage?.display_name || 'Mata-mata');
}

function bracketMatchRoundLabel(match, stage) {
  return roundLabelFor(bracketStageKey(stage, match), bracketStageLabel(stage));
}

function normalizedStatus(match) {
  const state = normalizeMatchState(match?.match_state || match?.status);

  return {
    state,
    label: matchStateLabelFor(state, match?.match_state_label || match?.status_label),
  };
}

export function computeWorldCupStats(groups, bracketStages) {
  const groupMatches = (groups || []).flatMap((g) => g.matches || []);
  const bracketMatches = (bracketStages || []).flatMap((s) => s.matches || []);
  const allMatches = [...groupMatches, ...bracketMatches];

  const finished = allMatches.filter(
    (m) => m.home_score !== null && m.home_score !== undefined
      && m.away_score !== null && m.away_score !== undefined,
  );

  const totalGoals = finished.reduce(
    (sum, m) => sum + Number(m.home_score) + Number(m.away_score), 0,
  );
  const avgGoals = finished.length ? totalGoals / finished.length : 0;

  let biggestBlowout = null;
  let biggestDiff = 0;
  let mostGoalsMatch = null;
  let mostGoals = 0;

  for (const m of finished) {
    const diff = Math.abs(Number(m.home_score) - Number(m.away_score));
    const total = Number(m.home_score) + Number(m.away_score);
    if (diff > biggestDiff) { biggestDiff = diff; biggestBlowout = m; }
    if (total > mostGoals) { mostGoals = total; mostGoalsMatch = m; }
  }

  const draws = finished.filter((m) => Number(m.home_score) === Number(m.away_score));
  const gamesWithGoals = finished.filter((m) => Number(m.home_score) + Number(m.away_score) > 0);
  const awayWins = finished.filter((m) => Number(m.away_score) > Number(m.home_score));
  const chronologicalFinished = finished
    .map((match, index) => {
      const rawDate = match.starts_at || match.started_at || match.match_date || match.scheduled_at || match.starts_at_br;
      const timestamp = rawDate ? Date.parse(rawDate) : Number.NaN;
      return { match, index, timestamp: Number.isNaN(timestamp) ? null : timestamp };
    })
    .sort((a, b) => {
      if (a.timestamp !== null && b.timestamp !== null && a.timestamp !== b.timestamp) {
        return a.timestamp - b.timestamp;
      }

      if (a.timestamp !== null && b.timestamp === null) {
        return -1;
      }

      if (a.timestamp === null && b.timestamp !== null) {
        return 1;
      }

      return a.index - b.index;
    });
  let noDrawStreak = 0;
  let currentNoDrawStreak = 0;
  for (const { match } of chronologicalFinished) {
    if (Number(match.home_score) === Number(match.away_score)) {
      currentNoDrawStreak = 0;
    } else {
      currentNoDrawStreak += 1;
      noDrawStreak = Math.max(noDrawStreak, currentNoDrawStreak);
    }
  }

  const firstKnockoutStage = (bracketStages || []).find((stage) => (
    ['round_of_32', 'round_of_16'].includes(normalizeRoundKey(stage.code || stage.display_name))
  ));
  const classifiedTeams = new Set();
  for (const match of firstKnockoutStage?.matches || []) {
    for (const team of [match.home_team, match.away_team]) {
      if (!team) {
        continue;
      }

      const label = teamLabel(team, '');
      if (!label) {
        continue;
      }

      classifiedTeams.add(String(team.id || team.team_id || team.external_id || label));
    }
  }
  const classifiedTeamsTotal = firstKnockoutStage ? (firstKnockoutStage.matches?.length || 0) * 2 : 0;

  const teamMap = {};
  for (const m of finished) {
    const hName = teamLabel(m.home_team, '');
    const aName = teamLabel(m.away_team, '');
    if (hName) {
      if (!teamMap[hName]) teamMap[hName] = { name: hName, team: m.home_team, goalsFor: 0, goalsAgainst: 0, games: 0 };
      teamMap[hName].goalsFor += Number(m.home_score);
      teamMap[hName].goalsAgainst += Number(m.away_score);
      teamMap[hName].games += 1;
    }
    if (aName) {
      if (!teamMap[aName]) teamMap[aName] = { name: aName, team: m.away_team, goalsFor: 0, goalsAgainst: 0, games: 0 };
      teamMap[aName].goalsFor += Number(m.away_score);
      teamMap[aName].goalsAgainst += Number(m.home_score);
      teamMap[aName].games += 1;
    }
  }

  const topScorers = Object.values(teamMap).sort((a, b) => b.goalsFor - a.goalsFor).slice(0, 5);
  const bestAttack = Object.values(teamMap)
    .filter((t) => t.games >= 2)
    .map((t) => ({ ...t, avgGoals: t.goalsFor / t.games }))
    .sort((a, b) => b.avgGoals - a.avgGoals)
    .slice(0, 5);
  const bestDefense = Object.values(teamMap)
    .filter((t) => t.games >= 2)
    .map((t) => ({ ...t, avgConceded: t.goalsAgainst / t.games }))
    .sort((a, b) => a.avgConceded - b.avgConceded)
    .slice(0, 5);

  const phaseMap = {};
  if (groupMatches.length) {
    const gf = groupMatches.filter((m) => m.home_score != null && m.away_score != null);
    if (gf.length) {
      const g = gf.reduce((s, m) => s + Number(m.home_score) + Number(m.away_score), 0);
      phaseMap['Fase de Grupos'] = { goals: g, games: gf.length, avg: g / gf.length };
    }
  }
  for (const stage of (bracketStages || [])) {
    const sf = (stage.matches || []).filter((m) => m.home_score != null && m.away_score != null);
    if (sf.length) {
      const g = sf.reduce((s, m) => s + Number(m.home_score) + Number(m.away_score), 0);
      phaseMap[bracketStageLabel(stage)] = { goals: g, games: sf.length, avg: g / sf.length };
    }
  }

  let topPhase = null;
  for (const [name, s] of Object.entries(phaseMap)) {
    if (!topPhase || s.avg > topPhase.avg) topPhase = { name, ...s };
  }

  const scoreCounts = {};
  for (const m of finished) {
    const hi = Math.max(Number(m.home_score), Number(m.away_score));
    const lo = Math.min(Number(m.home_score), Number(m.away_score));
    const key = `${hi}x${lo}`;
    scoreCounts[key] = (scoreCounts[key] || 0) + 1;
  }
  let mostCommonScore = null;
  let mostCommonCount = 0;
  for (const [score, count] of Object.entries(scoreCounts)) {
    if (count > mostCommonCount) { mostCommonCount = count; mostCommonScore = score; }
  }

  return {
    totalGoals, avgGoals,
    finishedCount: finished.length,
    totalCount: allMatches.length,
    biggestBlowout, biggestDiff,
    drawsCount: draws.length,
    drawsPercent: finished.length ? Math.round((draws.length / finished.length) * 100) : 0,
    gamesWithGoals: gamesWithGoals.length,
    gamesWithGoalsPercent: finished.length ? Math.round((gamesWithGoals.length / finished.length) * 100) : 0,
    topScorers, bestAttack, bestDefense,
    topPhase, mostGoalsMatch, mostGoals,
    mostCommonScore, mostCommonCount,
    awayWinsCount: awayWins.length,
    noDrawStreak,
    classifiedTeamsCount: classifiedTeams.size,
    classifiedTeamsTotal,
  };
}

function numericCell(key) {
  return {
    accessorKey: key,
    header: key.toUpperCase(),
    cell: ({ getValue }) => getValue() ?? 0,
    meta: { align: 'center' },
  };
}

export function GroupStandingsTable({ standings = [] }) {
  const columns = useMemo(() => [
    {
      accessorKey: 'position',
      header: '#',
      cell: ({ getValue }) => getValue() ?? '-',
      meta: { align: 'center' },
    },
    {
      id: 'team',
      header: 'Seleção',
      cell: ({ row }) => row.original.team?.display_name || row.original.team?.name || 'Seleção',
      meta: { align: 'team' },
    },
    {
      accessorKey: 'points',
      header: 'PTS',
      cell: ({ getValue }) => <strong>{getValue() ?? 0}</strong>,
      meta: { align: 'center', highlight: true },
    },
    numericCell('played'),
    numericCell('won'),
    numericCell('drawn'),
    numericCell('lost'),
    numericCell('goals_for'),
    numericCell('goals_against'),
    numericCell('goal_difference'),
  ], []);
  const table = useReactTable({
    data: standings,
    columns,
    getCoreRowModel: getCoreRowModel(),
  });

  return (
    <div className="copa-standings-shell">
      <table className="copa-standings-table">
        <thead>
          {table.getHeaderGroups().map((headerGroup) => (
            <tr key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <th key={header.id} className={`align-${header.column.columnDef.meta?.align ?? 'center'}`}>
                  {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                </th>
              ))}
            </tr>
          ))}
        </thead>
        <tbody>
          {table.getRowModel().rows.map((row) => (
            <tr key={row.id}>
              {row.getVisibleCells().map((cell) => (
                <td
                  key={cell.id}
                  className={[
                    `align-${cell.column.columnDef.meta?.align ?? 'center'}`,
                    cell.column.columnDef.meta?.highlight ? 'highlight' : '',
                  ].filter(Boolean).join(' ')}
                >
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function AnimatedValue({ value, decimals = 0, suffix = '' }) {
  const [display, setDisplay] = useState(0);
  const frameRef = useRef(null);

  useEffect(() => {
    const target = Number(value) || 0;
    if (!target) { setDisplay(0); return undefined; }

    const duration = 800;
    const start = performance.now();

    function tick(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      setDisplay(eased * target);
      if (progress < 1) { frameRef.current = requestAnimationFrame(tick); }
    }

    frameRef.current = requestAnimationFrame(tick);
    return () => { if (frameRef.current) cancelAnimationFrame(frameRef.current); };
  }, [value]);

  return <strong>{decimals ? display.toFixed(decimals) : Math.round(display)}{suffix}</strong>;
}

export function WorldCupStatsPanel({ groups = [], bracketStages = [] }) {
  const stats = useMemo(() => computeWorldCupStats(groups, bracketStages), [groups, bracketStages]);
  const [rankTab, setRankTab] = useState('scorers');

  if (!stats.finishedCount) {
    return <section className="empty-state compact">Estatísticas serão exibidas após os primeiros jogos.</section>;
  }

  const rankData = rankTab === 'scorers' ? stats.topScorers
    : rankTab === 'attack' ? stats.bestAttack
    : stats.bestDefense;

  const rankMax = rankTab === 'scorers'
    ? (rankData[0]?.goalsFor || 1)
    : rankTab === 'attack'
      ? (rankData[0]?.avgGoals || 1)
      : (rankData[rankData.length - 1]?.avgConceded || 1);

  function barWidth(team) {
    if (rankTab === 'scorers') return (team.goalsFor / rankMax) * 100;
    if (rankTab === 'attack') return (team.avgGoals / rankMax) * 100;
    return rankMax ? ((rankMax - team.avgConceded + 0.1) / (rankMax + 0.1)) * 100 : 50;
  }

  function rankValue(team) {
    if (rankTab === 'scorers') return team.goalsFor;
    if (rankTab === 'attack') return team.avgGoals.toFixed(1);
    return team.avgConceded.toFixed(1);
  }

  function rankUnit() {
    if (rankTab === 'scorers') return 'gols';
    if (rankTab === 'attack') return 'gols/jogo';
    return 'sofridos/jogo';
  }

  return (
    <section className="copa-stats-panel">
      <div className="copa-stats-headline">
        <article className="copa-stat-card">
          <Zap size={22} />
          <AnimatedValue value={stats.totalGoals} />
          <span>Total de gols</span>
        </article>
        <article className="copa-stat-card">
          <TrendingUp size={22} />
          <AnimatedValue value={stats.avgGoals} decimals={1} />
          <span>Média / jogo</span>
        </article>
        <article className="copa-stat-card">
          <CalendarCheck size={22} />
          <strong>{stats.finishedCount}<small> / {stats.totalCount}</small></strong>
          <span>Jogos disputados</span>
          <div className="stat-progress"><div className="stat-progress-fill" style={{ '--progress': `${Math.round((stats.finishedCount / Math.max(stats.totalCount, 1)) * 100)}%` }} /></div>
        </article>
        <article className="copa-stat-card accent">
          <Flame size={22} />
          <strong className="stat-matchup">{stats.biggestBlowout ? `${shortTeamCode(stats.biggestBlowout.home_team, teamLabel(stats.biggestBlowout.home_team, ''))} ${stats.biggestBlowout.home_score}x${stats.biggestBlowout.away_score} ${shortTeamCode(stats.biggestBlowout.away_team, teamLabel(stats.biggestBlowout.away_team, ''))}` : '-'}</strong>
          <span>Maior goleada</span>
        </article>
        <article className="copa-stat-card">
          <Equal size={22} />
          <AnimatedValue value={stats.drawsCount} />
          <span>Empates ({stats.drawsPercent}%)</span>
        </article>
        <article className="copa-stat-card">
          <Target size={22} />
          <AnimatedValue value={stats.gamesWithGoalsPercent} suffix="%" />
          <span>Jogos com gols</span>
        </article>
      </div>

      <div className="copa-stats-rankings">
        <nav className="copa-stats-rank-tabs">
          <button type="button" className={rankTab === 'scorers' ? 'active' : ''} onClick={() => setRankTab('scorers')}>Mais gols</button>
          <button type="button" className={rankTab === 'attack' ? 'active' : ''} onClick={() => setRankTab('attack')}>Melhor ataque</button>
          <button type="button" className={rankTab === 'defense' ? 'active' : ''} onClick={() => setRankTab('defense')}>Melhor defesa</button>
        </nav>
        <div className="copa-stats-rank-list">
          {rankData.map((team, index) => (
            <article key={team.name} className="copa-stats-rank-item" style={{ '--delay': `${index * 80}ms` }}>
              <span className="rank-pos">{index + 1}</span>
              <b>{shortTeamCode(team.team, team.name)}</b>
              <span className="rank-name">{team.name}</span>
              <div className="rank-bar-track"><div className="rank-bar-fill" style={{ '--width': `${Math.max(barWidth(team), 10)}%` }} /></div>
              <strong>{rankValue(team)} <small>{rankUnit()}</small></strong>
            </article>
          ))}
          {!rankData.length ? <span className="empty-state compact">Mínimo de 2 jogos por seleção para exibir.</span> : null}
        </div>
      </div>

      <div className="copa-stats-insights">
        {stats.topPhase ? (
          <article className="copa-insight-card gold" title="Fase com maior média de gols por jogo."><BarChart3 size={18} /><div><span>Fase mais goleadora</span><strong>{stats.topPhase.name} - {stats.topPhase.avg.toFixed(1)} gols/jogo</strong></div></article>
        ) : null}
        {stats.mostGoalsMatch ? (
          <article className="copa-insight-card green" title="Confronto com a maior soma de gols."><Award size={18} /><div><span>Jogo com mais gols</span><strong>{teamLabel(stats.mostGoalsMatch.home_team, '?')} {stats.mostGoalsMatch.home_score}x{stats.mostGoalsMatch.away_score} {teamLabel(stats.mostGoalsMatch.away_team, '?')} - {stats.mostGoals} gols</strong></div></article>
        ) : null}
        <article className="copa-insight-card blue" title="Maior sequência de jogos finalizados sem empate."><Shield size={18} /><div><span>Sequência sem empates</span><strong>{stats.noDrawStreak} jogos</strong></div></article>
        {stats.mostCommonScore ? (
          <article className="copa-insight-card gold" title="Placar final mais repetido entre os jogos finalizados."><Lightbulb size={18} /><div><span>Placar mais frequente</span><strong>{stats.mostCommonScore} - {stats.mostCommonCount} {stats.mostCommonCount === 1 ? 'vez' : 'vezes'}</strong></div></article>
        ) : null}
        <article className="copa-insight-card green" title="Vitórias de seleções visitantes em jogos finalizados."><ArrowRightLeft size={18} /><div><span>Vitórias do visitante</span><strong>{stats.awayWinsCount} jogos ({stats.finishedCount ? Math.round((stats.awayWinsCount / stats.finishedCount) * 100) : 0}%)</strong></div></article>
        <article className="copa-insight-card blue" title="Seleções já preenchidas na primeira fase do mata-mata."><Trophy size={18} /><div><span>Classificados no mata-mata</span><strong>{stats.classifiedTeamsCount}<small> / {stats.classifiedTeamsTotal || 32}</small></strong></div></article>
      </div>
    </section>
  );
}

export function WorldCupGroupPanel({
  group,
  predictionsByMatch = {},
  draftFor,
  onDraftChange,
  onSave,
  allowPredictions = false,
}) {
  return (
    <article className="copa-group-card official">
      <header>
        <div>
          <p className="eyebrow">Grupo {group.code}</p>
          <h3>{group.display_name}</h3>
        </div>
        <span>{group.matches?.length ?? 0} jogos</span>
      </header>

      <GroupStandingsTable standings={group.standings ?? []} />

      <div className="copa-group-match-list">
        {group.matches?.map((match, index) => (
          <WorldCupMatchCard
            key={match.id || `${group.code}-${index}`}
            match={match}
            prediction={match.id ? predictionsByMatch[match.id] : null}
            draft={draftFor ? draftFor(match) : null}
            onDraftChange={onDraftChange}
            onSave={onSave}
            allowPredictions={allowPredictions}
          />
        ))}
        {!group.matches?.length ? <section className="empty-state compact">Nenhum jogo sincronizado neste grupo ainda.</section> : null}
      </div>
    </article>
  );
}

export function WorldCupMatchCard({
  match,
  prediction = null,
  draft = null,
  onDraftChange,
  onSave,
  allowPredictions = false,
}) {
  const homeName = teamLabel(match.home_team, match.slot_home_label || 'Aguardando classificado');
  const awayName = teamLabel(match.away_team, match.slot_away_label || 'Aguardando classificado');
  const homeCode = shortTeamCode(match.home_team, homeName);
  const awayCode = shortTeamCode(match.away_team, awayName);
  const status = normalizedStatus(match);
  const canPredict = Boolean(allowPredictions && match?.can_predict && match?.id && draft && onDraftChange && onSave);
  const showPredictionForm = Boolean(allowPredictions && draft);
  const resultDetail = resultDetailLabel(match);

  return (
    <article className="copa-match-card group-card">
      <div className="match-card-meta">
        <span>{match.group?.display_name || `Grupo ${match.group_code}`}</span>
        <strong>{formatDate(match.starts_at_br)}</strong>
      </div>
      <div className="match-scoreline">
        <span><b className={teamCodeClassName(homeCode)}>{homeCode}</b>{homeName}</span>
        <strong>{scoreLabel(match)}</strong>
        <span>{awayName}<b className={teamCodeClassName(awayCode)}>{awayCode}</b></span>
      </div>
      {resultDetail ? <span className="match-result-detail">{resultDetail}</span> : null}
      {showPredictionForm ? (
        <div className="prediction-team-form compact">
          <label className="prediction-team-row">
            <span className="prediction-team-info">
              <b className={teamCodeClassName(homeCode)}>{homeCode}</b>
              <span>{homeName}</span>
            </span>
            <input aria-label={`Palpite ${homeName}`} type="number" min="0" max="99" value={draft.predicted_home_score} disabled={!canPredict} onChange={(event) => onDraftChange(match.id, 'predicted_home_score', event.target.value)} />
          </label>
          <label className="prediction-team-row">
            <span className="prediction-team-info">
              <b className={teamCodeClassName(awayCode)}>{awayCode}</b>
              <span>{awayName}</span>
            </span>
            <input aria-label={`Palpite ${awayName}`} type="number" min="0" max="99" value={draft.predicted_away_score} disabled={!canPredict} onChange={(event) => onDraftChange(match.id, 'predicted_away_score', event.target.value)} />
          </label>
          <button type="button" disabled={!canPredict} onClick={() => onSave(match)}>
            {prediction ? <Check size={16} /> : null}
            {prediction ? 'Atualizar' : 'Salvar'}
          </button>
        </div>
      ) : null}
      <div className="match-card-footer">
        <span className={`match-status ${status.state}`}>{status.label}</span>
        {prediction ? <span>Seu palpite: {prediction.predicted_home_score} x {prediction.predicted_away_score}</span> : null}
      </div>
    </article>
  );
}

const BRACKET_PROGRESSION = ['round_of_32', 'round_of_16', 'quarterfinal', 'semifinal'];
const BRACKET_SIDE_ROWS = 24;
const KNOCKOUT_STAGE_KEYS = ['round_of_32', 'round_of_16', 'quarterfinal', 'semifinal', 'third_place', 'final'];

function bracketSlotRow(index, total) {
  if (!total) {
    return 1;
  }

  const step = BRACKET_SIDE_ROWS / total;

  return Math.max(1, Math.round((index * step) + (step / 2)));
}

export function WorldCupBracketView({
  stages = [],
  predictionsByMatch = {},
  draftFor,
  onDraftChange,
  onSave,
  allowPredictions = false,
}) {
  const bracket = useMemo(() => {
    const normalized = stages.map((stage) => {
      const code = normalizeRoundKey(stage.code || stage.display_name) || stage.code || 'unknown_stage';
      return {
        ...stage,
        code,
        label: bracketStageLabel(stage),
        matches: [...(stage.matches ?? [])].sort((a, b) => (a.bracket_order ?? 0) - (b.bracket_order ?? 0)),
      };
    });

    const finalStage = normalized.find((s) => s.code === 'final') || null;
    const thirdPlaceStage = normalized.find((s) => s.code === 'third_place') || null;

    const progression = BRACKET_PROGRESSION
      .map((code) => normalized.find((s) => s.code === code))
      .filter(Boolean);

    const mobileStages = normalized
      .filter((stage) => KNOCKOUT_STAGE_KEYS.includes(stage.code))
      .sort((a, b) => KNOCKOUT_STAGE_KEYS.indexOf(a.code) - KNOCKOUT_STAGE_KEYS.indexOf(b.code));

    const leftCols = [];
    const rightCols = [];

    for (const stage of progression) {
      const mid = Math.ceil(stage.matches.length / 2);
      leftCols.push({ ...stage, matches: stage.matches.slice(0, mid) });
      rightCols.push({ ...stage, matches: stage.matches.slice(mid) });
    }

    return { leftCols, rightCols: [...rightCols].reverse(), finalStage, thirdPlaceStage, mobileStages };
  }, [stages]);

  const [activeMobileStageCode, setActiveMobileStageCode] = useState('');
  const activeMobileStage = bracket.mobileStages.find((stage) => stage.code === activeMobileStageCode) || bracket.mobileStages[0] || null;

  useEffect(() => {
    if (!bracket.mobileStages.length) {
      setActiveMobileStageCode('');
      return;
    }

    if (!bracket.mobileStages.some((stage) => stage.code === activeMobileStageCode)) {
      setActiveMobileStageCode(bracket.mobileStages[0].code);
    }
  }, [activeMobileStageCode, bracket.mobileStages]);

  if (!bracket.leftCols.length && !bracket.finalStage) {
    return <section className="empty-state compact">Nenhum chaveamento sincronizado ainda.</section>;
  }

  function renderCard(match, stage, index) {
    return (
      <WorldCupBracketMatchCard
        key={match.id || `${stage.code}-${index}`}
        match={match}
        stage={stage}
        prediction={match.id ? predictionsByMatch[match.id] : null}
        draft={draftFor ? draftFor(match) : null}
        onDraftChange={onDraftChange}
        onSave={onSave}
        allowPredictions={allowPredictions}
      />
    );
  }

  function renderRound(col, prefix) {
    return (
      <div key={`${prefix}-${col.code}`} className={`bracket-round ${col.code}`} data-side={prefix}>
        <span className="bracket-round-label">{col.label}</span>
        <div className="bracket-round-matches" style={{ '--round-slots': BRACKET_SIDE_ROWS }}>
          {col.matches.map((m, i) => (
            <div
              key={m.id || `${col.code}-${prefix}-${i}`}
              className="bracket-match-slot"
              style={{ '--slot-row': bracketSlotRow(i, col.matches.length) }}
            >
              {renderCard(m, col, i)}
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <section
      className="copa-bracket-responsive"
      aria-label="Chaveamento da Copa"
      data-variant={allowPredictions ? 'interactive' : 'readonly'}
    >
      <div className="bracket-mobile-panel" aria-label="Chaveamento em lista por fase">
        <div className="bracket-mobile-panel-header">
          <span><List size={16} /> Ver lista</span>
          <small>Toque em uma fase para acompanhar os jogos sem arrastar a tabela completa.</small>
        </div>
        <div className="bracket-mobile-tabs" role="tablist" aria-label="Fases do chaveamento">
          {bracket.mobileStages.map((stage) => (
            <button
              key={stage.code}
              type="button"
              role="tab"
              aria-selected={activeMobileStage?.code === stage.code}
              className={activeMobileStage?.code === stage.code ? 'active' : undefined}
              onClick={() => setActiveMobileStageCode(stage.code)}
            >
              {stage.label}
            </button>
          ))}
        </div>
        {activeMobileStage ? (
          <div className="bracket-mobile-match-list" role="tabpanel" aria-label={activeMobileStage.label}>
            {activeMobileStage.matches.map((match, index) => renderCard(match, activeMobileStage, index))}
          </div>
        ) : null}
      </div>
      <p className="bracket-scroll-hint">Deslize para ver o chaveamento completo</p>
      <div
        className="copa-bracket-board"
        style={{ '--left-cols': bracket.leftCols.length || 1, '--right-cols': bracket.rightCols.length || 1 }}
      >
        <div className="bracket-lane left">
          {bracket.leftCols.map((col) => renderRound(col, 'left'))}
        </div>

        <div className="bracket-center">
          {bracket.finalStage ? (
            <div className="bracket-round final">
              <span className="bracket-round-label"><Trophy size={14} /> {bracket.finalStage.label}</span>
              <div className="bracket-round-matches">
                {bracket.finalStage.matches.map((m, i) => renderCard(m, bracket.finalStage, i))}
              </div>
            </div>
          ) : null}
          {bracket.thirdPlaceStage ? (
            <div className="bracket-round third_place">
              <span className="bracket-round-label">{bracket.thirdPlaceStage.label}</span>
              <div className="bracket-round-matches">
                {bracket.thirdPlaceStage.matches.map((m, i) => renderCard(m, bracket.thirdPlaceStage, i))}
              </div>
            </div>
          ) : null}
        </div>

        <div className="bracket-lane right">
          {bracket.rightCols.map((col) => renderRound(col, 'right'))}
        </div>
      </div>
    </section>
  );
}

export function WorldCupBracketMatchCard({
  match,
  stage = null,
  prediction = null,
  draft = null,
  onDraftChange,
  onSave,
  allowPredictions = false,
}) {
  const homeName = bracketTeamLabel(match, 'home');
  const awayName = bracketTeamLabel(match, 'away');
  const homeFlag = teamFlagUrl(match.home_team);
  const awayFlag = teamFlagUrl(match.away_team);
  const homeCode = shortTeamCode(match.home_team, homeName);
  const awayCode = shortTeamCode(match.away_team, awayName);
  const { hasScore, homeIsWinner, awayIsWinner } = matchResultSides(match);
  const status = normalizedStatus(match);
  const canPredict = Boolean(allowPredictions && match?.can_predict && match?.id && draft && onDraftChange && onSave);
  const showPredictionForm = Boolean(allowPredictions && draft);
  const resultDetail = resultDetailLabel(match);
  const predictedHomeScore = Number(draft?.predicted_home_score ?? 0);
  const predictedAwayScore = Number(draft?.predicted_away_score ?? 0);
  const needsWinnerPick = Boolean(
    showPredictionForm
    && predictedHomeScore === predictedAwayScore
    && KNOCKOUT_STAGE_KEYS.includes(bracketStageKey(stage, match))
  );
  const predictedWinnerSide = draft?.predicted_winner_side ?? null;
  const canSavePrediction = canPredict && (!needsWinnerPick || ['home', 'away'].includes(predictedWinnerSide));

  return (
    <article className={`copa-match-card bracket-card${hasScore ? ' decided' : ''}`} data-match-state={status.state}>
      <div className="bracket-card-meta">
        <span>{bracketMatchRoundLabel(match, stage)}</span>
        <strong>{formatDate(match.starts_at_br)}</strong>
      </div>
      <div className="bracket-team-list">
        <div className={`bracket-team-row${homeIsWinner ? ' winner' : ''}${hasScore && !homeIsWinner ? ' eliminated' : ''}${showPredictionForm ? ' predicting' : ''}`} title={homeName}>
          <span className="bracket-team-chip">
            {homeFlag ? <img src={homeFlag} alt="" loading="lazy" /> : <span className="bracket-flag-fallback" aria-hidden="true" />}
            <b className={teamCodeClassName(homeCode)}>{homeCode}</b>
          </span>
          <span className="bracket-team-name">{homeName}</span>
          {showPredictionForm ? (
            <input className="bracket-team-prediction-input" aria-label={`Palpite ${homeName}`} type="number" min="0" max="99" value={draft.predicted_home_score} disabled={!canPredict} onChange={(event) => onDraftChange(match.id, 'predicted_home_score', event.target.value)} />
          ) : null}
          <strong>{match.home_score ?? '-'}</strong>
        </div>
        <div className={`bracket-team-row${awayIsWinner ? ' winner' : ''}${hasScore && !awayIsWinner ? ' eliminated' : ''}${showPredictionForm ? ' predicting' : ''}`} title={awayName}>
          <span className="bracket-team-chip">
            {awayFlag ? <img src={awayFlag} alt="" loading="lazy" /> : <span className="bracket-flag-fallback" aria-hidden="true" />}
            <b className={teamCodeClassName(awayCode)}>{awayCode}</b>
          </span>
          <span className="bracket-team-name">{awayName}</span>
          {showPredictionForm ? (
            <input className="bracket-team-prediction-input" aria-label={`Palpite ${awayName}`} type="number" min="0" max="99" value={draft.predicted_away_score} disabled={!canPredict} onChange={(event) => onDraftChange(match.id, 'predicted_away_score', event.target.value)} />
          ) : null}
          <strong>{match.away_score ?? '-'}</strong>
        </div>
      </div>
      {resultDetail ? <span className="bracket-penalty-label">{resultDetail}</span> : null}
      {needsWinnerPick ? (
        <div className="bracket-winner-choice-row" role="group" aria-label="Classificado no desempate">
          <button
            type="button"
            className={predictedWinnerSide === 'home' ? 'active' : ''}
            disabled={!canPredict}
            onClick={() => onDraftChange(match.id, 'predicted_winner_side', 'home')}
          >
            {homeCode}
          </button>
          <span>classificado</span>
          <button
            type="button"
            className={predictedWinnerSide === 'away' ? 'active' : ''}
            disabled={!canPredict}
            onClick={() => onDraftChange(match.id, 'predicted_winner_side', 'away')}
          >
            {awayCode}
          </button>
        </div>
      ) : null}
      {showPredictionForm ? (
        <div className="bracket-prediction-row save-only">
          <button type="button" disabled={!canSavePrediction} onClick={() => onSave(match)}>
            {prediction ? <Check size={14} /> : null}
            {prediction ? 'Atualizar' : 'Salvar'}
          </button>
        </div>
      ) : null}
      <div className="match-card-footer compact">
        <span className={`match-status ${status.state}`}>{status.label}</span>
        {prediction ? <span>{prediction.predicted_home_score} x {prediction.predicted_away_score}</span> : <Shield size={14} aria-hidden="true" />}
      </div>
      {bracketStageKey(stage, match) === 'final' ? (
        <div className="bracket-stage-mark" aria-hidden="true"><Trophy size={15} /></div>
      ) : null}
    </article>
  );
}
