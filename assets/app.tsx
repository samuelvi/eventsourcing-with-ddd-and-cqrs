import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Wizard } from './pages/Wizard';
import { DataExplorer } from './pages/DataExplorer';
import { DemoFlow } from './pages/DemoFlow';

const queryClient = new QueryClient();

// Shared Flat Icons
export const Icons: Record<string, React.FC> = {
    Home: () => (
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
    ),
    Wizard: () => (
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
        </svg>
    ),
    Explorer: () => (
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
    ),
    Demo: () => (
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
        </svg>
    ),
    ArrowRight: () => (
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <line x1="5" y1="12" x2="19" y2="12" />
            <polyline points="12 5 19 12 12 19" />
        </svg>
    ),
    CheckCircle: () => (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
    )
};

type PageType = 'home' | 'wizard' | 'explorer' | 'demo';

const NavButton = ({
    target,
    label,
    icon: Icon,
    currentPage,
    onNavigate
}: {
    target: PageType;
    label: string;
    icon: React.FC;
    currentPage: string;
    onNavigate: (page: PageType) => void;
}) => (
    <button
        onClick={() => onNavigate(target)}
        style={{
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            padding: '8px 16px',
            background: 'none',
            border: 'none',
            cursor: 'pointer',
            borderRadius: '8px',
            color: currentPage === target ? '#111827' : '#6b7280',
            backgroundColor: currentPage === target ? '#f3f4f6' : 'transparent',
            fontWeight: currentPage === target ? 600 : 500,
            transition: 'all 0.2s',
            fontSize: '14px'
        }}
    >
        <Icon /> {label}
    </button>
);

function App() {
    const [page, setPage] = useState<PageType>(
        (window.location.pathname === '/wizard'
            ? 'wizard'
            : window.location.pathname === '/explorer'
              ? 'explorer'
              : window.location.pathname === '/demo'
                ? 'demo'
                : 'home') as PageType
    );

    useEffect(() => {
        const handlePopState = () => {
            const path = window.location.pathname;
            setPage(
                (path === '/wizard'
                    ? 'wizard'
                    : path === '/explorer'
                      ? 'explorer'
                      : path === '/demo'
                        ? 'demo'
                        : 'home') as PageType
            );
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    const navigateTo = (newPage: PageType) => {
        const url =
            newPage === 'wizard'
                ? '/wizard'
                : newPage === 'explorer'
                  ? '/explorer'
                  : newPage === 'demo'
                    ? '/demo'
                    : '/';
        window.history.pushState({}, '', url);
        setPage(newPage);
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
                    currentPage={page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="wizard"
                    label="Booking Wizard"
                    icon={Icons.Wizard}
                    currentPage={page}
                    onNavigate={navigateTo}
                />
                <NavButton
                    target="explorer"
                    label="Data Explorer"
                    icon={Icons.Explorer}
                    currentPage={page}
                    onNavigate={navigateTo}
                />
                <div style={{ flexGrow: 1 }} />
                <NavButton
                    target="demo"
                    label="System Monitor"
                    icon={Icons.Demo}
                    currentPage={page}
                    onNavigate={navigateTo}
                />
            </nav>

            <main style={{ padding: '40px' }}>
                {page === 'home' && (
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

                {page === 'wizard' && <Wizard />}
                {page === 'explorer' && <DataExplorer />}
                {page === 'demo' && <DemoFlow />}
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
