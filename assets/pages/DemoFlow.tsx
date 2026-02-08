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

    const refreshStats = async () => {
        const res = await fetch('/api/demo/stats');
        setStats(await res.json());
        const statusRes = await fetch('/api/demo/status');
        const statusData = await statusRes.json();
        setProjectionsEnabled(statusData.projectionsEnabled);
    };

    useEffect(() => {
        refreshStats();
        const interval = setInterval(refreshStats, 3000);
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
            await fetch('/api/booking-wizard', {
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
            setMessage(`✅ Created booking for ${name}`);
            await refreshStats();
        } catch (e) {
            setMessage('❌ Error creating booking');
        }
        setLoading(false);
    };

    const runRebuild = async () => {
        setLoading(true);
        setMessage('🔄 Rebuilding projections...');
        await fetch('/api/demo/rebuild', { method: 'POST' });
        await refreshStats();
        setMessage('✨ Projections repaired successfully!');
        setLoading(false);
    };

    const runReset = async () => {
        if (!confirm('Are you sure you want to reset the entire database to fixtures?')) return;
        setLoading(true);
        setMessage('♻️ Resetting system...');
        await fetch('/api/demo/reset', { method: 'POST' });
        await refreshStats();
        setMessage('🌟 System reset to initial state.');
        setLoading(false);
    };

    return (
        <div style={{ maxWidth: '800px', margin: '0 auto', fontFamily: 'sans-serif' }}>
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '10px' }}>
                <button 
                    onClick={runReset}
                    disabled={loading}
                    style={{ padding: '5px 15px', fontSize: '12px', cursor: 'pointer', backgroundColor: '#f8f9fa', border: '1px solid #ccc', borderRadius: '4px' }}
                >
                    ♻️ Reset to Initial State
                </button>
            </div>
            <div style={{ backgroundColor: '#f8f9fa', padding: '20px', borderRadius: '8px', border: '1px solid #ddd', marginBottom: '20px' }}>
                <h2 style={{ marginTop: 0 }}>Step 1: Configuration ⚙️</h2>
                <p>Use this switch to simulate a service failure in your projectors.</p>
                <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
                    <button 
                        onClick={toggleProjections}
                        disabled={loading}
                        style={{
                            padding: '10px 20px',
                            backgroundColor: projectionsEnabled ? '#28a745' : '#dc3545',
                            color: 'white',
                            border: 'none',
                            borderRadius: '20px',
                            cursor: 'pointer',
                            fontWeight: 'bold'
                        }}
                    >
                        Projections: {projectionsEnabled ? 'RUNNING ✅' : 'STOPPED ❌'}
                    </button>
                    <span>{projectionsEnabled ? 'System is healthy.' : "SIMULATING FAILURE: Events will be saved, but Read Models won't update."}</span>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' }}>
                <div style={{ padding: '20px', border: '1px solid #ddd', borderRadius: '8px' }}>
                    <h3>Step 2: Generate Traffic 🚀</h3>
                    <button 
                        onClick={submitRandomBooking}
                        disabled={loading}
                        style={{ padding: '12px', width: '100%', cursor: 'pointer', backgroundColor: '#007bff', color: 'white', border: 'none', borderRadius: '4px' }}
                    >
                        Create Random Booking
                    </button>
                    {message && <p style={{ fontSize: '14px', color: '#666', marginTop: '10px' }}>{message}</p>}
                </div>

                <div style={{ padding: '20px', border: '1px solid #ddd', borderRadius: '8px', backgroundColor: '#fff' }}>
                    <h3>Step 3: Monitor State 📊</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                            <strong>Event Store (Truth):</strong>
                            <span style={{ fontSize: '18px', color: '#007bff' }}>{stats.events} events</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #eee', paddingTop: '5px' }}>
                            <strong>Users Projection:</strong>
                            <span style={{ fontSize: '18px', color: stats.events > stats.bookings ? '#dc3545' : '#28a745' }}>{stats.users} records</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #eee', paddingTop: '5px' }}>
                            <strong>Bookings Projection:</strong>
                            <span style={{ fontSize: '18px', color: stats.events > stats.bookings ? '#dc3545' : '#28a745' }}>{stats.bookings} records</span>
                        </div>
                    </div>
                </div>
            </div>

            {stats.events > stats.bookings && (
                <div style={{ backgroundColor: '#fff3cd', color: '#856404', padding: '20px', borderRadius: '8px', border: '1px solid #ffeeba', textAlign: 'center' }}>
                    <h3>⚠️ INCONSISTENCY DETECTED!</h3>
                    <p>The system has more events than projections. In a CRUD system, this data would be lost or require manual DB fixing.</p>
                    <p><strong>In Event Sourcing, we just replay the history:</strong></p>
                    <button 
                        onClick={runRebuild}
                        disabled={loading}
                        style={{
                            padding: '15px 30px',
                            backgroundColor: '#ffc107',
                            color: '#212529',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: 'pointer',
                            fontSize: '18px',
                            fontWeight: 'bold'
                        }}
                    >
                        🛠️ Repair Projections (Replay History)
                    </button>
                </div>
            )}
        </div>
    );
}
