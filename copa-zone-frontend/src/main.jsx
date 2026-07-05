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

const SITE_URL = (import.meta.env.VITE_SITE_URL || 'https://copazone.app').replace(/\/+$/, '');
const DEFAULT_IMAGE = `${SITE_URL}/brand/copa-zone-logo.png`;
const DEFAULT_DESCRIPTION =
  'Crie uma liga recreativa da Copa do Mundo 2026, convide amigos, registre palpites e acompanhe o ranking em um só lugar.';

const publicPages = {
  '/como-funciona': {
    eyebrow: 'Como funciona',
    title: 'Como funciona o CopaZone: ligas e palpites da Copa 2026',
    description:
      'Entenda como criar uma liga, convidar amigos, registrar palpites e acompanhar a disputa da Copa do Mundo 2026.',
    intro:
      'O CopaZone organiza a disputa em um fluxo simples: você cria ou entra em uma liga, faz seus palpites e acompanha a pontuação com seus amigos.',
    sections: [
      { title: 'Crie sua liga', text: 'Monte uma liga para reunir amigos, família ou colegas em uma disputa simples durante a Copa.' },
      { title: 'Convide participantes', text: 'Compartilhe o acesso da liga com quem vai participar e mantenha todos no mesmo espaço.' },
      { title: 'Acompanhe a disputa', text: 'Veja palpites, resultados e pontuação em uma experiência pensada para ser clara do início ao fim.' },
    ],
  },
  '/copa-do-mundo-2026': {
    eyebrow: 'Copa do Mundo 2026',
    title: 'Copa do Mundo 2026: grupos, jogos e ligas',
    description:
      'Use o CopaZone para organizar ligas de palpites da Copa do Mundo 2026 com amigos.',
    intro:
      'O CopaZone foi pensado para transformar os jogos da Copa do Mundo 2026 em uma disputa leve entre amigos.',
    sections: [
      { title: 'Jogos da Copa', text: 'Acompanhe os confrontos da competição dentro das ligas em que você participa.' },
      { title: 'Palpites organizados', text: 'Registre seus palpites em um lugar único, sem depender de planilhas ou mensagens espalhadas.' },
      { title: 'Disputa entre amigos', text: 'Acompanhe a pontuação da liga e veja quem está melhor na rodada.' },
    ],
  },
  '/regras': {
    eyebrow: 'Regras',
    title: 'Regras de pontuação e palpites',
    description:
      'Conheça as regras gerais para participar de ligas, enviar palpites e acompanhar a pontuação no CopaZone.',
    intro:
      'As regras ajudam a manter a disputa simples, justa e fácil de acompanhar para todos os participantes.',
    sections: [
      { title: 'Palpites antes do jogo', text: 'Envie seus palpites dentro do prazo indicado para que todos participem em igualdade.' },
      { title: 'Ligas com convite', text: 'Cada liga reúne seus próprios participantes, mantendo a disputa no grupo certo.' },
      { title: 'Pontuação da liga', text: 'A classificação mostra o desempenho dos participantes conforme os jogos acontecem.' },
    ],
  },
  '/faq': {
    eyebrow: 'FAQ',
    title: 'Dúvidas sobre ligas e palpites da Copa 2026',
    description:
      'Tire dúvidas sobre cadastro, ligas, palpites e funcionamento geral do CopaZone.',
    intro:
      'Reunimos as principais dúvidas para ajudar novos participantes a entenderem rapidamente como usar a CopaZone.',
    sections: [
      { title: 'Preciso criar conta?', text: 'Sim. O cadastro permite guardar suas ligas, seus palpites e sua pontuação.' },
      { title: 'Posso convidar amigos?', text: 'Sim. A ideia do CopaZone é reunir pessoas em ligas para acompanhar a Copa juntas.' },
      { title: 'A CopaZone é oficial da FIFA?', text: 'Não. A CopaZone é um aplicativo recreativo independente para ligas de palpites entre amigos.' },
    ],
  },
  '/sobre-o-copazone': {
    eyebrow: 'Sobre o CopaZone',
    title: 'Sobre o CopaZone',
    description:
      'Conheça o CopaZone, uma plataforma recreativa para criar ligas de palpites da Copa do Mundo 2026 entre amigos.',
    intro:
      'O CopaZone nasceu para transformar a Copa do Mundo em uma disputa leve, organizada e fácil de acompanhar entre grupos de amigos.',
    sections: [
      { title: 'Produto recreativo', text: 'A plataforma organiza palpites, ligas e ranking sem envolver apostas, odds ou movimentação financeira.' },
      { title: 'Foco em grupos', text: 'Cada liga reúne participantes em um espaço próprio para acompanhar jogos, palpites e pontuação.' },
      { title: 'Identidade clara', text: 'A marca CopaZone representa uma experiência independente para quem quer viver a Copa com amigos.' },
    ],
  },
};

