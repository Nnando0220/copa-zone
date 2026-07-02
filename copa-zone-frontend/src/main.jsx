import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router';
import { Toaster, toast } from 'sonner';
import { ShieldCheck, Trophy, Users } from 'lucide-react';
import { LoginPage } from './features/auth/pages/login-page';
import { RegisterPage } from './features/auth/pages/register-page';
import { authService } from './features/auth/services/auth-service';
import { DashboardPage } from './features/dashboard/pages/dashboard-page';
import { AuthenticatedShell } from './features/portal/components/authenticated-shell';
import { CreateLeaguePage } from './features/portal/pages/create-league-page';
import { JoinLeaguePage } from './features/portal/pages/join-league-page';
import { LeagueDetailPage } from './features/portal/pages/league-detail-page';
import { LeagueWorldCupPage } from './features/portal/pages/league-world-cup-page';
import { MyLeaguesPage } from './features/portal/pages/my-leagues-page';
import { PublicLeaguesPage } from './features/portal/pages/public-leagues-page';
import { WorldCupDataPage } from './features/portal/pages/world-cup-data-page';
import './styles.css';

function GuardScreen({ title = 'Validando sessão' }) {
  return (
    <main className="auth-shell">
      <section className="auth-panel compact">
        <p className="eyebrow">CopaZone</p>
        <h1>{title}</h1>
      </section>
    </main>
  );
}

function LandingPage({ user }) {
  return (
    <main className="landing-page">
      <section className="landing-hero">
        <div>
          <p className="eyebrow">CopaZone</p>
          <h1>Bolao recreativo da Copa do Mundo</h1>
          <p>
            Entre, crie sua liga e acompanhe seus palpites com a API do backend como fonte oficial da verdade.
          </p>
          <div className="landing-actions">
            {user ? (
              <Link to="/dashboard">Abrir painel</Link>
            ) : (
              <>
                <Link to="/login">Entrar</Link>
                <Link to="/cadastro" className="secondary">
                  Criar conta
                </Link>
              </>
            )}
          </div>
        </div>

        <div className="landing-summary">
          {[
            { icon: ShieldCheck, title: 'Cookie HttpOnly', desc: 'Sessao segura controlada pelo backend.' },
            { icon: Users, title: 'Ligas', desc: 'Proxima etapa do fluxo do MVP.' },
            { icon: Trophy, title: 'Copa', desc: 'Escopo exclusivo da Copa do Mundo.' },
          ].map((item) => {
            const Icon = item.icon;

            return (
              <article key={item.title}>
                <Icon size={24} />
                <strong>{item.title}</strong>
                <span>{item.desc}</span>
              </article>
            );
          })}
        </div>
      </section>
    </main>
  );
}

function RequireAuth({ user, isBooting, children }) {
  const location = useLocation();

  if (isBooting) {
    return <GuardScreen />;
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return children;
}

function RedirectWhenAuthenticated({ user, children }) {
  if (user) {
    return <Navigate to="/dashboard" replace />;
  }

  return children;
}

function ProtectedShellRoute({ user, isBooting, onLogout, isLoggingOut, title, subtitle, children }) {
  return (
    <RequireAuth user={user} isBooting={isBooting}>
      <AuthenticatedShell
        user={user}
        onLogout={onLogout}
        isLoggingOut={isLoggingOut}
        title={title}
        subtitle={subtitle}
      >
        {children}
      </AuthenticatedShell>
    </RequireAuth>
  );
}

function AppRoutes() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [isBooting, setIsBooting] = useState(true);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  useEffect(() => {
    authService
      .me()
      .then((payload) => setUser(payload.data.user))
      .catch(() => setUser(null))
      .finally(() => setIsBooting(false));
  }, []);

  async function logout() {
    setIsLoggingOut(true);

    try {
      await authService.logout();
      setUser(null);
      toast.success('Sessao encerrada com sucesso.');
      navigate('/login', { replace: true });
    } catch (error) {
      toast.error(error.message || 'Nao foi possivel sair agora.');
    } finally {
      setIsLoggingOut(false);
    }
  }

  if (isBooting) {
    return <GuardScreen />;
  }

  return (
    <>
      <Routes>
        <Route path="/" element={<LandingPage user={user} />} />
        <Route
          path="/login"
          element={
            <RedirectWhenAuthenticated user={user}>
              <LoginPage onAuthenticated={setUser} />
            </RedirectWhenAuthenticated>
          }
        />
        <Route
          path="/cadastro"
          element={
            <RedirectWhenAuthenticated user={user}>
              <RegisterPage onAuthenticated={setUser} />
            </RedirectWhenAuthenticated>
          }
        />
        <Route
          path="/dashboard"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Dashboard"
              subtitle="Acompanhe suas ligas, atividades e oportunidades publicas da CopaZone."
            >
              <DashboardPage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/ligas/minhas"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Minhas ligas"
              subtitle="Ligas publicas e privadas aparecem aqui somente quando voce participa delas."
            >
              <MyLeaguesPage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/ligas/publicas"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Ligas publicas"
              subtitle="Explore ligas abertas. Ligas privadas permanecem escondidas ate voce estar inscrito."
            >
              <PublicLeaguesPage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/ligas/entrar"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Entrar em liga"
              subtitle="Cole o codigo de convite para acessar uma liga privada."
            >
              <JoinLeaguePage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/copa/dados"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Dados da Copa"
              subtitle="Visualize os dados sincronizados da OpenLigaDB antes de liberar os palpites."
            >
              <WorldCupDataPage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/ligas/entrar-codigo"
          element={<Navigate to="/ligas/entrar" replace />}
        />
        <Route
          path="/ligas/:leagueId/copa"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Modo Copa"
              subtitle="Fases, jogos, palpites por placar e ranking da liga."
            >
              <LeagueWorldCupPage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/ligas/:leagueId"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Liga"
              subtitle="Acompanhe os dados iniciais da liga. Palpites entram na proxima fase."
            >
              <LeagueDetailPage />
            </ProtectedShellRoute>
          }
        />
        <Route
          path="/ligas/criar"
          element={
            <ProtectedShellRoute
              user={user}
              isBooting={isBooting}
              onLogout={logout}
              isLoggingOut={isLoggingOut}
              title="Criar liga"
              subtitle="Configure uma liga publica ou privada para disputar a Copa com seus amigos."
            >
              <CreateLeaguePage />
            </ProtectedShellRoute>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
      <Toaster richColors position="top-right" />
    </>
  );
}

function App() {
  return (
    <BrowserRouter>
      <AppRoutes />
    </BrowserRouter>
  );
}

createRoot(document.getElementById('root')).render(<App />);
