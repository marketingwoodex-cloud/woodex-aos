import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './state/AuthContext.jsx';
import { ToastProvider } from './state/ToastContext.jsx';
import AppShell from './components/AppShell.jsx';
import { BrandMark } from './components/icons.jsx';
import Login from './pages/Login.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Products from './pages/Products.jsx';
import Orders from './pages/Orders.jsx';
import Customers from './pages/Customers.jsx';
import Projects from './pages/Projects.jsx';
import ProjectDetail from './pages/ProjectDetail.jsx';
import Users from './pages/Users.jsx';
import Activity from './pages/Activity.jsx';
import Settings from './pages/Settings.jsx';
import Account from './pages/Account.jsx';
import Storefront from './pages/shop/Storefront.jsx';
import ProductPage from './pages/shop/ProductPage.jsx';
import LiveOverview from './pages/live/LiveOverview.jsx';
import LiveCatalogue from './pages/live/LiveCatalogue.jsx';
import LiveOrders from './pages/live/LiveOrders.jsx';
import LiveQuotations from './pages/live/LiveQuotations.jsx';
import NotFound from './pages/NotFound.jsx';

function Splash() {
  return (
    <div className="splash">
      <BrandMark size={52} />
      <p>Loading Woodex AOS…</p>
      <span className="spinner" />
    </div>
  );
}

function RequireAuth({ children }) {
  const { user, loading } = useAuth();
  const location = useLocation();
  if (loading) return <Splash />;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  return children;
}

function AppRoutes() {
  const { user, loading } = useAuth();

  return (
    <Routes>
      <Route path="/login" element={loading ? <Splash /> : user ? <Navigate to="/dashboard" replace /> : <Login />} />
      <Route path="/shop" element={<Storefront />} />
      <Route path="/shop/p/:slug" element={<ProductPage />} />
      <Route
        element={
          <RequireAuth>
            <AppShell />
          </RequireAuth>
        }
      >
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/products" element={<Products />} />
        <Route path="/orders" element={<Orders />} />
        <Route path="/customers" element={<Customers />} />
        <Route path="/projects" element={<Projects />} />
        <Route path="/projects/:id" element={<ProjectDetail />} />
        <Route path="/users" element={<Users />} />
        <Route path="/activity" element={<Activity />} />
        <Route path="/settings" element={<Settings />} />
        <Route path="/account" element={<Account />} />
        <Route path="/live" element={<LiveOverview />} />
        <Route path="/live/catalogue" element={<LiveCatalogue />} />
        <Route path="/live/orders" element={<LiveOrders />} />
        <Route path="/live/quotations" element={<LiveQuotations />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <AppRoutes />
      </ToastProvider>
    </AuthProvider>
  );
}