function upsertMeta(selector, createElement, attributes) {
  let element = document.head.querySelector(selector);

  if (!element) {
    element = createElement();
    document.head.appendChild(element);
  }

  Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
}

function removeElement(selector) {
  document.head.querySelector(selector)?.remove();
}

function upsertJsonLd(id, data) {
  let element = document.head.querySelector(`script[data-seo-jsonld="${id}"]`);

  if (!element) {
    element = document.createElement('script');
    element.setAttribute('type', 'application/ld+json');
    element.setAttribute('data-seo-jsonld', id);
    document.head.appendChild(element);
  }

  element.textContent = JSON.stringify(data);
}

function removeJsonLd(id) {
  document.head.querySelector(`script[data-seo-jsonld="${id}"]`)?.remove();
}

function breadcrumbStructuredData(page, path) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      {
        '@type': 'ListItem',
        position: 1,
        name: 'Início',
        item: `${SITE_URL}/`,
      },
      {
        '@type': 'ListItem',
        position: 2,
        name: page.title,
        item: `${SITE_URL}${path}`,
      },
    ],
  };
}

function Seo({ title, description = DEFAULT_DESCRIPTION, path, robots = 'index,follow', structuredData }) {
  const location = useLocation();

  useEffect(() => {
    const currentPath = path || location.pathname;
    const canonicalUrl = currentPath === '/' ? `${SITE_URL}/` : `${SITE_URL}${currentPath}`;
    const fullTitle = title ? `${title} | CopaZone` : 'CopaZone | Liga de Palpites da Copa do Mundo 2026';
    const isIndexable = !robots.toLowerCase().includes('noindex');

    document.title = fullTitle;
    upsertMeta('meta[name="description"]', () => document.createElement('meta'), { name: 'description', content: description });
    upsertMeta('meta[name="robots"]', () => document.createElement('meta'), { name: 'robots', content: robots });
    if (isIndexable) {
      upsertMeta('link[rel="canonical"]', () => {
        const link = document.createElement('link');
        link.setAttribute('rel', 'canonical');
        return link;
      }, { href: canonicalUrl });
      upsertMeta('meta[property="og:url"]', () => document.createElement('meta'), { property: 'og:url', content: canonicalUrl });
    } else {
      removeElement('link[rel="canonical"]');
      removeElement('meta[property="og:url"]');
    }
    upsertMeta('meta[property="og:title"]', () => document.createElement('meta'), { property: 'og:title', content: fullTitle });
    upsertMeta('meta[property="og:description"]', () => document.createElement('meta'), { property: 'og:description', content: description });
    upsertMeta('meta[property="og:image"]', () => document.createElement('meta'), { property: 'og:image', content: DEFAULT_IMAGE });
    upsertMeta('meta[name="twitter:card"]', () => document.createElement('meta'), { name: 'twitter:card', content: 'summary_large_image' });
    upsertMeta('meta[name="twitter:image"]', () => document.createElement('meta'), { name: 'twitter:image', content: DEFAULT_IMAGE });
    if (structuredData) {
      upsertJsonLd('route', structuredData);
    } else {
      removeJsonLd('route');
    }
  }, [description, location.pathname, path, robots, structuredData, title]);

  return null;
}

function GuardScreen({ title = 'Validando sessão' }) {
  return (
    <main className="auth-shell">
      <Seo title="Validando sessão" robots="noindex,nofollow" />
      <section className="auth-panel compact">
        <p className="eyebrow">CopaZone</p>
        <h1>{title}</h1>
      </section>
    </main>
  );
}

