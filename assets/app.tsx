import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Wizard } from './pages/Wizard';
import { DataExplorer } from './pages/DataExplorer';
import { DemoFlow } from './pages/DemoFlow';
import { UsersManagement } from './pages/UsersManagement';
import { DashboardPanel } from './pages/DashboardPanel';
import { PostgresExecutionsPage } from './pages/PostgresExecutionsPage';
import { RedisJobsPage } from './pages/RedisJobsPage';
import { SupplierResponses } from './features/quotes/pages/SupplierResponses';
import { Icons } from './components/Icons';
import { NavButton, PageType } from './components/NavButton';

const queryClient = new QueryClient();

type UsersRouteMode = 'list' | 'create' | 'edit';

type RouteState = {
    page: PageType;
    usersMode: UsersRouteMode;
    userId: string | null;
};

function resolveRoute(pathname: string): RouteState {
    if (pathname === '/wizard') {
        return { page: 'wizard', usersMode: 'list', userId: null };
    }

    if (pathname === '/explorer') {
        return { page: 'explorer', usersMode: 'list', userId: null };
    }

    if (pathname === '/demo') {
        return { page: 'demo', usersMode: 'list', userId: null };
    }

    if (pathname === '/users') {
        return { page: 'users', usersMode: 'list', userId: null };
    }

    if (pathname === '/panel') {
        return { page: 'panel', usersMode: 'list', userId: null };
    }

    if (pathname === '/executions') {
        return { page: 'executions', usersMode: 'list', userId: null };
    }

    if (pathname === '/redis-jobs') {
        return { page: 'redisJobs', usersMode: 'list', userId: null };
    }

    if (pathname === '/supplier-responses') {
        return { page: 'supplier', usersMode: 'list', userId: null };
    }

    if (pathname === '/users/new') {
        return { page: 'users', usersMode: 'create', userId: null };
    }

    const editMatch = pathname.match(/^\/users\/([^/]+)\/edit$/);
    if (editMatch) {
        return { page: 'users', usersMode: 'edit', userId: editMatch[1] };
    }

    return { page: 'home', usersMode: 'list', userId: null };
}

