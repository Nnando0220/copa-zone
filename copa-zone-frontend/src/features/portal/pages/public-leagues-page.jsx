import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { getRequestErrorMessage } from '../../../api/errors';
import { EmptyState } from '../components/empty-state';
import { LeagueCard } from '../components/league-card';
import { portalService } from '../services/portal-service';

export function PublicLeaguesPage() {
  const navigate = useNavigate();
  const [leagues, setLeagues] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    portalService
      .publicLeagues()
      .then((payload) => setLeagues(payload.data ?? []))
      .catch((requestError) => setError(requestError.message || 'NÃ£o foi possÃ­vel carregar ligas pÃºblicas.'))
      .finally(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return <section className="content-loading">Carregando ligas pÃºblicas...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  return (
    <section className="content-section">
      {leagues.length === 0 ? (
        <EmptyState
          title="Nenhuma liga pÃºblica aberta"
          description="Quando novas ligas pÃºblicas estiverem disponÃ­veis, elas aparecerÃ£o aqui para vocÃª explorar."
        />
      ) : (
        <div className="league-grid">
          {leagues.map((league) => (
            <LeagueCard
              key={league.id}
              league={league}
              actionLabel="Ver liga"
              onAction={() => navigate(`/ligas/${league.id}`)}
            />
          ))}
        </div>
      )}
    </section>
  );
}


