import React, { useState, useEffect } from 'react';
import { v4 as uuidv4 } from 'uuid';

interface Stats {
    events: number;
    users: number;
    bookings: number;
}

// Simple flat icons as SVG components
const IconCheck = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6L9 17l-5-5"/></svg>;
const IconAlert = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>;
const IconActivity = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>;
const IconZap = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>;

export function DemoFlow() {
    const [stats, setStats] = useState<Stats>({ events: 0, users: 0, bookings: 0 });
    const [projectionsEnabled, setProjectionsEnabled] = useState(true);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');

    const isInconsistent = stats.events > stats.bookings;

    const refreshStats = async () => {
        try {
            const res = await fetch('/api/demo/stats');
            const data = await res.json();
            setStats(data);
            
            const statusRes = await fetch('/api/demo/status');
            const statusData = await statusRes.json();
            setProjectionsEnabled(statusData.projectionsEnabled);
        } catch (e) {
            console.error("Fetch error", e);
        }
    };

    useEffect(() => {
        refreshStats();
        const interval = setInterval(refreshStats, 3000);
        return () => clearInterval(interval);
    }, []);

    const toggleProjections = async () => {
        setLoading(true);
        try {
            const res = await fetch('/api/demo/toggle', { method: 'POST' });
            const data = await res.json();
            setProjectionsEnabled(data.projectionsEnabled);
            setMessage(data.projectionsEnabled ? 'Projectors are now online' : 'Projectors disconnected');
        } catch (e) {
            setMessage('Error toggling status');
        } finally {
            setLoading(false);
        }
    };

    const submitRandomBooking = async () => {
        setLoading(true);
        const name = `Client ${Math.floor(Math.random() * 1000)}`;
        const email = `client${Math.floor(Math.random() * 1000)}@example.com`;
        
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
                setMessage(`Fact recorded for ${name}`);
            }
            await refreshStats();
        } catch (e) {
            setMessage('Error creating entry');
        } finally {
            setLoading(false);
        }
    };

    const runRebuild = async () => {
        setLoading(true);
        setMessage('Replaying history...');
        try {
            await fetch('/api/demo/rebuild', { method: 'POST' });
            await refreshStats();
            setMessage('Consistency restored');
        } catch (e) {
            setMessage('Rebuild failed');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{ maxWidth: '1000px', margin: '0 auto' }}>
            <header style={{ marginBottom: '40px' }}>
                <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>TED Demo Mode</h1>
                <p style={{ margin: '4px 0 0', color: '#6b7280' }}>Visualize the separation of concerns in Event Sourcing.</p>
            </header>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(350px, 1fr))', gap: '32px' }}>
                
                {/* INTERACTION ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    
                    {/* TOGGLE */}
                    <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                        <h3 style={{ marginTop: 0, fontSize: '18px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <IconZap /> 1. Infrastructure Link
                        </h3>
                        <p style={{ color: '#6b7280', fontSize: '15px', lineHeight: '1.6', marginBottom: '24px' }}>
                            Toggle the projection engine. When disabled, events are stored but read models remain static.
                        </p>
                        <button 
                            onClick={toggleProjections}
                            disabled={loading}
                            style={{
                                width: '100%',
                                padding: '16px',
                                fontSize: '16px',
                                backgroundColor: projectionsEnabled ? '#6366f1' : '#f43f5e',
                                color: 'white',
                                border: 'none',
                                borderRadius: '12px',
                                cursor: 'pointer',
                                fontWeight: 600,
                                display: 'flex',
                                justifyContent: 'center',
                                alignItems: 'center',
                                gap: '10px',
                                transition: 'all 0.2s'
                            }}
                        >
                            {projectionsEnabled ? <IconCheck /> : <IconAlert />}
                            Projectors: {projectionsEnabled ? 'Online' : 'Broken'}
                        </button>
                    </div>

                    {/* EVENT GEN */}
                    <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                        <h3 style={{ marginTop: 0, fontSize: '18px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <IconActivity /> 2. Fact Generator
                        </h3>
                        <p style={{ color: '#6b7280', fontSize: '15px', lineHeight: '1.6', marginBottom: '24px' }}>
                            Emit a domain event to witness how the system handles new information.
                        </p>
                        <button 
                            onClick={submitRandomBooking}
                            disabled={loading}
                            style={{ 
                                width: '100%', 
                                padding: '16px', 
                                fontSize: '16px', 
                                backgroundColor: '#111827', 
                                color: 'white', 
                                border: 'none', 
                                borderRadius: '12px', 
                                cursor: 'pointer', 
                                fontWeight: 600
                            }}
                        >
                            + Create New Entry
                        </button>
                        {message && (
                            <div style={{ marginTop: '20px', padding: '12px', backgroundColor: '#f3f4f6', color: '#374151', borderRadius: '8px', fontSize: '14px', textAlign: 'center' }}>
                                {message}
                            </div>
                        )}
                    </div>
                </div>

                {/* STATUS ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                        <h3 style={{ marginTop: 0, marginBottom: '24px', fontSize: '18px', fontWeight: 600 }}>3. Integrity Monitor</h3>
                        
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <div style={{ fontSize: '13px', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Event Store</div>
                                    <div style={{ fontSize: '16px', fontWeight: 500 }}>Historical Facts</div>
                                </div>
                                <div style={{ fontSize: '36px', fontWeight: 700, color: '#6366f1' }}>{stats.events}</div>
                            </div>

                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <div style={{ fontSize: '13px', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Read Models</div>
                                    <div style={{ fontSize: '16px', fontWeight: 500 }}>Current State</div>
                                </div>
                                <div style={{ fontSize: '36px', fontWeight: 700, color: isInconsistent ? '#f43f5e' : '#10b981' }}>{stats.bookings}</div>
                            </div>
                        </div>

                        <div style={{ 
                            marginTop: '32px', 
                            padding: '12px', 
                            backgroundColor: isInconsistent ? '#fff1f2' : '#ecfdf5', 
                            borderRadius: '12px', 
                            textAlign: 'center', 
                            border: `1px solid ${isInconsistent ? '#fecdd3' : '#d1fae5'}`,
                            fontSize: '14px',
                            fontWeight: 600,
                            color: isInconsistent ? '#e11d48' : '#059669',
                            textTransform: 'uppercase'
                        }}>
                            {isInconsistent ? 'Inconsistency Detected' : 'System Synchronized'}
                        </div>
                    </div>

                    {/* HEALING */}
                    <div style={{ 
                        backgroundColor: isInconsistent ? '#fff' : 'transparent', 
                        padding: '32px', 
                        borderRadius: '24px', 
                        border: isInconsistent ? '2px solid #6366f1' : '2px dashed #e5e7eb',
                        textAlign: 'center',
                        transition: 'all 0.3s ease'
                    }}>
                        {isInconsistent ? (
                            <>
                                <h4 style={{ margin: '0 0 12px', fontSize: '16px', fontWeight: 600 }}>Self-Healing Protocol</h4>
                                <p style={{ margin: '0 0 24px', color: '#6b7280', fontSize: '14px', lineHeight: '1.5' }}>
                                    Restore consistency by replaying the immutable history from the Event Store.
                                </p>
                                <button 
                                    onClick={runRebuild}
                                    disabled={loading}
                                    style={{
                                        width: '100%',
                                        padding: '16px',
                                        backgroundColor: '#6366f1',
                                        color: 'white',
                                        border: 'none',
                                        borderRadius: '12px',
                                        cursor: 'pointer',
                                        fontSize: '16px',
                                        fontWeight: 600,
                                        boxShadow: '0 10px 15px -3px rgba(99, 102, 241, 0.3)'
                                    }}
                                >
                                    Repair & Sync
                                </button>
                            </>
                        ) : (
                            <p style={{ color: '#9ca3af', fontStyle: 'italic', margin: 0 }}>System is optimal. All facts are projected.</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}