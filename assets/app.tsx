import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { Wizard } from './pages/Wizard';
import { DataExplorer } from './pages/DataExplorer';
import { DemoFlow } from './pages/DemoFlow';

function App() {
    // Initial state based on current URL
    const [page, setPage] = useState<'home' | 'wizard' | 'explorer' | 'demo'>(
        window.location.pathname === '/wizard' ? 'wizard' : 
        window.location.pathname === '/explorer' ? 'explorer' : 
        window.location.pathname === '/demo' ? 'demo' : 'home'
    );

    // Sync state with browser back/forward buttons
    useEffect(() => {
        const handlePopState = () => {
            const path = window.location.pathname;
            setPage(
                path === '/wizard' ? 'wizard' : 
                path === '/explorer' ? 'explorer' : 
                path === '/demo' ? 'demo' : 'home'
            );
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    const navigateTo = (newPage: 'home' | 'wizard' | 'explorer' | 'demo') => {
        const url = newPage === 'wizard' ? '/wizard' : 
                    newPage === 'explorer' ? '/explorer' : 
                    newPage === 'demo' ? '/demo' : '/';
        window.history.pushState({}, '', url);
        setPage(newPage);
    };

    return (
        <div style={{ fontFamily: 'sans-serif', padding: '20px' }}>
            <nav style={{ marginBottom: '20px', borderBottom: '1px solid #eee', paddingBottom: '10px' }}>
                <button 
                    onClick={() => navigateTo('home')}
                    style={{ marginRight: '10px', background: 'none', border: 'none', cursor: 'pointer', fontWeight: page === 'home' ? 'bold' : 'normal' }}
                >
                    Home
                </button>
                <button 
                    onClick={() => navigateTo('wizard')}
                    style={{ marginRight: '10px', background: 'none', border: 'none', cursor: 'pointer', fontWeight: page === 'wizard' ? 'bold' : 'normal' }}
                >
                    Start Booking Wizard
                </button>
                <button 
                    onClick={() => navigateTo('explorer')}
                    style={{ marginRight: '10px', background: 'none', border: 'none', cursor: 'pointer', fontWeight: page === 'explorer' ? 'bold' : 'normal' }}
                >
                    🔍 Data Explorer
                </button>
                <button 
                    onClick={() => navigateTo('demo')}
                    style={{ background: 'none', border: 'none', cursor: 'pointer', fontWeight: page === 'demo' ? 'bold' : 'normal', color: '#dc3545' }}
                >
                    🎭 TED Demo Mode
                </button>
            </nav>

            {page === 'home' && (
                <div>
                    <h1>Welcome to the Event Sourcing System</h1>
                    <p>Select an option from the menu to get started.</p>
                    <div style={{ display: 'flex', gap: '10px' }}>
                        <button 
                            onClick={() => navigateTo('wizard')}
                            style={{ padding: '10px 20px', backgroundColor: '#28a745', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer' }}
                        >
                            Go to Booking Wizard 🚀
                        </button>
                        <button 
                            onClick={() => navigateTo('demo')}
                            style={{ padding: '10px 20px', backgroundColor: '#dc3545', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer' }}
                        >
                            Start TED Demo 🎭
                        </button>
                    </div>
                </div>
            )}

            {page === 'wizard' && <Wizard />}
            {page === 'explorer' && <DataExplorer />}
            {page === 'demo' && <DemoFlow />}
        </div>
    );
}

const rootElement = document.getElementById('root');
if (rootElement) {
    const root = ReactDOM.createRoot(rootElement);
    root.render(
        <React.StrictMode>
            <App />
        </React.StrictMode>
    );
}
