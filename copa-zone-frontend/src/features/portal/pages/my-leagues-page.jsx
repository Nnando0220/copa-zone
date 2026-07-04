import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { getRequestErrorMessage } from '../../../api/errors';
import { EmptyState } from '../components/empty-state';
import { LeagueCard } from '../components/league-card';
import { portalService } from '../services/portal-service';

export function MyLeaguesPage() {
  const navigate = useNavigate();
  const [leagues, setLeagues] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    portalService
      .myLeagues()
      .then((payload) => setLeagues(payload.data ?? []))
      .catch((requestError) => setError(requestError.message || 'NÃ£o foi possÃ­vel carregar suas ligas.'))
      .finally(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return <section className="content-loading">Carregando suas ligas...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  return (
    <section className="content-section">
      {leagues.length === 0 ? (
        <EmptyState
          title="VocÃª ainda nÃ£o estÃ¡ inscrito em ligas"
          description="Crie sua primeira liga e convide seus amigos para o bolÃ£o."
          action={<Link to="/ligas/criar">Criar liga</Link>}
        />
      ) : (
        <div className="league-grid">
          {leagues.map((league) => (
            <LeagueCard
              key={league.id}
              league={league}
              onAction={() => navigate(`/ligas/${league.id}`)}
            />
          ))}
        </div>
      )}
    </section>
  );
}


