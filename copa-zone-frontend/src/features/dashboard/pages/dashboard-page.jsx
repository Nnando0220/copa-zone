import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { Activity, Globe2, LockKeyhole, Users } from 'lucide-react';
import { EmptyState } from '../../portal/components/empty-state';
import { LeagueCard } from '../../portal/components/league-card';
import { portalService } from '../../portal/services/portal-service';

export function DashboardPage() {
  const navigate = useNavigate();
  const [dashboard, setDashboard] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    portalService
      .dashboard()
      .then((payload) => setDashboard(payload.data))
      .catch((requestError) => setError(requestError.message || 'Nao foi possivel carregar o dashboard.'))
      .finally(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return <section className="content-loading">Carregando suas ligas e atividades...</section>;
  }

  if (error) {
    return <section className="content-error">{error}</section>;
  }

  const summary = dashboard?.summary ?? {};
  const myLeagues = dashboard?.my_leagues ?? [];
  const publicLeagues = dashboard?.public_leagues ?? [];
  const activity = dashboard?.activity ?? [];

  return (
    <div className="dashboard-content">
      <section className="metric-grid">
        <article>
          <LockKeyhole size={21} />
          <span>Minhas ligas</span>
          <strong>{summary.my_leagues_count ?? 0}</strong>
        </article>
        <article>
          <Users size={21} />
          <span>Privadas inscritas</span>
          <strong>{summary.private_leagues_count ?? 0}</strong>
        </article>
        <article>
          <Globe2 size={21} />
          <span>Publicas abertas</span>
          <strong>{summary.public_leagues_available_count ?? 0}</strong>
        </article>
      </section>

      <section className="dashboard-band">
        <div>
          <p className="eyebrow">Agora nas suas ligas</p>
          <h2>{summary.activity_label}</h2>
        </div>

        <div className="activity-list">
          {activity.map((item) => (
            <article key={`${item.type}-${item.title}`}>
              <Activity size={18} />
              <div>
                <strong>{item.title}</strong>
                <span>{item.description}</span>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="content-section">
        <div className="section-header">
          <div>
            <p className="eyebrow">Minhas ligas</p>
            <h2>Ligas onde voce participa</h2>
          </div>
          <Link to="/ligas/minhas">Ver todas</Link>
        </div>

        {myLeagues.length === 0 ? (
          <EmptyState
            title="Nenhuma liga inscrita ainda"
            description="Crie sua primeira liga para convidar amigos, configurar participantes e acompanhar os palpites da Copa."
            action={<Link to="/ligas/criar">Criar liga</Link>}
          />
        ) : (
          <div className="league-grid compact">
            {myLeagues.map((league) => (
              <LeagueCard
                key={league.id}
                league={league}
                onAction={() => navigate(`/ligas/${league.id}`)}
              />
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