function App() {
    const [route, setRoute] = useState<RouteState>(resolveRoute(window.location.pathname));

    useEffect(() => {
        const handlePopState = () => {
            setRoute(resolveRoute(window.location.pathname));
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    const navigateToPath = (path: string) => {
        window.history.pushState({}, '', path);
        setRoute(resolveRoute(path));
    };

    const navigateTo = (newPage: PageType) => {
        const url =
            newPage === 'wizard'
                ? '/wizard'
                : newPage === 'explorer'
                  ? '/explorer'
                  : newPage === 'demo'
                    ? '/demo'
                    : newPage === 'users'
                      ? '/users'
                      : newPage === 'panel'
                        ? '/panel'
                        : newPage === 'executions'
                          ? '/executions'
                          : newPage === 'redisJobs'
                            ? '/redis-jobs'
                            : newPage === 'supplier'
                              ? '/supplier-responses'
                              : '/';
        navigateToPath(url);
    };

    return (
        <div
            style={{
                minHeight: '100vh',
                backgroundColor: '#fafaf9', // bone white
                color: '#1c1917', // warm charcoal
                fontFamily: 'Inter, system-ui, sans-serif'
            }}
        >
            <nav
                style={{
                    backgroundColor: '#fff',
                    borderBottom: '1px solid #e7e5e4',
                    padding: '12px 40px',
                    display: 'flex',
                    gap: '8px',
                    position: 'sticky',
                    top: 0,
                    zIndex: 100,
                    boxShadow: '0 1px 2px rgba(0,0,0,0.02)'
                }}
            >
                <NavButton
                    target="home"
                    label="Overview"
                    icon={Icons.Home}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="wizard"
                    label="Booking"
                    icon={Icons.Wizard}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="users"
                    label="Users"
                    icon={Icons.Users}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="supplier"
                    label="Supplier Responses"
                    icon={Icons.Supplier}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="demo"
                    label="Architecture Monitor"
                    icon={Icons.Demo}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="explorer"
                    label="Data Explorer"
                    icon={Icons.Explorer}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="panel"
                    label="Control Panel"
                    icon={Icons.Dashboard}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="executions"
                    label="Postgres Executions"
                    icon={Icons.Executions}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="redisJobs"
                    label="Redis Jobs"
                    icon={Icons.RedisJobs}
                    currentPage={route.page}
                    onNavigate={navigateTo}
                />
            </nav>

            <main style={{ padding: '40px' }}>
                {route.page === 'home' && (
                    <div
                        style={{
                            maxWidth: '800px',
                            margin: '0 auto',
                            textAlign: 'center',
                            padding: '60px 0'
                        }}
                    >
                        <h1
                            style={{
                                fontSize: '56px',
                                fontWeight: 900,
                                letterSpacing: '-0.04em',
                                marginBottom: '24px',
                                color: '#1c1917'
                            }}
                        >
                            Modern Event Sourcing
                        </h1>
                        <p
                            style={{
                                fontSize: '22px',
                                color: '#57534e',
                                marginBottom: '48px',
                                lineHeight: '1.6'
                            }}
                        >
                            A Proof of Concept demonstrating Domain-Driven Design, CQRS, and
                            reliable state reconstruction through historical facts.
                        </p>

                        <div style={{ display: 'flex', justifyContent: 'center', gap: '20px' }}>
                            <button
                                onClick={() => navigateTo('wizard')}
                                style={{
                                    padding: '14px 32px',
                                    background: 'linear-gradient(135deg, #1c1917 0%, #44403c 100%)',
                                    color: 'white',
                                    border: 'none',
                                    borderRadius: '14px',
                                    cursor: 'pointer',
                                    fontSize: '17px',
                                    fontWeight: 700,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '10px',
                                    boxShadow: '0 10px 15px -3px rgba(0,0,0,0.2)'
                                }}
                            >
                                Start Wizard <Icons.ArrowRight />
                            </button>
                            <button
                                onClick={() => navigateTo('demo')}
                                style={{
                                    padding: '14px 32px',
                                    backgroundColor: '#fff',
                                    color: '#1c1917',
                                    border: '1px solid #e7e5e4',
                                    borderRadius: '14px',
                                    cursor: 'pointer',
                                    fontSize: '17px',
                                    fontWeight: 700,
                                    boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                                }}
                            >
                                Open Architecture Monitor
                            </button>
                        </div>

                        <div
                            style={{
                                marginTop: '100px',
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr 1fr',
                                gap: '32px',
                                textAlign: 'left'
                            }}
                        >
                            {[
                                {
                                    t: 'Facts over State',
                                    d: 'Store every change as an immutable event.'
                                },
                                {
                                    t: 'Strict Idempotency',
                                    d: 'Client-side identity ensures no duplicates.'
                                },
                                {
                                    t: 'Instant Recovery',
                                    d: 'Rebuild your entire system from history.'
                                }
                            ].map((f, i) => (
                                <div
                                    key={i}
                                    style={{
                                        padding: '32px',
                                        backgroundColor: '#fff',
                                        borderRadius: '24px',
                                        border: '1px solid #e7e5e4',
                                        boxShadow: '0 4px 6px -1px rgba(0,0,0,0.03)'
                                    }}
                                >
                                    <div style={{ color: '#b45309', marginBottom: '16px' }}>
                                        <Icons.CheckCircle />
                                    </div>
                                    <h4
                                        style={{
                                            margin: '0 0 12px',
                                            fontSize: '18px',
                                            fontWeight: 800
                                        }}
                                    >
                                        {f.t}
                                    </h4>
                                    <p
                                        style={{
                                            margin: 0,
                                            color: '#78716c',
                                            fontSize: '15px',
                                            lineHeight: '1.6'
                                        }}
                                    >
                                        {f.d}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {route.page === 'wizard' && <Wizard />}
                {route.page === 'explorer' && <DataExplorer />}
                {route.page === 'demo' && <DemoFlow />}
                {route.page === 'panel' && <DashboardPanel />}
                {route.page === 'executions' && <PostgresExecutionsPage />}
                {route.page === 'redisJobs' && <RedisJobsPage />}
                {route.page === 'supplier' && <SupplierResponses />}
                {route.page === 'users' && (
                    <UsersManagement
                        mode={route.usersMode}
                        userId={route.userId}
                        onNavigate={navigateToPath}
                    />
                )}
            </main>
        </div>
    );
}

const rootElement = document.getElementById('root');
if (rootElement) {
    const root = ReactDOM.createRoot(rootElement);
    root.render(
        <React.StrictMode>
            <QueryClientProvider client={queryClient}>
                <App />
            </QueryClientProvider>
        </React.StrictMode>
    );
}
