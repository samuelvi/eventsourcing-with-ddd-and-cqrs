import React, { useState, useEffect } from 'react';
import { v4 as uuidv4 } from 'uuid';

interface Stats {
    events: number;
    users: number;
    bookings: number;
}

export function DemoFlow() {
    const [stats, setStats] = useState<Stats>({ events: 0, users: 0, bookings: 0 });
    const [projectionsEnabled, setProjectionsEnabled] = useState(true);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');

    const isInconsistent = stats.events > stats.bookings;

    const refreshStats = async () => {
        try {
            const res = await fetch('/api/demo/stats');
            setStats(await res.json());
            const statusRes = await fetch('/api/demo/status');
            const statusData = await statusRes.json();
            setProjectionsEnabled(statusData.projectionsEnabled);
        } catch (e) {
            console.error("Fetch error", e);
        }
    };

    useEffect(() => {
        refreshStats();
        const interval = setInterval(refreshStats, 2000);
        return () => clearInterval(interval);
    }, []);

    const toggleProjections = async () => {
        setLoading(true);
        await fetch('/api/demo/toggle', { method: 'POST' });
        await refreshStats();
        setLoading(false);
    };

    const submitRandomBooking = async () => {
        setLoading(true);
        const name = `Demo User ${Math.floor(Math.random() * 1000)}`;
        const email = `demo${Math.floor(Math.random() * 1000)}@example.com`;
        
        try {
            const res = await fetch('/api/booking-wizard', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    bookingId: uuidv4(),
                    pax: Math.floor(Math.random() * 10) + 1,
                    budget: 100,
                    clientName: name,
                    clientEmail: email
                }),
            });
            if (res.ok) {
                setMessage(`✅ Event stored for ${name}`);
            }
            await refreshStats();
        } catch (e) {
            setMessage('❌ Error creating booking');
        }
        setLoading(false);
    };

    const runRebuild = async () => {
        setLoading(true);
        setMessage('🔄 Replaying history...');
        await fetch('/api/demo/rebuild', { method: 'POST' });
        await refreshStats();
        setMessage('✨ System state restored!');
        setLoading(false);
    };

    const runReset = async () => {
        if (!confirm('Clear all data and restart demo?')) return;
        setLoading(true);
        await fetch('/api/demo/reset', { method: 'POST' });
        setMessage('🌟 Fresh start.');
        await refreshStats();
        setLoading(false);
    };

    return (
        <div style={{ maxWidth: '900px', margin: '0 auto', fontFamily: 'system-ui, sans-serif', padding: '20px', backgroundColor: '#f0f2f5', borderRadius: '12px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h1 style={{ margin: 0 }}>🎭 TED Talk: The Power of Event Sourcing</h1>
                <button onClick={runReset} disabled={loading} style={{ padding: '8px 16px', cursor: 'pointer', backgroundColor: '#6c757d', color: 'white', border: 'none', borderRadius: '6px' }}>
                    ♻️ Full Reset
                </button>
            </div>

            {/* STEP 1: SIMULATE BREAKAGE */}
            <div style={{ backgroundColor: 'white', padding: '25px', borderRadius: '10px', boxShadow: '0 2px 4px rgba(0,0,0,0.1)', marginBottom: '20px' }}>
                <h2 style={{ marginTop: 0, color: '#333' }}>1. Simular Rotura de Infraestructura ⚡</h2>
                <p style={{ color: '#666' }}>Apaga los proyectores para simular un fallo en el microservicio de lectura.</p>
                <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
                    <button 
                        onClick={toggleProjections}
                        disabled={loading}
                        style={{
                            padding: '15px 30px',
                            fontSize: '18px',
                            backgroundColor: projectionsEnabled ? '#28a745' : '#dc3545',
                            color: 'white',
                            border: 'none',
                            borderRadius: '50px',
                            cursor: 'pointer',
                            fontWeight: 'bold',
                            boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
                            transition: 'all 0.3s'
                        }}
                    >
                        PROJECTORS: {projectionsEnabled ? 'ACTIVE ✅' : 'BROKEN ❌'}
                    </button>
                    <div style={{ fontSize: '16px', fontWeight: 'bold', color: projectionsEnabled ? '#28a745' : '#dc3545' }}>
                        {projectionsEnabled ? 'Estás en modo normal.' : '¡PROYECCIONES DESACTIVADAS! Los datos no llegarán a la UI.'}
                    </div>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' }}>
                {/* STEP 2: TRAFFIC */}
                <div style={{ backgroundColor: 'white', padding: '25px', borderRadius: '10px', boxShadow: '0 2px 4px rgba(0,0,0,0.1)' }}>
                    <h2 style={{ marginTop: 0 }}>2. Generar Tráfico 🚀</h2>
                    <button 
                        onClick={submitRandomBooking}
                        disabled={loading}
                        style={{ padding: '20px', width: '100%', fontSize: '18px', cursor: 'pointer', backgroundColor: '#007bff', color: 'white', border: 'none', borderRadius: '8px', fontWeight: 'bold' }}
                    >
                        + Crear Reserva Aleatoria
                    </button>
                    <p style={{ minHeight: '24px', marginTop: '15px', color: '#007bff', fontWeight: 'bold' }}>{message}</p>
                </div>

                {/* STEP 3: MONITOR */}
                <div style={{ backgroundColor: '#212529', color: '#00ff00', padding: '25px', borderRadius: '10px', fontFamily: 'monospace', boxShadow: '0 2px 4px rgba(0,0,0,0.1)' }}>
                    <h2 style={{ marginTop: 0, color: 'white', fontFamily: 'sans-serif' }}>3. Estado del Sistema 📊</h2>
                    <div style={{ fontSize: '20px', display: 'flex', flexDirection: 'column', gap: '15px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                            <span>&gt; EVENT_STORE:</span>
                            <span style={{ color: 'white' }}>{stats.events} events</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #444', paddingTop: '10px' }}>
                            <span>&gt; READ_MODELS:</span>
                            <span style={{ color: isInconsistent ? '#ff4d4d' : '#00ff00' }}>{stats.bookings} records</span>
                        </div>
                        <div style={{ textAlign: 'center', marginTop: '10px', fontSize: '14px', color: isInconsistent ? '#ff4d4d' : '#888' }}>
                            {isInconsistent ? 'STATUS: INCONSISTENT ⚠️' : 'STATUS: SYNCED OK'}
                        </div>
                    </div>
                </div>
            </div>

            {/* STEP 4: REPAIR (The Magic) */}
            <div style={{ 
                backgroundColor: isInconsistent ? '#fff3cd' : '#e9ecef', 
                padding: '30px', 
                borderRadius: '10px', 
                border: isInconsistent ? '3px solid #ffc107' : '1px solid #ccc', 
                textAlign: 'center',
                opacity: isInconsistent ? 1 : 0.6
            }}>
                <h2 style={{ marginTop: 0 }}>4. La Autocuración (Self-Healing) ✨</h2>
                <p style={{ fontSize: '18px' }}>
                    {isInconsistent 
                        ? 'Se ha detectado una pérdida de consistencia. El Event Store tiene la verdad, pero la UI está desactualizada.' 
                        : 'El sistema está sano. Si hubiera un fallo, podrías reconstruirlo todo aquí.'}
                </p>
                <button 
                    onClick={runRebuild}
                    disabled={loading || !isInconsistent}
                    style={{
                        padding: '20px 40px',
                        backgroundColor: isInconsistent ? '#ffc107' : '#6c757d',
                        color: '#212529',
                        border: 'none',
                        borderRadius: '8px',
                        cursor: isInconsistent ? 'pointer' : 'not-allowed',
                        fontSize: '22px',
                        fontWeight: 'bold',
                        boxShadow: isInconsistent ? '0 6px 12px rgba(0,0,0,0.2)' : 'none',
                        transition: 'all 0.3s'
                    }}
                >
                    🛠️ REPAIR & REPLAY HISTORY
                </button>
            </div>
        </div>
    );
}
