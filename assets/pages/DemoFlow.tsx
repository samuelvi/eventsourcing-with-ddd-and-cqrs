import React, { useState, useEffect } from 'react';
import { v4 as uuidv4 } from 'uuid';

interface Stats {
    events: number;
    users: number;
    bookings: number;
    snapshots: number;
    checkpoints: Record<string, string | null>;
}

// Simple flat icons as SVG components
const IconCheck = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6L9 17l-5-5"/></svg>;
const IconAlert = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>;
const IconActivity = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>;
const IconZap = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>;

export function DemoFlow() {
    const [stats, setStats] = useState<Stats>({ events: 0, users: 0, bookings: 0, snapshots: 0, checkpoints: {} });
    const [projectionsEnabled, setProjectionsEnabled] = useState(true);
    const [userProjectionsEnabled, setUserProjectionsEnabled] = useState(true);
    const [bookingProjectionsEnabled, setBookingProjectionsEnabled] = useState(true);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [showResetModal, setShowResetModal] = useState(false);
    
    const [events, setEvents] = useState<any[]>([]);
    const [users, setUsers] = useState<any[]>([]);
    const [bookings, setBookings] = useState<any[]>([]);
    const [checkpoints, setCheckpoints] = useState<any[]>([]);

    const isInconsistent = stats.events > stats.bookings || stats.events > stats.users;

    const safeFetch = async (url: string) => {
        try {
            const separator = url.includes('?') ? '&' : '?';
            const res = await fetch(`${url}${separator}t=${Date.now()}`);
            if (!res.ok) return [];
            const data = await res.json();
            return data['hydra:member'] || (Array.isArray(data) ? data : []);
        } catch (e) {
            console.error(`Error fetching ${url}:`, e);
            return [];
        }
    };

    const refreshStats = async () => {
        try {
            const res = await fetch('/api/demo/stats');
            if (res.ok) setStats(await res.json());
            
            const statusRes = await fetch('/api/demo/status');
            if (statusRes.ok) {
                const statusData = await statusRes.json();
                setProjectionsEnabled(statusData.projectionsEnabled);
                setUserProjectionsEnabled(statusData.userProjectionsEnabled);
                setBookingProjectionsEnabled(statusData.bookingProjectionsEnabled);
            }

            const [ev, usr, bk, cp] = await Promise.all([
                safeFetch('/api/event-store'),
                safeFetch('/api/users'), 
                safeFetch('/api/bookings?order[createdAt]=desc'),
                safeFetch('/api/checkpoints')
            ]);

            const sortedUsr = [...usr].sort((a, b) => b.id.localeCompare(a.id));

            setEvents(ev);
            setUsers(sortedUsr);
            setBookings(bk);
            setCheckpoints(cp);

        } catch (e) {
            console.error("Refresh loop error", e);
        }
    };

    useEffect(() => {
        refreshStats();
        const interval = setInterval(refreshStats, 2000);
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
            setMessage(`${type.toUpperCase()} link updated`);
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
            if (res.ok) setMessage(`Fact recorded: ${name}`);
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
            const res = await fetch('/api/demo/rebuild', { method: 'POST' });
            if (res.ok) {
                await refreshStats();
                setMessage('Consistency restored');
            } else {
                const data = await res.json();
                setMessage(`Reset failed: ${data.message || 'Unknown error'}`);
            }
        } catch (e) {
            setMessage('Network error during rebuild');
        } finally {
            setLoading(false);
        }
    };

    const executeReset = async () => {
        setShowResetModal(false);
        setLoading(true);
        try {
            const res = await fetch('/api/demo/reset', { method: 'POST' });
            const data = await res.json();
            
            if (res.ok) {
                setMessage('Lab reset complete');
                await refreshStats();
            } else {
                setMessage(`Reset failed: ${data.message || 'Unknown error'}`);
            }
        } catch (e) {
            setMessage('Network error during reset');
        } finally {
            setLoading(false);
        }
    };

    const DataList = ({ title, items, columns, emptyMsg, badge }: any) => (
        <div style={{ backgroundColor: '#fff', borderRadius: '16px', border: '1px solid #e5e7eb', overflow: 'hidden', boxShadow: '0 2px 4px rgba(0,0,0,0.02)' }}>
            <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderBottom: '1px solid #e5e7eb', fontSize: '13px', fontWeight: 600, color: '#374151', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                {title}
                {badge !== undefined && (
                    <span style={{ backgroundColor: '#eef2ff', color: '#6366f1', padding: '2px 8px', borderRadius: '10px', fontSize: '10px' }}>{badge}</span>
                )}
            </div>
            <div style={{ padding: '0', maxHeight: '350px', overflowY: 'auto' }}>
                {items.length === 0 ? (
                    <div style={{ padding: '24px', textAlign: 'center', color: '#9ca3af', fontSize: '12px' }}>{emptyMsg}</div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '11px' }}>
                        <tbody>
                            {items.map((item: any, i: number) => (
                                <tr key={i} style={{ borderBottom: i === items.length - 1 ? 'none' : '1px solid #f3f4f6', backgroundColor: i === 0 ? '#f0f9ff' : 'transparent' }}>
                                    {columns.map((col: string, j: number) => {
                                        let val = item[col];
                                        if (col.includes('.')) {
                                            const parts = col.split('.');
                                            val = item[parts[0]]?.[parts[1]];
                                        }
                                        
                                        return (
                                            <td key={j} style={{ padding: '10px 16px', color: '#4b5563' }}>
                                                {col.includes('Id') || col === 'id' ? (
                                                    <code style={{ color: '#6366f1', fontWeight: 600 }}>...{String(val || '').slice(-6)}</code>
                                                ) : col === 'payload' ? (
                                                    <span title={JSON.stringify(val)}>{JSON.stringify(val).slice(0, 30)}...</span>
                                                ) : col === 'createdAt' || col === 'occurredOn' ? (
                                                    new Date(val).toLocaleTimeString()
                                                ) : col === 'eventType' ? (
                                                    val.split('\\').pop()
                                                ) : val}
                                            </td>
                                        );
                                    })}
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
            {/* Modal Overlay */}
            {showResetModal && (
                <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.4)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000, backdropFilter: 'blur(4px)' }}>
                    <div style={{ backgroundColor: 'white', padding: '32px', borderRadius: '24px', maxWidth: '400px', width: '90%', boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)' }}>
                        <h2 style={{ margin: '0 0 12px', fontSize: '20px', fontWeight: 700, color: '#111827' }}>¿Resetear Laboratorio?</h2>
                        <p style={{ margin: '0 0 24px', color: '#6b7280', fontSize: '14px', lineHeight: 1.5 }}>Esta acción eliminará permanentemente todos los eventos de MongoDB y las proyecciones de PostgreSQL. Se recargarán los catálogos base.</p>
                        <div style={{ display: 'flex', gap: '12px' }}>
                            <button onClick={() => setShowResetModal(false)} style={{ flex: 1, padding: '12px', borderRadius: '12px', border: '1px solid #e5e7eb', backgroundColor: 'white', color: '#374151', cursor: 'pointer', fontWeight: 600, fontSize: '14px' }}>Cancelar</button>
                            <button onClick={executeReset} style={{ flex: 1, padding: '12px', borderRadius: '12px', border: 'none', backgroundColor: '#f43f5e', color: 'white', cursor: 'pointer', fontWeight: 600, fontSize: '14px' }}>Sí, Resetear Todo</button>
                        </div>
                    </div>
                </div>
            )}

            <header style={{ marginBottom: '40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>TED Demo: Event Sourcing & CQRS</h1>
                    <p style={{ margin: '4px 0 0', color: '#6b7280' }}>Enterprise features: Versioning & Snapshots.</p>
                </div>
                <button onClick={() => setShowResetModal(true)} disabled={loading} style={{ padding: '8px 16px', cursor: 'pointer', backgroundColor: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px', color: '#4b5563', fontSize: '13px', fontWeight: 600 }}>
                    Reset Lab
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
                            Generate New Event
                        </button>
                        {message && <div style={{ marginTop: '16px', fontSize: '13px', color: '#6366f1', textAlign: 'center', fontWeight: 500 }}>{message}</div>}
                    </div>
                </div>

                {/* STATUS ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.5fr', gap: '24px' }}>
                        <div style={{ backgroundColor: '#fff', padding: '32px', borderRadius: '24px', border: '1px solid #e5e7eb', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)', textAlign: 'center' }}>
                            <div style={{ fontSize: '12px', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase' }}>Historical Facts</div>
                            <div style={{ fontSize: '48px', fontWeight: 800, color: '#6366f1' }}>{stats.events}</div>
                            <div style={{ fontSize: '11px', color: '#10b981', fontWeight: 600, marginTop: '8px', backgroundColor: '#ecfdf5', padding: '4px 8px', borderRadius: '20px', display: 'inline-block' }}>
                                v1 Schema Active
                            </div>
                        </div>

                        <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '24px', border: isInconsistent ? '2px solid #6366f1' : '1px solid #e5e7eb', boxShadow: '0 10px 15px -3px rgba(0,0,0,0.05)' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '20px' }}>
                                <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 600 }}>Read Consistency</h3>
                                <div style={{ textAlign: 'right' }}>
                                    <div style={{ fontSize: '11px', color: '#9ca3af', textTransform: 'uppercase' }}>Snapshots</div>
                                    <div style={{ fontSize: '18px', fontWeight: 700, color: '#111827' }}>{stats.snapshots}</div>
                                </div>
                            </div>
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
                                    Repair & Sync
                                </button>
                            )}
                        </div>
                    </div>

                    {/* LIVE TABLES */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <DataList title="Latest Events (Store)" items={events} columns={['eventType', 'occurredOn']} emptyMsg="Empty store." badge={events.length} />
                            <DataList title="Active Checkpoints" items={checkpoints} columns={['projectionName', 'lastEventId']} emptyMsg="No checkpoints." badge={checkpoints.length} />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <DataList title="Users (Projection)" items={users} columns={['name', 'email']} emptyMsg="Empty projection." badge={users.length} />
                            <DataList title="Bookings (Projection)" items={bookings} columns={['data.clientName', 'createdAt']} emptyMsg="Empty projection." badge={bookings.length} />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
