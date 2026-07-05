import { Link, NavLink } from 'react-router';
import { CalendarDays, Globe2, Home, KeyRound, LockKeyhole, LogOut, Menu, Plus, UserRound } from 'lucide-react';
import { useState } from 'react';
import { BrandLogo } from '../../../components/brand-logo';

const navItems = [
  { to: '/dashboard', label: 'Inicio', icon: Home },
  { to: '/ligas/minhas', label: 'Minhas ligas', icon: LockKeyhole },
  { to: '/ligas/publicas', label: 'Ligas publicas', icon: Globe2 },
  { to: '/copa/dados', label: 'Dados da Copa', icon: CalendarDays },
  { to: '/ligas/entrar', label: 'Entrar por codigo', icon: KeyRound },
  { to: '/ligas/criar', label: 'Criar liga', icon: Plus },
];

export function AuthenticatedShell({ user, onLogout, isLoggingOut, title, subtitle, children }) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  return (
    <div className="app-shell">
      <aside className={isMenuOpen ? 'app-sidebar open' : 'app-sidebar'}>
        <Link to="/dashboard" className="shell-brand">
          <BrandLogo className="brand-logo-shell" subtitle="Copa do Mundo" />
        </Link>

        <nav className="shell-nav" aria-label="Menu principal">
          {navItems.map((item) => {
            const Icon = item.icon;

            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={() => setIsMenuOpen(false)}
                className={({ isActive }) => (isActive ? 'active' : undefined)}
              >
                <Icon size={18} />
                {item.label}
              </NavLink>
            );
          })}
        </nav>

        <div className="shell-user">
          <UserRound size={18} />
          <div>
            <strong>{user?.name}</strong>
            <small>{user?.email}</small>
          </div>
        </div>

        <button type="button" className="shell-logout" onClick={onLogout} disabled={isLoggingOut}>
          <LogOut size={18} />
          {isLoggingOut ? 'Saindo...' : 'Sair'}
        </button>
      </aside>

      {isMenuOpen && <button type="button" className="shell-scrim" aria-label="Fechar menu" onClick={() => setIsMenuOpen(false)} />}

      <main className="app-main">
        <header className="app-header">
          <button type="button" className="menu-button" onClick={() => setIsMenuOpen((value) => !value)} aria-label="Abrir menu">
            <Menu size={21} />
          </button>
          <div>
            <h1>{title}</h1>
            <p>{subtitle}</p>
          </div>
        </header>

        {children}
      </main>
    </div>
  );
}
