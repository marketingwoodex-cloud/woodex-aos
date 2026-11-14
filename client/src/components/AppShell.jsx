import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import Icon, { BrandMark } from './icons.jsx';
import { Avatar, Badge } from './ui.jsx';
import { useAuth } from '../state/AuthContext.jsx';
import { statusMeta } from '../lib/format.js';

const NAV = [
  { group: 'Overview', items: [{ to: '/dashboard', label: 'Dashboard', icon: 'grid' }] },
  {
    group: 'Commerce',
    items: [
      { to: '/products', label: 'Products', icon: 'box' },
      { to: '/orders', label: 'Orders', icon: 'receipt' },
      { to: '/customers', label: 'Customers', icon: 'users' },
    ],
  },
  {
    group: 'Platform',
    items: [
      { to: '/projects', label: 'Projects', icon: 'layers' },
      { to: '/activity', label: 'Activity', icon: 'pulse' },
    ],
  },
  {
    group: 'Workspace',
    items: [
      { to: '/users', label: 'Team', icon: 'shield', adminOnly: true },
      { to: '/settings', label: 'Settings', icon: 'sliders' },
    ],
  },
];

const TITLES = {
  '/dashboard': 'Dashboard',
  '/products': 'Products',
  '/orders': 'Orders',
  '/customers': 'Customers',
  '/projects': 'Projects',
  '/activity': 'Activity',
  '/users': 'Team & access',
  '/settings': 'Settings',
  '/account': 'My account',
};

function useTheme() {
  const [theme, setTheme] = useState(() => {
    if (typeof window === 'undefined') return 'light';
    return window.localStorage.getItem('woodex-theme') || 'light';
  });

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    window.localStorage.setItem('woodex-theme', theme);
  }, [theme]);

  return [theme, () => setTheme((current) => (current === 'light' ? 'dark' : 'light'))];
}

export default function AppShell() {
  const { user, logout, isAdmin } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [theme, toggleTheme] = useTheme();
  const [menuOpen, setMenuOpen] = useState(false);
  const [navOpen, setNavOpen] = useState(false);
  const menuRef = useRef(null);

  useEffect(() => {
    setNavOpen(false);
    setMenuOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    if (!menuOpen) return undefined;
    const onClick = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) setMenuOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [menuOpen]);

  const pageTitle = useMemo(() => {
    const match = Object.keys(TITLES)
      .filter((path) => location.pathname === path || location.pathname.startsWith(`${path}/`))
      .sort((a, b) => b.length - a.length)[0];
    return TITLES[match] ?? 'Woodex AOS';
  }, [location.pathname]);

  const groups = NAV.map((group) => ({
    ...group,
    items: group.items.filter((item) => !item.adminOnly || isAdmin),
  })).filter((group) => group.items.length);

  const handleSignOut = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className={`shell ${navOpen ? 'nav-open' : ''}`}>
      <aside className="sidebar">
        <Link to="/dashboard" className="brand">
          <BrandMark size={38} />
          <span className="brand-text">
            <strong>Woodex</strong>
            <small>Operating System</small>
          </span>
        </Link>

        <nav className="nav" aria-label="Main navigation">
          {groups.map((group) => (
            <div className="nav-group" key={group.group}>
              <p className="nav-group-label">{group.group}</p>
              {group.items.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) => `nav-item ${isActive ? 'nav-item-active' : ''}`}
                >
                  <Icon name={item.icon} size={18} />
                  <span>{item.label}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>

        <div className="sidebar-foot">
          <div className="workspace-card">
            <div className="workspace-card-head">
              <span className="workspace-dot" />
              <span>Lahore workshop</span>
            </div>
            <p>6 showrooms · 2 production lines</p>
          </div>
          <Link to="/account" className="sidebar-user">
            <Avatar name={user?.name} color={user?.accent} size={36} />
            <span className="sidebar-user-text">
              <strong>{user?.name}</strong>
              <small>{statusMeta(user?.role).label}</small>
            </span>
            <Icon name="chevronRight" size={15} />
          </Link>
        </div>
      </aside>

      <button type="button" className="sidebar-backdrop" aria-label="Close navigation" onClick={() => setNavOpen(false)} />

      <div className="main">
        <header className="topbar">
          <button type="button" className="menu-btn" onClick={() => setNavOpen((open) => !open)} aria-label="Toggle navigation">
            <Icon name="menu" size={20} />
          </button>
          <div className="topbar-title">
            <span>{pageTitle}</span>
            <Badge tone="success" dot>
              Live
            </Badge>
          </div>
          <div className="topbar-right">
            <span className="topbar-date">
              {new Date().toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short' })}
            </span>
            <button
              type="button"
              className="icon-btn icon-btn-ghost"
              onClick={toggleTheme}
              aria-label="Toggle colour theme"
              title="Toggle colour theme"
            >
              <Icon name={theme === 'light' ? 'moon' : 'sun'} size={17} />
            </button>
            <div className="user-menu" ref={menuRef}>
              <button
                type="button"
                className="user-menu-trigger"
                onClick={() => setMenuOpen((open) => !open)}
                aria-haspopup="menu"
                aria-expanded={menuOpen}
              >
                <Avatar name={user?.name} color={user?.accent} size={32} />
                <span className="user-menu-name">{user?.name?.split(' ')[0]}</span>
                <Icon name="chevronDown" size={14} />
              </button>
              {menuOpen ? (
                <div className="user-menu-panel" role="menu">
                  <div className="user-menu-head">
                    <strong>{user?.name}</strong>
                    <span>{user?.email}</span>
                    <Badge tone={statusMeta(user?.role).tone}>{statusMeta(user?.role).label}</Badge>
                  </div>
                  <Link to="/account" className="user-menu-item" role="menuitem">
                    <Icon name="shield" size={16} /> My account
                  </Link>
                  {isAdmin ? (
                    <Link to="/settings" className="user-menu-item" role="menuitem">
                      <Icon name="sliders" size={16} /> Workspace settings
                    </Link>
                  ) : null}
                  <button type="button" className="user-menu-item user-menu-danger" role="menuitem" onClick={handleSignOut}>
                    <Icon name="logout" size={16} /> Sign out
                  </button>
                </div>
              ) : null}
            </div>
          </div>
        </header>

        <main className="content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
