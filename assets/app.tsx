import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Wizard } from './pages/Wizard';
import { DataExplorer } from './pages/DataExplorer';
import { DemoFlow } from './pages/DemoFlow';
import { UsersManagement } from './pages/UsersManagement';
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
                      : '/';
        navigateToPath(url);
    };

    return (
        <div
            style={{
                minHeight: '100vh',
                backgroundColor: '#f9fafb',
                color: '#111827',
                fontFamily: 'Inter, system-ui, sans-serif'
            }}
        >
            <nav
                style={{
                    backgroundColor: '#fff',
                    borderBottom: '1px solid #e5e7eb',
                    padding: '12px 40px',
                    display: 'flex',
                    gap: '8px',
                    position: 'sticky',
                    top: 0,
                    zIndex: 100
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
                                fontSize: '48px',
                                fontWeight: 800,
                                letterSpacing: '-0.025em',
                                marginBottom: '16px'
                            }}
                        >
                            Modern Event Sourcing
                        </h1>
                        <p
                            style={{
                                fontSize: '20px',
                                color: '#6b7280',
                                marginBottom: '40px',
                                lineHeight: '1.6'
                            }}
                        >
                            A Proof of Concept demonstrating Domain-Driven Design, CQRS, and
                            reliable state reconstruction through historical facts.
                        </p>

                        <div style={{ display: 'flex', justifyContent: 'center', gap: '16px' }}>
                            <button
                                onClick={() => navigateTo('wizard')}
                                style={{
                                    padding: '12px 24px',
                                    backgroundColor: '#111827',
                                    color: 'white',
                                    border: 'none',
                                    borderRadius: '10px',
                                    cursor: 'pointer',
                                    fontSize: '16px',
                                    fontWeight: 600,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '8px'
                                }}
                            >
                                Start Wizard <Icons.ArrowRight />
                            </button>
                            <button
                                onClick={() => navigateTo('demo')}
                                style={{
                                    padding: '12px 24px',
                                    backgroundColor: '#fff',
                                    color: '#111827',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '10px',
                                    cursor: 'pointer',
                                    fontSize: '16px',
                                    fontWeight: 600
                                }}
                            >
                                Open Architecture Monitor
                            </button>
                        </div>

                        <div
                            style={{
                                marginTop: '80px',
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr 1fr',
                                gap: '24px',
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
                                        padding: '24px',
                                        backgroundColor: '#fff',
                                        borderRadius: '16px',
                                        border: '1px solid #e5e7eb'
                                    }}
                                >
                                    <div style={{ color: '#111827', marginBottom: '12px' }}>
                                        <Icons.CheckCircle />
                                    </div>
                                    <h4 style={{ margin: '0 0 8px', fontWeight: 700 }}>{f.t}</h4>
                                    <p
                                        style={{
                                            margin: 0,
                                            color: '#6b7280',
                                            fontSize: '14px',
                                            lineHeight: '1.5'
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
