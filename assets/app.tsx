import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { Wizard } from './pages/Wizard';

function App() {
    // Initial state based on current URL
    const [page, setPage] = useState<'home' | 'wizard'>(
        window.location.pathname === '/wizard' ? 'wizard' : 'home'
    );

    // Sync state with browser back/forward buttons
    useEffect(() => {
        const handlePopState = () => {
            setPage(window.location.pathname === '/wizard' ? 'wizard' : 'home');
        };
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    const navigateTo = (newPage: 'home' | 'wizard') => {
        const url = newPage === 'wizard' ? '/wizard' : '/';
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
                    style={{ background: 'none', border: 'none', cursor: 'pointer', fontWeight: page === 'wizard' ? 'bold' : 'normal' }}
                >
                    Start Booking Wizard
                </button>
            </nav>

            {page === 'home' && (
                <div>
                    <h1>Welcome to the Event Sourcing System</h1>
                    <p>Select an option from the menu to get started.</p>
                    <button 
                        onClick={() => navigateTo('wizard')}
                        style={{ padding: '10px 20px', backgroundColor: '#28a745', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer' }}
                    >
                        Go to Booking Wizard 🚀
                    </button>
                </div>
            )}

            {page === 'wizard' && <Wizard />}
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
