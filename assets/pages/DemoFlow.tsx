import React, { useState, useEffect } from 'react';
import { v4 as uuidv4 } from 'uuid';

interface Stats {
    events: number;
    users: number;
    bookings: number;
    checkpoints: Record<string, string | null>;
}

// Simple flat icons as SVG components
const IconCheck = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6L9 17l-5-5"/></svg>;
const IconAlert = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>;
const IconActivity = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>;
const IconZap = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>;
const IconTable = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>;

export function DemoFlow() {
    const [stats, setStats] = useState<Stats>({ events: 0, users: 0, bookings: 0, checkpoints: {} });
    const [projectionsEnabled, setProjectionsEnabled] = useState(true);
    const [userProjectionsEnabled, setUserProjectionsEnabled] = useState(true);
    const [bookingProjectionsEnabled, setBookingProjectionsEnabled] = useState(true);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    
    // Detailed data for lists
    const [events, setEvents] = useState<any[]>([]);
    const [users, setUsers] = useState<any[]>([]);
    const [bookings, setBookings] = useState<any[]>([]);
    const [checkpoints, setCheckpoints] = useState<any[]>([]);

    const isInconsistent = stats.events > stats.bookings || stats.events > stats.users;

    const refreshStats = async () => {
        try {
            // Stats & Status
            const res = await fetch('/api/demo/stats');
            const data = await res.json();
            setStats(data);
            
            const statusRes = await fetch('/api/demo/status');
            const statusData = await statusRes.json();
            setProjectionsEnabled(statusData.projectionsEnabled);
            setUserProjectionsEnabled(statusData.userProjectionsEnabled);
            setBookingProjectionsEnabled(statusData.bookingProjectionsEnabled);

            // Detailed Data
            const [evRes, usrRes, bkRes, cpRes] = await Promise.all([
                fetch('/api/event-store?order[occurredOn]=desc'),
                fetch('/api/users'),
                fetch('/api/bookings?order[createdAt]=desc'),
                fetch('/api/checkpoints')
            ]);
            
            const [evData, usrData, bkData, cpData] = await Promise.all([
                evRes.json(), usrRes.json(), bkRes.json(), cpRes.json()
            ]);

            setEvents((evData['hydra:member'] || []).slice(0, 5));
            setUsers((usrData['hydra:member'] || []).slice(0, 5));
            setBookings((bkData['hydra:member'] || []).slice(0, 5));
            setCheckpoints(cpData['hydra:member'] || []);

        } catch (e) {
            console.error("Fetch error", e);
        }
    };

    useEffect(() => {
        refreshStats();
        const interval = setInterval(refreshStats, 3000);
        return () => clearInterval(interval);
    }, []);

    const toggleProjections = async (type: 'master' | 'user' | 'booking') => {
        setLoading(true);
        try {
            const res = await fetch(`/api/demo/toggle/${type}`, { method: 'POST' });
            const data = await res.json();
            if (type === 'master') setProjectionsEnabled(data.projectionsEnabled);
            else if (type === 'user') setUserProjectionsEnabled(data.userProjectionsEnabled);
            else setBookingProjectionsEnabled(data.bookingProjectionsEnabled);
            
            setMessage(`${type.toUpperCase()} Link updated`);
        } catch (e) {
            setMessage('Error toggling status');
        } finally {
            setLoading(false);
        }
    };

    const submitRandomBooking = async () => {
        setLoading(true);
        const name = `Demo ${Math.floor(Math.random() * 1000)}`;
        const email = `client${Math.floor(Math.random() * 1000)}@test.com`;
        
        try {
            const res = await fetch('/api/booking-wizard', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    bookingId: uuidv4(),
                    pax: Math.floor(Math.random() * 5) + 1,
                    budget: 100,
                    clientName: name,
                    clientEmail: email
                }),
            });
            if (res.ok) setMessage(`Event emitted: ${name}`);
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

    const runReset = async () => {
        if (!confirm('Clear all data?')) return;
        setLoading(true);
        try {
            await fetch('/api/demo/reset', { method: 'POST' });
            setMessage('Reset complete');
            await refreshStats();
        } catch (e) {
            setMessage('Reset failed');
        } finally {
            setLoading(false);
        }
    };

    const DataList = ({ title, items, columns, emptyMsg }: any) => (
        <div style={{ backgroundColor: '#fff', borderRadius: '16px', border: '1px solid #e5e7eb', overflow: 'hidden', boxShadow: '0 2px 4px rgba(0,0,0,0.02)' }}>
            <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderBottom: '1px solid #e5e7eb', fontSize: '13px', fontWeight: 600, color: '#374151' }}>{title}</div>
            <div style={{ padding: '0' }}>
                {items.length === 0 ? (
                    <div style={{ padding: '24px', textAlign: 'center', color: '#9ca3af', fontSize: '12px' }}>{emptyMsg}</div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '11px' }}>
                        <tbody>
                            {items.map((item: any, i: number) => (
                                <tr key={i} style={{ borderBottom: i === items.length - 1 ? 'none' : '1px solid #f3f4f6' }}>
                                    {columns.map((col: string, j: number) => (
                                        <td key={j} style={{ padding: '10px 16px', color: '#4b5563' }}>
                                            {col === 'aggregateId' || col === 'id' ? (
                                                <code style={{ color: '#6366f1' }}>...{item[col]?.slice(-6)}</code>
                                            ) : col === 'payload' ? (
                                                JSON.stringify(item[col]).slice(0, 20) + '...'
                                            ) : item[col]}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );

    return (
        <div style={{ maxWidth: '1200px', margin: '0 auto', paddingBottom: '100px' }}>
            <header style={{ marginBottom: '40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>TED Demo: Event Sourcing & CQRS</h1>
                    <p style={{ margin: '4px 0 0', color: '#6b7280' }}>Visualizing the separation between historical facts and current state.</p>
                </div>
                <button onClick={runReset} disabled={loading} style={{ padding: '8px 16px', cursor: 'pointer', backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px', color: '#4b5563', fontSize: '13px', fontWeight: 600 }}>
                    ♻️ Reset Demo
                </button>
            </header>

            <div style={{ display: 'grid', gridTemplateColumns: '350px 1fr', gap: '32px', alignItems: 'start' }}>
                
                {/* INTERACTION ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                        <h3 style={{ marginTop: 0, fontSize: '16px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '24px' }}>
                            <IconZap /> 1. Infrastructure Links
                        </h3>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            <div style={{ padding: '16px', backgroundColor: '#f9fafb', borderRadius: '12px', border: '1px solid #f3f4f6' }}>
                                <div style={{ fontSize: '11px', fontWeight: 700, color: '#6b7280', marginBottom: '12px', textTransform: 'uppercase' }}>Master Bus Notification</div>
                                <button onClick={() => toggleProjections('master')} disabled={loading} style={{ width: '100%', padding: '10px', backgroundColor: projectionsEnabled ? '#6366f1' : '#f43f5e', color: 'white', border: 'none', borderRadius: '8px', cursor: 'pointer', fontSize: '13px', fontWeight: 600, display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '8px' }}>
                                    {projectionsEnabled ? <IconCheck /> : <IconAlert />} {projectionsEnabled ? 'ENABLED' : 'STOPPED'}
                                </button>
                            </div>
                            <div style={{ padding: '0 8px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontSize: '13px', fontWeight: 500 }}>User Service</span>
                                    <button onClick={() => toggleProjections('user')} style={{ padding: '6px 12px', backgroundColor: userProjectionsEnabled ? '#eef2ff' : '#fff1f2', color: userProjectionsEnabled ? '#6366f1' : '#f43f5e', border: '1px solid currentColor', borderRadius: '6px', cursor: 'pointer', fontSize: '11px', fontWeight: 700 }}>
                                        {userProjectionsEnabled ? 'ONLINE' : 'FAILING'}
                                    </button>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontSize: '13px', fontWeight: 500 }}>Booking Service</span>
                                    <button onClick={() => toggleProjections('booking')} style={{ padding: '6px 12px', backgroundColor: bookingProjectionsEnabled ? '#eef2ff' : '#fff1f2', color: bookingProjectionsEnabled ? '#6366f1' : '#f43f5e', border: '1px solid currentColor', borderRadius: '6px', cursor: 'pointer', fontSize: '11px', fontWeight: 700 }}>
                                        {bookingProjectionsEnabled ? 'ONLINE' : 'FAILING'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                        <h3 style={{ marginTop: 0, fontSize: '16px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <IconActivity /> 2. Fact Generator
                        </h3>
                        <button onClick={submitRandomBooking} disabled={loading} style={{ width: '100%', marginTop: '16px', padding: '16px', fontSize: '15px', backgroundColor: '#111827', color: 'white', border: 'none', borderRadius: '12px', cursor: 'pointer', fontWeight: 600 }}>
                            + Emite Nuevo Evento
                        </button>
                        {message && <div style={{ marginTop: '16px', fontSize: '13px', color: '#6366f1', textAlign: 'center', fontWeight: 500 }}>{message}</div>}
                    </div>
                </div>

                {/* STATUS ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.5fr', gap: '24px' }}>
                        <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)', display: 'flex', flexDirection: 'column', justifyContent: 'center', textAlign: 'center' }}>
                            <div style={{ fontSize: '12px', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', marginBottom: '8px' }}>Total Facts</div>
                            <div style={{ fontSize: '48px', fontWeight: 800, color: '#6366f1' }}>{stats.events}</div>
                            <div style={{ fontSize: '13px', color: '#6b7280', marginTop: '8px' }}>Immutable Events</div>
                        </div>

                        <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '24px', border: isInconsistent ? '2px solid #6366f1' : '1px solid #e5e7eb', boxShadow: '0 10px 15px -3px rgba(0,0,0,0.05)', position: 'relative' }}>
                            <h3 style={{ marginTop: 0, fontSize: '15px', fontWeight: 600, marginBottom: '20px' }}>Read Consistency</h3>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '14px' }}>
                                    <span style={{ color: '#6b7280' }}>User Projections:</span>
                                    <span style={{ fontWeight: 700, color: stats.users < stats.events ? '#f43f5e' : '#10b981' }}>{stats.users} records</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '14px' }}>
                                    <span style={{ color: '#6b7280' }}>Booking Projections:</span>
                                    <span style={{ fontWeight: 700, color: stats.bookings < stats.events ? '#f43f5e' : '#10b981' }}>{stats.bookings} records</span>
                                </div>
                            </div>
                            
                            {isInconsistent && (
                                <button onClick={runRebuild} disabled={loading} style={{ width: '100%', marginTop: '20px', padding: '12px', backgroundColor: '#6366f1', color: 'white', border: 'none', borderRadius: '8px', cursor: 'pointer', fontSize: '14px', fontWeight: 600 }}>
                                    🛠️ Repair & Replay History
                                </button>
                            )}
                        </div>
                    </div>

                    {/* LIVE TABLES PREVIEW */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <DataList title="Latest Events (Store)" items={events} columns={['eventType', 'aggregateId']} emptyMsg="No events recorded." />
                            <DataList title="Current Checkpoints" items={checkpoints} columns={['projectionName', 'lastEventId']} emptyMsg="No checkpoints active." />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <DataList title="Users (Projection)" items={users} columns={['name', 'email']} emptyMsg="No users projected." />
                            <DataList title="Bookings (Projection)" items={bookings} columns={['id', 'createdAt']} emptyMsg="No bookings projected." />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}