function LandingPage() {
  return (
    <main className="landing-page">
      <Seo
        title="Liga de Palpites da Copa do Mundo 2026"
        description={DEFAULT_DESCRIPTION}
        path="/"
      />
      <section className="landing-hero">
        <div>
          <p className="eyebrow">CopaZone</p>
          <h1>Liga de palpites da Copa do Mundo entre amigos</h1>
          <p>
            Crie uma liga recreativa, convide participantes e acompanhe palpites e pontuação durante a Copa do Mundo.
          </p>
          <div className="landing-actions">
            <Link to="/login">Entrar</Link>
            <Link to="/cadastro" className="secondary">
              Criar conta
            </Link>
            <Link to="/como-funciona" className="secondary">
              Como funciona
            </Link>
          </div>
        </div>

        <div className="landing-summary">
          {[
            { icon: ShieldCheck, title: 'Conta protegida', desc: 'Acesse suas ligas e seus palpites com segurança.' },
            { icon: Users, title: 'Ligas com amigos', desc: 'Reúna seu grupo em uma disputa simples de acompanhar.' },
            { icon: Trophy, title: 'Copa do Mundo', desc: 'Palpites e pontuação focados nos jogos da Copa.' },
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
              <span>Liga da Firma</span>
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

function HomeRoute({ user, isBooting }) {
  if (!isBooting && user) {
    return <Navigate to="/dashboard" replace />;
  }

  return <LandingPage />;
}

function PublicInfoPage({ page, path }) {
  return (
    <main className="landing-page public-info-page">
      <Seo title={page.title} description={page.description} path={path} structuredData={breadcrumbStructuredData(page, path)} />
      <section className="public-info-shell">
        <Link to="/" className="public-back-link">
          CopaZone
        </Link>
        <div className="public-info-hero">
          <p className="eyebrow">{page.eyebrow}</p>
          <h1>{page.title}</h1>
          <p>{page.intro}</p>
          <div className="landing-actions">
            <Link to="/cadastro">Criar conta</Link>
            <Link to="/login" className="secondary">
              Entrar
            </Link>
          </div>
        </div>

        <div className="public-info-grid">
          {page.sections.map((section) => (
            <article key={section.title}>
              <strong>{section.title}</strong>
              <span>{section.text}</span>
            </article>
          ))}
        </div>

        <nav className="public-info-nav" aria-label="Páginas públicas da CopaZone">
          <Link to="/como-funciona">Como funciona</Link>
          <Link to="/copa-do-mundo-2026">Copa 2026</Link>
          <Link to="/regras">Regras</Link>
          <Link to="/faq">FAQ</Link>
          <Link to="/sobre-o-copazone">Sobre</Link>
        </nav>
      </section>
    </main>
  );
}

function NotFoundPage() {
  return (
        <main className="not-found-page">
      <Seo
        title="Página não encontrada"
        description="O endereço informado não existe ou não está mais disponível no CopaZone."
        robots="noindex,nofollow"
      />
      <section className="not-found-panel">
        <p className="eyebrow">Erro 404</p>
        <h1>Página não encontrada</h1>
        <p>O endereço informado não existe ou não está mais disponível.</p>
        <Link to="/">Voltar para o início</Link>
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

  return (
    <>
      <Seo title="Acesso" robots="noindex,nofollow" />
      {children}
    </>
  );
}

function ProtectedShellRoute({ user, isBooting, onLogout, isLoggingOut, title, subtitle, children }) {
  return (
    <RequireAuth user={user} isBooting={isBooting}>
      <Seo title={title} robots="noindex,nofollow" />
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
  const location = useLocation();
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [isBooting, setIsBooting] = useState(true);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const isPublicPath = location.pathname === '/' || Object.hasOwn(publicPages, location.pathname);

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

  if (isBooting && !isPublicPath) {
    return <GuardScreen />;
  }

  return (
    <>
      <Routes>
        <Route path="/" element={<HomeRoute user={user} isBooting={isBooting} />} />
        {Object.entries(publicPages).map(([path, page]) => (
          <Route key={path} path={path} element={<PublicInfoPage page={page} path={path} />} />
        ))}
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
        <Route path="*" element={<NotFoundPage />} />
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
