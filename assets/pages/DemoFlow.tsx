import React, { useState, useEffect } from 'react';
import { v4 as uuidv4 } from 'uuid';

interface Stats {
    events: number;
    users: number;
    bookings: number;
    snapshots: number;
    checkpoints: Record<string, string | null>;
}

// Professional monotone icons
const IconOn = () => (
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
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
    </svg>
);
const IconOff = () => (
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
        <circle cx="12" cy="12" r="10" />
        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
    </svg>
);
const IconActivity = () => (
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
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
    </svg>
);
const IconCpu = () => (
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
        <rect x="4" y="4" width="16" height="16" rx="2" ry="2" />
        <rect x="9" y="9" width="6" height="6" />
        <line x1="9" y1="1" x2="9" y2="4" />
        <line x1="15" y1="1" x2="15" y2="4" />
        <line x1="9" y1="20" x2="9" y2="23" />
        <line x1="15" y1="20" x2="15" y2="23" />
        <line x1="20" y1="9" x2="23" y2="9" />
        <line x1="20" y1="15" x2="23" y2="15" />
        <line x1="1" y1="9" x2="4" y2="9" />
        <line x1="1" y1="15" x2="4" y2="15" />
    </svg>
);

export function DemoFlow() {
    const [stats, setStats] = useState<Stats>({
        events: 0,
        users: 0,
        bookings: 0,
        snapshots: 0,
        checkpoints: {}
    });
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
        } catch {
            console.error(`Error fetching ${url}:`);
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
        } catch {
            console.error('Refresh loop error');
        }
    };

    useEffect(() => {
        refreshStats();
        const interval = setInterval(refreshStats, 2000);
        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const toggleProjections = async (type: 'master' | 'user' | 'booking') => {
        setLoading(true);
        try {
            const res = await fetch(`/api/demo/toggle/${type}`, { method: 'POST' });
            const data = await res.json();
            if (type === 'master') setProjectionsEnabled(data.projectionsEnabled);
            else if (type === 'user') setUserProjectionsEnabled(data.userProjectionsEnabled);
            else setBookingProjectionsEnabled(data.bookingProjectionsEnabled);
            setMessage(`${type.toUpperCase()} state updated`);
        } catch {
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
                })
            });
            if (res.ok) setMessage(`Fact recorded: ${name}`);
            await refreshStats();
        } catch {
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
                setMessage(`Sync failed: ${data.message || 'Unknown error'}`);
            }
        } catch {
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
        } catch {
            setMessage('Network error during reset');
        } finally {
            setLoading(false);
        }
    };

    const DataList = ({ title, items, columns, emptyMsg, badge }: any) => (
        <div
            style={{
                backgroundColor: '#fff',
                borderRadius: '16px',
                border: '1px solid #e5e7eb',
                overflow: 'hidden',
                boxShadow: '0 2px 4px rgba(0,0,0,0.02)'
            }}
        >
            <div
                style={{
                    padding: '12px 16px',
                    backgroundColor: '#f9fafb',
                    borderBottom: '1px solid #e5e7eb',
                    fontSize: '13px',
                    fontWeight: 600,
                    color: '#374151',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center'
                }}
            >
                {title}
                {badge !== undefined && (
                    <span
                        style={{
                            backgroundColor: '#f3f4f6',
                            color: '#374151',
                            padding: '2px 8px',
                            borderRadius: '10px',
                            fontSize: '10px',
                            border: '1px solid #e5e7eb'
                        }}
                    >
                        {badge}
                    </span>
                )}
            </div>
            <div style={{ padding: '0', maxHeight: '350px', overflowY: 'auto' }}>
                {items.length === 0 ? (
                    <div
                        style={{
                            padding: '24px',
                            textAlign: 'center',
                            color: '#9ca3af',
                            fontSize: '12px'
                        }}
                    >
                        {emptyMsg}
                    </div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '11px' }}>
                        <tbody>
                            {items.map((item: any, i: number) => (
                                <tr
                                    key={i}
                                    style={{
                                        borderBottom:
                                            i === items.length - 1 ? 'none' : '1px solid #f3f4f6',
                                        backgroundColor: i === 0 ? '#f9fafb' : 'transparent'
                                    }}
                                >
                                    {columns.map((col: string, j: number) => {
                                        let val = item[col];
                                        if (col.includes('.')) {
                                            const parts = col.split('.');
                                            val = item[parts[0]]?.[parts[1]];
                                        }

                                        return (
                                            <td
                                                key={j}
                                                style={{ padding: '10px 16px', color: '#4b5563' }}
                                            >
                                                {col.includes('Id') || col === 'id' ? (
                                                    <code
                                                        style={{
                                                            color: '#111827',
                                                            fontWeight: 600
                                                        }}
                                                    >
                                                        ...{String(val || '').slice(-6)}
                                                    </code>
                                                ) : col === 'payload' ? (
                                                    <span title={JSON.stringify(val)}>
                                                        {JSON.stringify(val).slice(0, 30)}...
                                                    </span>
                                                ) : col === 'createdAt' || col === 'occurredOn' ? (
                                                    new Date(val).toLocaleTimeString()
                                                ) : col === 'eventType' ? (
                                                    val.split('\\').pop()
                                                ) : (
                                                    val
                                                )}
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
                <div
                    style={{
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        backgroundColor: 'rgba(0,0,0,0.4)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 1000,
                        backdropFilter: 'blur(4px)'
                    }}
                >
                    <div
                        style={{
                            backgroundColor: 'white',
                            padding: '32px',
                            borderRadius: '24px',
                            maxWidth: '400px',
                            width: '90%',
                            boxShadow:
                                '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)'
                        }}
                    >
                        <h2
                            style={{
                                margin: '0 0 12px',
                                fontSize: '20px',
                                fontWeight: 700,
                                color: '#111827'
                            }}
                        >
                            Reset Architecture State?
                        </h2>
                        <p
                            style={{
                                margin: '0 0 24px',
                                color: '#6b7280',
                                fontSize: '14px',
                                lineHeight: 1.5
                            }}
                        >
                            This will permanently delete all events from MongoDB and read models
                            from PostgreSQL. Base catalogs will be reloaded.
                        </p>
                        <div style={{ display: 'flex', gap: '12px' }}>
                            <button
                                onClick={() => setShowResetModal(false)}
                                style={{
                                    flex: 1,
                                    padding: '12px',
                                    borderRadius: '12px',
                                    border: '1px solid #e5e7eb',
                                    backgroundColor: 'white',
                                    color: '#374151',
                                    cursor: 'pointer',
                                    fontWeight: 600,
                                    fontSize: '14px'
                                }}
                            >
                                Cancel
                            </button>
                            <button
                                onClick={executeReset}
                                style={{
                                    flex: 1,
                                    padding: '12px',
                                    borderRadius: '12px',
                                    border: 'none',
                                    backgroundColor: '#111827',
                                    color: 'white',
                                    cursor: 'pointer',
                                    fontWeight: 600,
                                    fontSize: '14px'
                                }}
                            >
                                Execute Reset
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <header
                style={{
                    marginBottom: '40px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center'
                }}
            >
                <div>
                    <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>
                        Architecture Monitor
                    </h1>
                    <p style={{ margin: '4px 0 0', color: '#6b7280' }}>
                        Real-time consistency tracking between Event Store and Read Models.
                    </p>
                </div>
                <button
                    onClick={() => setShowResetModal(true)}
                    disabled={loading}
                    style={{
                        padding: '8px 16px',
                        cursor: 'pointer',
                        backgroundColor: '#fff',
                        border: '1px solid #e5e7eb',
                        borderRadius: '8px',
                        color: '#4b5563',
                        fontSize: '13px',
                        fontWeight: 600
                    }}
                >
                    Reset Lab
                </button>
            </header>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '350px 1fr',
                    gap: '32px',
                    alignItems: 'start'
                }}
            >
                {/* INTERACTION ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    <div
                        style={{
                            backgroundColor: '#fff',
                            padding: '32px',
                            borderRadius: '24px',
                            border: '1px solid #e5e7eb',
                            boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)'
                        }}
                    >
                        <h3
                            style={{
                                marginTop: 0,
                                fontSize: '16px',
                                fontWeight: 600,
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                                marginBottom: '24px'
                            }}
                        >
                            <IconCpu /> Infrastructure Control
                        </h3>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            <div
                                style={{
                                    padding: '16px',
                                    backgroundColor: '#f9fafb',
                                    borderRadius: '12px',
                                    border: '1px solid #f3f4f6'
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: '11px',
                                        fontWeight: 700,
                                        color: '#6b7280',
                                        marginBottom: '12px',
                                        textTransform: 'uppercase'
                                    }}
                                >
                                    Message Bus Status
                                </div>
                                <button
                                    onClick={() => toggleProjections('master')}
                                    disabled={loading}
                                    style={{
                                        width: '100%',
                                        padding: '10px',
                                        backgroundColor: projectionsEnabled ? '#111827' : '#f3f4f6',
                                        color: projectionsEnabled ? 'white' : '#9ca3af',
                                        border: 'none',
                                        borderRadius: '8px',
                                        cursor: 'pointer',
                                        fontSize: '13px',
                                        fontWeight: 600,
                                        display: 'flex',
                                        justifyContent: 'center',
                                        alignItems: 'center',
                                        gap: '8px'
                                    }}
                                >
                                    {projectionsEnabled ? <IconOn /> : <IconOff />}{' '}
                                    {projectionsEnabled ? 'ACTIVE' : 'PAUSED'}
                                </button>
                            </div>
                            <div
                                style={{
                                    padding: '0 8px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '12px'
                                }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center'
                                    }}
                                >
                                    <span style={{ fontSize: '13px', fontWeight: 500 }}>
                                        User Projection
                                    </span>
                                    <button
                                        onClick={() => toggleProjections('user')}
                                        style={{
                                            padding: '6px 12px',
                                            backgroundColor: userProjectionsEnabled
                                                ? '#f9fafb'
                                                : '#fff1f2',
                                            color: userProjectionsEnabled ? '#111827' : '#f43f5e',
                                            border: '1px solid currentColor',
                                            borderRadius: '6px',
                                            cursor: 'pointer',
                                            fontSize: '11px',
                                            fontWeight: 700
                                        }}
                                    >
                                        {userProjectionsEnabled ? 'ONLINE' : 'OFFLINE'}
                                    </button>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center'
                                    }}
                                >
                                    <span style={{ fontSize: '13px', fontWeight: 500 }}>
                                        Booking Projection
                                    </span>
                                    <button
                                        onClick={() => toggleProjections('booking')}
                                        style={{
                                            padding: '6px 12px',
                                            backgroundColor: bookingProjectionsEnabled
                                                ? '#f9fafb'
                                                : '#fff1f2',
                                            color: bookingProjectionsEnabled
                                                ? '#111827'
                                                : '#f43f5e',
                                            border: '1px solid currentColor',
                                            borderRadius: '6px',
                                            cursor: 'pointer',
                                            fontSize: '11px',
                                            fontWeight: 700
                                        }}
                                    >
                                        {bookingProjectionsEnabled ? 'ONLINE' : 'OFFLINE'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        style={{
                            backgroundColor: '#fff',
                            padding: '32px',
                            borderRadius: '24px',
                            border: '1px solid #e5e7eb',
                            boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)'
                        }}
                    >
                        <h3
                            style={{
                                marginTop: 0,
                                fontSize: '16px',
                                fontWeight: 600,
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px'
                            }}
                        >
                            <IconActivity /> Event Simulation
                        </h3>
                        <button
                            onClick={submitRandomBooking}
                            disabled={loading}
                            style={{
                                width: '100%',
                                marginTop: '16px',
                                padding: '16px',
                                fontSize: '15px',
                                backgroundColor: '#111827',
                                color: 'white',
                                border: 'none',
                                borderRadius: '12px',
                                cursor: 'pointer',
                                fontWeight: 600
                            }}
                        >
                            Generate New Event
                        </button>
                        {message && (
                            <div
                                style={{
                                    marginTop: '16px',
                                    fontSize: '13px',
                                    color: '#111827',
                                    textAlign: 'center',
                                    fontWeight: 500
                                }}
                            >
                                {message}
                            </div>
                        )}
                    </div>
                </div>

                {/* STATUS ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.5fr', gap: '24px' }}>
                        <div
                            style={{
                                backgroundColor: '#fff',
                                padding: '32px',
                                borderRadius: '24px',
                                border: '1px solid #e5e7eb',
                                boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)',
                                textAlign: 'center'
                            }}
                        >
                            <div
                                style={{
                                    fontSize: '12px',
                                    fontWeight: 600,
                                    color: '#9ca3af',
                                    textTransform: 'uppercase'
                                }}
                            >
                                Historical Facts
                            </div>
                            <div style={{ fontSize: '48px', fontWeight: 800, color: '#111827' }}>
                                {stats.events}
                            </div>
                            <div
                                style={{
                                    fontSize: '11px',
                                    color: '#4b5563',
                                    fontWeight: 600,
                                    marginTop: '8px',
                                    backgroundColor: '#f3f4f6',
                                    padding: '4px 12px',
                                    borderRadius: '20px',
                                    display: 'inline-block',
                                    border: '1px solid #e5e7eb'
                                }}
                            >
                                Schema v1
                            </div>
                        </div>

                        <div
                            style={{
                                backgroundColor: '#fff',
                                padding: '24px',
                                borderRadius: '24px',
                                border: isInconsistent ? '2px solid #111827' : '1px solid #e5e7eb',
                                boxShadow: '0 10px 15px -3px rgba(0,0,0,0.05)'
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'flex-start',
                                    marginBottom: '20px'
                                }}
                            >
                                <h3 style={{ margin: 0, fontSize: '15px', fontWeight: 600 }}>
                                    Read Consistency
                                </h3>
                                <div style={{ textAlign: 'right' }}>
                                    <div
                                        style={{
                                            fontSize: '11px',
                                            color: '#9ca3af',
                                            textTransform: 'uppercase'
                                        }}
                                    >
                                        Snapshots
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '18px',
                                            fontWeight: 700,
                                            color: '#111827'
                                        }}
                                    >
                                        {stats.snapshots}
                                    </div>
                                </div>
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        fontSize: '14px'
                                    }}
                                >
                                    <span style={{ color: '#6b7280' }}>User Records:</span>
                                    <span
                                        style={{
                                            fontWeight: 700,
                                            color:
                                                stats.users < stats.events ? '#f43f5e' : '#111827'
                                        }}
                                    >
                                        {stats.users}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        fontSize: '14px'
                                    }}
                                >
                                    <span style={{ color: '#6b7280' }}>Booking Records:</span>
                                    <span
                                        style={{
                                            fontWeight: 700,
                                            color:
                                                stats.bookings < stats.events
                                                    ? '#f43f5e'
                                                    : '#111827'
                                        }}
                                    >
                                        {stats.bookings}
                                    </span>
                                </div>
                            </div>
                            {isInconsistent && (
                                <button
                                    onClick={runRebuild}
                                    disabled={loading}
                                    style={{
                                        width: '100%',
                                        marginTop: '20px',
                                        padding: '12px',
                                        backgroundColor: '#111827',
                                        color: 'white',
                                        border: 'none',
                                        borderRadius: '8px',
                                        cursor: 'pointer',
                                        fontSize: '14px',
                                        fontWeight: 600
                                    }}
                                >
                                    Repair & Sync
                                </button>
                            )}
                        </div>
                    </div>

                    {/* LIVE TABLES */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <DataList
                                title="Event Store"
                                items={events}
                                columns={['eventType', 'occurredOn']}
                                emptyMsg="No events."
                                badge={events.length}
                            />
                            <DataList
                                title="Checkpoints"
                                items={checkpoints}
                                columns={['projectionName', 'lastEventId']}
                                emptyMsg="No checkpoints."
                                badge={checkpoints.length}
                            />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <DataList
                                title="Users Projection"
                                items={users}
                                columns={['name', 'email']}
                                emptyMsg="No users."
                                badge={users.length}
                            />
                            <DataList
                                title="Bookings Projection"
                                items={bookings}
                                columns={['data.clientName', 'createdAt']}
                                emptyMsg="No bookings."
                                badge={bookings.length}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
