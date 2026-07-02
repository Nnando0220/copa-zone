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
          <h1>Bolão recreativo da Copa do Mundo</h1>
          <p>
            Entre, crie sua liga e acompanhe a Copa em tempo real com sua turma.
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
            { icon: ShieldCheck, title: 'Sessão segura', desc: 'Seus dados protegidos a cada acesso.' },
            { icon: Users, title: 'Ligas', desc: 'Crie ou entre em ligas com seus amigos.' },
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

      <section className="landing-preview-section" aria-labelledby="landing-preview-title">
        <div className="landing-preview-heading">
          <p className="eyebrow">Por dentro da CopaZone</p>
          <h2 id="landing-preview-title">Veja o que aparece depois do acesso</h2>
          <p>Organize sua liga, acompanhe o chaveamento e compare os palpites da turma em uma experiência feita para a Copa.</p>
        </div>

        <div className="landing-preview-grid">
          <article className="landing-preview-card dashboard-shot">
            <div className="preview-window-bar">
              <span />
              <span />
              <span />
            </div>
            <div className="preview-shot-header">
              <small>Início</small>
              <strong>Suas ligas em destaque</strong>
            </div>
            <div className="preview-metrics">
              <span><b>3</b>Ligas</span>
              <span><b>24</b>Amigos</span>
              <span><b>12</b>Grupos</span>
            </div>
            <div className="preview-league-row">
              <span>Bolão da Firma</span>
              <strong>Modo Copa</strong>
            </div>
            <div className="preview-league-row">
              <span>Família 2026</span>
              <strong>Convite</strong>
            </div>
          </article>

          <article className="landing-preview-card bracket-shot">
            <div className="preview-window-bar">
              <span />
              <span />
              <span />
            </div>
            <div className="preview-shot-header">
              <small>Chaveamento</small>
              <strong>Palpites por confronto</strong>
            </div>
            <div className="preview-bracket">
              <div>
                <span>BRA 2</span>
                <span>JAP 1</span>
              </div>
              <div>
                <span>FRA 1</span>
                <span>ARG 1</span>
              </div>
              <div className="final">
                <span>BRA</span>
                <span>ARG</span>
              </div>
            </div>
            <div className="preview-prediction">
              <span>Seu palpite</span>
              <strong>Brasil 2 x 1 Japão</strong>
            </div>
          </article>

          <article className="landing-preview-card ranking-shot">
            <div className="preview-window-bar">
              <span />
              <span />
              <span />
            </div>
            <div className="preview-shot-header">
              <small>Ranking</small>
              <strong>Disputa ponto a ponto</strong>
            </div>
            <ol className="preview-ranking">
              <li><b>1</b><span>Fernando</span><strong>42 pts</strong></li>
              <li><b>2</b><span>Ana</span><strong>39 pts</strong></li>
              <li><b>3</b><span>Lucas</span><strong>34 pts</strong></li>
            </ol>
            <div className="preview-live-pill">Atualização ao vivo</div>
          </article>
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
      toast.success('Sessão encerrada com sucesso.');
      navigate('/login', { replace: true });
    } catch (error) {
      toast.error(error.message || 'Não foi possível sair agora.');
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
              title="Início"
              subtitle="Acompanhe suas ligas, atividades e ligas públicas da CopaZone."
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
              subtitle="Ligas públicas e privadas aparecem aqui quando você participa delas."
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
              title="Ligas públicas"
              subtitle="Explore ligas abertas. Ligas privadas aparecem para você depois da inscrição."
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
              subtitle="Cole o código de convite para acessar uma liga privada."
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
              subtitle="Veja o calendário de jogos, grupos e chaveamento da Copa do Mundo."
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
              subtitle="Veja os detalhes e partidas da sua liga."
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
              subtitle="Configure uma liga pública ou privada para disputar a Copa com seus amigos."
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
