import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
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
      .catch((requestError) => setError(requestError.message || 'Nao foi possivel carregar ligas publicas.'))
      .finally(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return <section className="content-loading">Carregando ligas publicas...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  return (
    <section className="content-section">
      {leagues.length === 0 ? (
        <EmptyState
          title="Nenhuma liga publica aberta"
          description="Quando gestores abrirem ligas publicas, elas aparecerao aqui para todos os usuarios autenticados."
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
