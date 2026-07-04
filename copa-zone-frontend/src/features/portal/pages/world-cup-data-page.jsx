import { useEffect, useMemo, useState } from 'react';
import { getEcho, leaveChannel } from '../../../realtime/echo';
import { MatchList } from '../components/match-list';
import { MatchPeriodFilter } from '../components/match-period-filter';
import { WorldCupBracketView, WorldCupGroupPanel, WorldCupStatsPanel } from '../components/world-cup-structure';
import { portalService } from '../services/portal-service';

const tabs = [
  { id: 'summary', label: 'Estatísticas' },
  { id: 'groups', label: 'Fases' },
  { id: 'bracket', label: 'Chaveamento' },
  { id: 'matches', label: 'Partidas' },
];

export function WorldCupDataPage() {
  const [diagnostic, setDiagnostic] = useState(null);
  const [groups, setGroups] = useState([]);
  const [bracketStages, setBracketStages] = useState([]);
  const [matches, setMatches] = useState([]);
  const [activeTab, setActiveTab] = useState('summary');
  const [activeGroupCode, setActiveGroupCode] = useState('');
  const [period, setPeriod] = useState('all');
  const [matchesTotal, setMatchesTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMatches, setIsLoadingMatches] = useState(false);
  const [error, setError] = useState('');
  const [refreshNonce, setRefreshNonce] = useState(0);

  const selectedGroup = useMemo(
    () => groups.find((group) => group.code === activeGroupCode) ?? groups[0] ?? null,
    [activeGroupCode, groups],
  );

  useEffect(() => {
    let isMounted = true;

    function loadDiagnostic() {
      setError('');

      Promise.all([
        portalService.worldCup(),
        portalService.worldCupGroups(),
        portalService.worldCupBracket(),
      ])
        .then(([worldCupPayload, groupsPayload, bracketPayload]) => {
          if (!isMounted) {
            return;
          }

          setDiagnostic({
            edition: worldCupPayload.data.edition,
            meta: worldCupPayload.meta,
          });
          setGroups(groupsPayload.data.groups ?? []);
          setBracketStages(bracketPayload.data.bracket?.stages ?? []);
          setError('');
        })
        .catch((requestError) => {
          if (isMounted) {
            setError(requestError.message || 'Não foi possível carregar os dados da Copa.');
          }
        })
        .finally(() => {
          if (isMounted) {
            setIsLoading(false);
          }
        });
    }

    loadDiagnostic();

    return () => {
      isMounted = false;
    };
  }, [refreshNonce]);

  useEffect(() => {
    if (!groups.length) {
      return;
    }

    if (!activeGroupCode || !groups.some((group) => group.code === activeGroupCode)) {
      setActiveGroupCode(groups[0].code);
    }
  }, [activeGroupCode, groups]);

  useEffect(() => {
    let isMounted = true;

    function loadMatches() {
      setIsLoadingMatches(true);
      setError('');

      portalService.worldCupMatches({ period })
        .then((matchesPayload) => {
          if (!isMounted) {
            return;
          }

          setMatches(matchesPayload.data.matches ?? []);
          setMatchesTotal(matchesPayload.meta?.total ?? 0);
          setError('');
        })
        .catch((requestError) => {
          if (isMounted) {
            setError(requestError.message || 'Não foi possível carregar as partidas da Copa.');
          }
        })
        .finally(() => {
          if (isMounted) {
            setIsLoadingMatches(false);
          }
        });
    }

    loadMatches();

    return () => {
      isMounted = false;
    };
  }, [period, refreshNonce]);

  useEffect(() => {
    const echo = getEcho();

    if (!echo) {
      return undefined;
    }

    const reload = () => setRefreshNonce((current) => current + 1);

    echo.channel('world-cup')
      .listen('.world_cup.match.updated', reload)
      .listen('.world_cup.match.finished', reload)
      .listen('.world_cup.stage.updated', reload);

    return () => leaveChannel('world-cup');
  }, []);

  if (isLoading) {
    return <section className="content-loading">Carregando dados da Copa...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  const meta = diagnostic?.meta ?? {};
  const edition = diagnostic?.edition;

  return (
    <section className="world-cup-data-page">
      <div className="content-section world-cup-overview">
        <div className="section-header">
          <div>
            <p className="eyebrow">Copa do Mundo 2026</p>
            <h2>{edition ? `${edition.name} ${edition.season}` : 'Dados ainda não atualizados'}</h2>
          </div>
          <span className="diagnostic-pill">{meta.groups_count ?? 0} grupos/fases</span>
        </div>
        <nav className="league-copa-tabs" aria-label="Navegacao dos dados da Copa">
          {tabs.map((tab) => (
            <button key={tab.id} type="button" className={activeTab === tab.id ? 'active' : ''} onClick={() => setActiveTab(tab.id)}>
              {tab.label}
            </button>
          ))}
        </nav>

        {activeTab === 'summary' && (
          <WorldCupStatsPanel groups={groups} bracketStages={bracketStages} />
        )}

        {activeTab === 'groups' && (
          <div className="copa-phase-section">
            <div className="section-header">
              <div><p className="eyebrow">Primeira fase</p><h2>Grupos e fases oficiais</h2></div>
              <span className="diagnostic-pill">{groups.length} grupos</span>
            </div>
            <div className="copa-group-tabs group-submenu" role="tablist" aria-label="Grupos da Copa">
              {groups.map((group) => (
                <button key={group.code} type="button" className={group.code === selectedGroup?.code ? 'active' : ''} onClick={() => setActiveGroupCode(group.code)}>
                  {group.code}
                </button>
              ))}
            </div>
            {selectedGroup ? <WorldCupGroupPanel group={selectedGroup} /> : <section className="empty-state">Nenhum grupo disponível ainda.</section>}
          </div>
        )}

        {activeTab === 'bracket' && (
          <section className="copa-bracket-shell">
            <div className="section-header">
              <div><p className="eyebrow">Mata-mata</p><h2>Chaveamento da Copa</h2></div>
              <span className="diagnostic-pill">{bracketStages.reduce((total, stage) => total + (stage.matches?.length ?? 0), 0)} confrontos</span>
            </div>
            <WorldCupBracketView stages={bracketStages} />
          </section>
        )}

        {activeTab === 'matches' && (
          <>
            <MatchPeriodFilter value={period} onChange={setPeriod} total={matchesTotal} />
            {isLoadingMatches ? <section className="content-loading compact">Atualizando partidas...</section> : <MatchList matches={matches} />}
          </>
        )}
      </div>
    </section>
  );
}
