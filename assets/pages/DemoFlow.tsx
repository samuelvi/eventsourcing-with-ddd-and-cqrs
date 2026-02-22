import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { v4 as uuidv4 } from 'uuid';

// Warm professional icons
const IconOn = () => (
    <svg
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2.5"
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
        strokeWidth="2.5"
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
    const queryClient = useQueryClient();
    const [toast, setToast] = useState<{ text: string; type: 'info' | 'error' } | null>(null);
    const [showResetModal, setShowResetModal] = useState(false);

    const showToast = (text: string, type: 'info' | 'error' = 'info') => {
        setToast({ text, type });
        setTimeout(() => setToast(null), 4000);
    };

    // --- Queries ---
    const {
        data: stats = {
            events: 0,
            users: 0,
            bookings: 0,
            snapshots: 0,
            checkpoints: {},
            queue: { async: 0, failed: 0 }
        }
    } = useQuery({
        queryKey: ['stats'],
        queryFn: async () => {
            const res = await fetch('/api/demo/stats');
            if (!res.ok) throw new Error('Stats error');
            return res.json();
        },
        refetchInterval: 2000
    });

    const {
        data: status = {
            projectionsEnabled: true,
            userProjectionsEnabled: true,
            bookingProjectionsEnabled: true,
            userAddressSchemaEnabled: false
        }
    } = useQuery({
        queryKey: ['status'],
        queryFn: async () => {
            const res = await fetch('/api/demo/status');
            if (!res.ok) throw new Error('Status error');
            return res.json();
        },
        refetchInterval: 2000
    });

    const safeFetch = async (url: string) => {
        try {
            const separator = url.includes('?') ? '&' : '?';
            const res = await fetch(`${url}${separator}t=${Date.now()}`);
            if (!res.ok) return [];
            const data = await res.json();
            return data['hydra:member'] || (Array.isArray(data) ? data : []);
        } catch {
            return [];
        }
    };

    const { data: events = [] } = useQuery<Record<string, unknown>[]>({
        queryKey: ['events'],
        queryFn: () => safeFetch('/api/event-store'),
        refetchInterval: 2000
    });

    const { data: users = [] } = useQuery<Record<string, unknown>[]>({
        queryKey: ['users'],
        queryFn: () => safeFetch('/api/users'),
        refetchInterval: 2000
    });

    const { data: bookings = [] } = useQuery<Record<string, unknown>[]>({
        queryKey: ['bookings'],
        queryFn: () => safeFetch('/api/bookings?order[createdAt]=desc'),
        refetchInterval: 2000
    });

    const { data: checkpoints = [] } = useQuery<Record<string, unknown>[]>({
        queryKey: ['checkpoints'],
        queryFn: () => safeFetch('/api/checkpoints'),
        refetchInterval: 2000
    });

    const eventTypeCounts = events.reduce<Record<string, number>>((acc, event) => {
        const rawType = String(event.eventType || '');
        const eventType = rawType.split('\\').pop() || rawType;
        acc[eventType] = (acc[eventType] || 0) + 1;
        return acc;
    }, {});

    const expectedUserRecords = eventTypeCounts.UserRegistered || 0;
    const expectedBookingRecords = eventTypeCounts.BookingWizardCompleted || 0;
    const isInconsistent =
        stats.users < expectedUserRecords || stats.bookings < expectedBookingRecords;

    // --- Mutations ---
    const toggleMutation = useMutation({
        mutationFn: async (type: 'master' | 'user' | 'booking') => {
            await fetch(`/api/demo/toggle/${type}`, { method: 'POST' });
            return type;
        },
        onSuccess: (type) => {
            queryClient.invalidateQueries({ queryKey: ['status'] });
            showToast(`${type.toUpperCase()} updated successfully`);
        },
        onError: () => showToast('Update failed', 'error')
    });

    const createBookingMutation = useMutation({
        mutationFn: async (payload: Record<string, unknown>) => {
            const res = await fetch('/api/booking-wizard', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error('Failed');
        },
        onSuccess: () => {
            queryClient.invalidateQueries();
            showToast('New fact recorded in history');
        },
        onError: () => showToast('Creation failed', 'error')
    });

    const createUserMutation = useMutation({
        mutationFn: async (payload: Record<string, unknown>) => {
            const res = await fetch('/api/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' },
                body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error('Failed');
        },
        onSuccess: () => {
            queryClient.invalidateQueries();
            showToast('User registration recorded');
        },
        onError: () => showToast('Registration failed', 'error')
    });

    const rebuildFromMongoMutation = useMutation({
        mutationFn: async () => {
            const res = await fetch('/api/demo/rebuild-from-mongo', { method: 'POST' });
            if (!res.ok) throw new Error('Rebuild failed');
        },
        onSuccess: () => {
            queryClient.invalidateQueries();
            showToast('SQL models synchronized with history');
        },
        onError: () => showToast('Sync failed', 'error')
    });

    const clearTransactionalMutation = useMutation({
        mutationFn: async () => {
            const res = await fetch('/api/demo/clear-transactional', { method: 'POST' });
            if (!res.ok) throw new Error('Clear failed');
        },
        onSuccess: () => {
            queryClient.invalidateQueries();
            showToast('SQL projection tables cleared');
        },
        onError: () => showToast('Clear failed', 'error')
    });

    const resetMutation = useMutation({
        mutationFn: async () => {
            await fetch('/api/demo/reset', { method: 'POST' });
        },
        onSuccess: () => {
            queryClient.invalidateQueries();
            showToast('Lab reset complete');
            setShowResetModal(false);
        },
        onError: () => showToast('Reset failed', 'error')
    });

    const evolveUserSchemaMutation = useMutation({
        mutationFn: async () => {
            const res = await fetch('/api/demo/evolve-user-schema', { method: 'POST' });
            if (!res.ok) throw new Error('Schema evolution failed');
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['status'] });
            queryClient.invalidateQueries({ queryKey: ['users'] });
            showToast('User schema v2 enabled (address)');
        },
        onError: () => showToast('Schema evolution failed', 'error')
    });

    // --- Actions ---
    const toggleProjections = (type: 'master' | 'user' | 'booking') => toggleMutation.mutate(type);
    const submitRandomBooking = () =>
        createBookingMutation.mutate({
            bookingId: uuidv4(),
            pax: Math.floor(Math.random() * 5) + 1,
            budget: 100,
            clientName: `Demo ${Math.floor(Math.random() * 1000)}`,
            clientEmail: `client${Math.floor(Math.random() * 1000)}@test.com`
        });
    const registerRandomUser = () =>
        createUserMutation.mutate({
            id: uuidv4(),
            name: `Pure User ${Math.floor(Math.random() * 1000)}`,
            email: `user${Math.floor(Math.random() * 1000)}@pure.com`,
            ...(status.userAddressSchemaEnabled
                ? {
                      address: `${Math.floor(Math.random() * 200) + 1} Demo Street, ES`
                  }
                : {})
        });
    const runRebuild = () => {
        showToast('Syncing history...');
        rebuildFromMongoMutation.mutate();
    };
    const clearTransactionalData = () => {
        showToast('Clearing read models...');
        clearTransactionalMutation.mutate();
    };
    const executeReset = () => resetMutation.mutate();
    const evolveUserSchema = () => evolveUserSchemaMutation.mutate();

    const projectionsEnabled = status.projectionsEnabled;
    const userProjectionsEnabled = status.userProjectionsEnabled;
    const bookingProjectionsEnabled = status.bookingProjectionsEnabled;
    const userAddressSchemaEnabled = status.userAddressSchemaEnabled;
    const sortedUsers = [...users].sort((a, b) =>
        String(b.id || '').localeCompare(String(a.id || ''))
    );
    const loading =
        toggleMutation.isPending ||
        createBookingMutation.isPending ||
        createUserMutation.isPending ||
        rebuildFromMongoMutation.isPending ||
        clearTransactionalMutation.isPending ||
        evolveUserSchemaMutation.isPending ||
        resetMutation.isPending;

    const DataList = ({
        title,
        items,
        columns,
        emptyMsg,
        badge
    }: {
        title: string;
        items: Record<string, unknown>[];
        columns: string[];
        emptyMsg: string;
        badge?: number;
    }) => (
        <div
            style={{
                backgroundColor: '#fff',
                borderRadius: '20px',
                border: '1px solid #e7e5e4',
                overflow: 'hidden',
                boxShadow: '0 4px 6px -1px rgba(0,0,0,0.03)'
            }}
        >
            <div
                style={{
                    padding: '14px 20px',
                    backgroundColor: '#fafaf9',
                    borderBottom: '1px solid #e7e5e4',
                    fontSize: '13px',
                    fontWeight: 700,
                    color: '#44403c',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center'
                }}
            >
                {title}
                {badge !== undefined && (
                    <span
                        style={{
                            backgroundColor: '#f5f5f4',
                            color: '#78716c',
                            padding: '2px 10px',
                            borderRadius: '12px',
                            fontSize: '11px',
                            border: '1px solid #e7e5e4'
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
                            padding: '32px',
                            textAlign: 'center',
                            color: '#a8a29e',
                            fontSize: '13px'
                        }}
                    >
                        {emptyMsg}
                    </div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12px' }}>
                        <tbody>
                            {items.map((item, i) => (
                                <tr
                                    key={i}
                                    style={{
                                        borderBottom:
                                            i === items.length - 1 ? 'none' : '1px solid #f5f5f4',
                                        backgroundColor: i === 0 ? '#fffbeb' : 'transparent'
                                    }}
                                >
                                    {columns.map((col, j) => {
                                        let val: any = item[col];
                                        if (col.includes('.')) {
                                            const [p1, p2] = col.split('.');
                                            val = (item[p1] as any)?.[p2];
                                        }
                                        return (
                                            <td
                                                key={j}
                                                style={{ padding: '12px 20px', color: '#57534e' }}
                                            >
                                                {col.includes('Id') || col === 'id' ? (
                                                    <code
                                                        style={{
                                                            color: '#b45309',
                                                            fontWeight: 700
                                                        }}
                                                    >
                                                        ...{String(val || '').slice(-6)}
                                                    </code>
                                                ) : col === 'payload' ? (
                                                    <span title={JSON.stringify(val)}>
                                                        {JSON.stringify(val).slice(0, 30)}...
                                                    </span>
                                                ) : col === 'createdAt' || col === 'occurredOn' ? (
                                                    new Date(String(val)).toLocaleTimeString()
                                                ) : col === 'eventType' ? (
                                                    <span style={{ fontWeight: 600 }}>
                                                        {String(val).split('\\').pop()}
                                                    </span>
                                                ) : val === null || val === undefined ? (
                                                    '—'
                                                ) : (
                                                    String(val)
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
        <div style={{ maxWidth: '1300px', margin: '0 auto', paddingBottom: '100px' }}>
            {/* Elegant Toast */}
            {toast && (
                <div
                    style={{
                        position: 'fixed',
                        bottom: '40px',
                        left: '50%',
                        transform: 'translateX(-50%)',
                        background:
                            toast.type === 'error'
                                ? 'linear-gradient(135deg, #ef4444 0%, #991b1b 100%)'
                                : 'linear-gradient(135deg, #b45309 0%, #78350f 100%)',
                        color: 'white',
                        padding: '16px 32px',
                        borderRadius: '16px',
                        zIndex: 2000,
                        fontSize: '15px',
                        fontWeight: 700,
                        boxShadow: '0 25px 50px -12px rgba(0,0,0,0.3)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '14px',
                        border: '1px solid rgba(255,255,255,0.1)',
                        animation: 'fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1)'
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            padding: '6px',
                            backgroundColor: 'rgba(255,255,255,0.2)',
                            borderRadius: '50%'
                        }}
                    >
                        {toast.type === 'error' ? <IconOff /> : <IconOn />}
                    </div>
                    {toast.text}
                </div>
            )}

            {/* Warm Modal */}
            {showResetModal && (
                <div
                    style={{
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        backgroundColor: 'rgba(28, 25, 23, 0.7)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 1000,
                        backdropFilter: 'blur(10px)'
                    }}
                >
                    <div
                        style={{
                            backgroundColor: 'white',
                            padding: '48px',
                            borderRadius: '32px',
                            maxWidth: '460px',
                            width: '90%',
                            boxShadow: '0 25px 50px -12px rgba(0,0,0,0.5)',
                            border: '1px solid #e7e5e4'
                        }}
                    >
                        <h2
                            style={{
                                margin: '0 0 16px',
                                fontSize: '26px',
                                fontWeight: 900,
                                color: '#1c1917',
                                letterSpacing: '-0.03em'
                            }}
                        >
                            System Hard Reset
                        </h2>
                        <p
                            style={{
                                margin: '0 0 32px',
                                color: '#57534e',
                                fontSize: '17px',
                                lineHeight: 1.6
                            }}
                        >
                            Permanently wipe all facts and projections. This action is destructive.
                        </p>
                        <div style={{ display: 'flex', gap: '16px' }}>
                            <button
                                onClick={() => setShowResetModal(false)}
                                style={{
                                    flex: 1,
                                    padding: '16px',
                                    borderRadius: '14px',
                                    border: '1px solid #e7e5e4',
                                    backgroundColor: '#fafaf9',
                                    color: '#57534e',
                                    cursor: 'pointer',
                                    fontWeight: 700,
                                    fontSize: '15px'
                                }}
                            >
                                Cancel
                            </button>
                            <button
                                onClick={executeReset}
                                style={{
                                    flex: 1,
                                    padding: '16px',
                                    borderRadius: '14px',
                                    border: 'none',
                                    backgroundColor: '#ef4444',
                                    color: 'white',
                                    cursor: 'pointer',
                                    fontWeight: 700,
                                    fontSize: '15px',
                                    boxShadow: '0 10px 15px -3px rgba(239, 68, 68, 0.3)'
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
                    marginBottom: '56px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center'
                }}
            >
                <div>
                    <h1
                        style={{
                            margin: 0,
                            fontSize: '36px',
                            fontWeight: 900,
                            color: '#1c1917',
                            letterSpacing: '-0.04em'
                        }}
                    >
                        Architecture Monitor
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#78716c', fontSize: '18px' }}>
                        Real-time flow through the hybrid storage engine.
                    </p>
                </div>
                <button
                    onClick={() => setShowResetModal(true)}
                    disabled={loading}
                    style={{
                        padding: '12px 24px',
                        cursor: 'pointer',
                        backgroundColor: '#fff',
                        border: '1px solid #e7e5e4',
                        borderRadius: '14px',
                        color: '#78716c',
                        fontSize: '14px',
                        fontWeight: 700,
                        boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                    }}
                >
                    Reset Lab
                </button>
            </header>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '400px 1fr',
                    gap: '48px',
                    alignItems: 'start'
                }}
            >
                {/* INTERACTION ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                    <div
                        style={{
                            backgroundColor: '#fff',
                            padding: '36px',
                            borderRadius: '32px',
                            border: '1px solid #e7e5e4',
                            boxShadow: '0 20px 25px -5px rgba(0,0,0,0.03)'
                        }}
                    >
                        <h3
                            style={{
                                marginTop: 0,
                                fontSize: '19px',
                                fontWeight: 800,
                                color: '#1c1917',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '12px',
                                marginBottom: '32px'
                            }}
                        >
                            <div style={{ color: '#b45309' }}>
                                <IconCpu />
                            </div>{' '}
                            Infrastructure Control
                        </h3>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            <div
                                style={{
                                    padding: '24px',
                                    backgroundColor: '#fffbeb',
                                    borderRadius: '20px',
                                    border: '1px solid #fef3c7'
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: '12px',
                                        fontWeight: 800,
                                        color: '#92400e',
                                        marginBottom: '16px',
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.1em'
                                    }}
                                >
                                    Message Bus Status
                                </div>
                                <button
                                    onClick={() => toggleProjections('master')}
                                    disabled={loading}
                                    style={{
                                        width: '100%',
                                        padding: '14px',
                                        background: projectionsEnabled
                                            ? 'linear-gradient(135deg, #b45309 0%, #92400e 100%)'
                                            : '#f5f5f4',
                                        color: projectionsEnabled ? 'white' : '#a8a29e',
                                        border: 'none',
                                        borderRadius: '12px',
                                        cursor: 'pointer',
                                        fontSize: '15px',
                                        fontWeight: 800,
                                        display: 'flex',
                                        justifyContent: 'center',
                                        alignItems: 'center',
                                        gap: '12px',
                                        boxShadow: projectionsEnabled
                                            ? '0 10px 15px -3px rgba(180, 83, 9, 0.3)'
                                            : 'none'
                                    }}
                                >
                                    {projectionsEnabled ? <IconOn /> : <IconOff />}{' '}
                                    {projectionsEnabled ? 'BUS ACTIVE' : 'BUS PAUSED'}
                                </button>
                            </div>
                            <div
                                style={{
                                    padding: '0 8px',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '20px'
                                }}
                            >
                                {[
                                    {
                                        label: 'User Projection',
                                        active: userProjectionsEnabled,
                                        type: 'user'
                                    },
                                    {
                                        label: 'Booking Projection',
                                        active: bookingProjectionsEnabled,
                                        type: 'booking'
                                    }
                                ].map((p, idx) => (
                                    <div
                                        key={idx}
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center'
                                        }}
                                    >
                                        <span
                                            style={{
                                                fontSize: '15px',
                                                fontWeight: 700,
                                                color: '#44403c'
                                            }}
                                        >
                                            {p.label}
                                        </span>
                                        <button
                                            onClick={() => toggleProjections(p.type as any)}
                                            aria-label={p.label}
                                            style={{
                                                padding: '10px 18px',
                                                backgroundColor: p.active ? '#ecfdf5' : '#fff1f2',
                                                color: p.active ? '#059669' : '#e11d48',
                                                border: 'none',
                                                borderRadius: '10px',
                                                cursor: 'pointer',
                                                fontSize: '12px',
                                                fontWeight: 800
                                            }}
                                        >
                                            {p.active ? 'ONLINE' : 'OFFLINE'}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div
                        style={{
                            backgroundColor: '#fff',
                            padding: '36px',
                            borderRadius: '32px',
                            border: '1px solid #e7e5e4',
                            boxShadow: '0 20px 25px -5px rgba(0,0,0,0.03)'
                        }}
                    >
                        <h3
                            style={{
                                marginTop: 0,
                                fontSize: '19px',
                                fontWeight: 800,
                                color: '#1c1917',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '12px'
                            }}
                        >
                            <div style={{ color: '#b45309' }}>
                                <IconActivity />
                            </div>{' '}
                            Event Simulation
                        </h3>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            <button
                                onClick={registerRandomUser}
                                disabled={loading}
                                style={{
                                    width: '100%',
                                    marginTop: '24px',
                                    padding: '16px',
                                    fontSize: '15px',
                                    backgroundColor: '#fff',
                                    color: '#1c1917',
                                    border: '1px solid #e7e5e4',
                                    borderRadius: '14px',
                                    cursor: 'pointer',
                                    fontWeight: 700,
                                    boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                                }}
                            >
                                Register User Only
                            </button>
                            <button
                                onClick={evolveUserSchema}
                                disabled={loading || userAddressSchemaEnabled}
                                style={{
                                    width: '100%',
                                    padding: '14px',
                                    fontSize: '14px',
                                    backgroundColor: userAddressSchemaEnabled
                                        ? '#f5f5f4'
                                        : '#fffbeb',
                                    color: userAddressSchemaEnabled ? '#a8a29e' : '#92400e',
                                    border: '1px solid #fde68a',
                                    borderRadius: '14px',
                                    cursor: userAddressSchemaEnabled ? 'default' : 'pointer',
                                    fontWeight: 700
                                }}
                            >
                                {userAddressSchemaEnabled
                                    ? 'User Schema v2 Enabled (address)'
                                    : 'Simulate User Schema Change: add address'}
                            </button>
                            <button
                                onClick={submitRandomBooking}
                                disabled={loading}
                                style={{
                                    width: '100%',
                                    padding: '18px',
                                    fontSize: '16px',
                                    background: 'linear-gradient(135deg, #1c1917 0%, #44403c 100%)',
                                    color: 'white',
                                    border: 'none',
                                    borderRadius: '14px',
                                    cursor: 'pointer',
                                    fontWeight: 800,
                                    boxShadow: '0 10px 15px -3px rgba(0,0,0,0.2)'
                                }}
                            >
                                Generate New Booking
                            </button>
                        </div>
                    </div>

                    <div
                        style={{
                            backgroundColor: '#fff',
                            padding: '36px',
                            borderRadius: '32px',
                            border: '1px solid #e7e5e4',
                            boxShadow: '0 20px 25px -5px rgba(0,0,0,0.03)'
                        }}
                    >
                        <h3
                            style={{
                                marginTop: 0,
                                marginBottom: '12px',
                                fontSize: '19px',
                                fontWeight: 800,
                                color: '#1c1917'
                            }}
                        >
                            PostgreSQL Recovery
                        </h3>
                        <p
                            style={{
                                margin: '0 0 28px',
                                color: '#78716c',
                                fontSize: '15px',
                                lineHeight: 1.6
                            }}
                        >
                            Sync read models from event history.
                        </p>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                            <button
                                onClick={clearTransactionalData}
                                disabled={loading}
                                style={{
                                    width: '100%',
                                    padding: '16px',
                                    fontSize: '15px',
                                    backgroundColor: '#fafaf9',
                                    color: '#44403c',
                                    border: '1px solid #e7e5e4',
                                    borderRadius: '14px',
                                    cursor: 'pointer',
                                    fontWeight: 700
                                }}
                            >
                                Clear Transactional Data (Postgres)
                            </button>
                            <button
                                onClick={runRebuild}
                                disabled={loading}
                                style={{
                                    width: '100%',
                                    padding: '16px',
                                    fontSize: '15px',
                                    background: 'linear-gradient(135deg, #b45309 0%, #92400e 100%)',
                                    color: 'white',
                                    border: 'none',
                                    borderRadius: '14px',
                                    cursor: 'pointer',
                                    fontWeight: 800,
                                    boxShadow: '0 10px 15px -3px rgba(180, 83, 9, 0.2)'
                                }}
                            >
                                Rebuild from Mongo (Events)
                            </button>
                        </div>
                    </div>
                </div>

                {/* STATUS ZONE */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '48px' }}>
                    <div
                        style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '28px' }}
                    >
                        <div
                            style={{
                                background: 'linear-gradient(135deg, #ffffff 0%, #fffbeb 100%)',
                                padding: '40px 28px',
                                borderRadius: '32px',
                                border: '1px solid #fef3c7',
                                boxShadow: '0 20px 25px -5px rgba(0,0,0,0.03)',
                                textAlign: 'center',
                                display: 'flex',
                                flexDirection: 'column',
                                justifyContent: 'center'
                            }}
                        >
                            <div
                                style={{
                                    fontSize: '13px',
                                    fontWeight: 800,
                                    color: '#92400e',
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.1em'
                                }}
                            >
                                Historical Facts
                            </div>
                            <div
                                style={{
                                    fontSize: '56px',
                                    fontWeight: 950,
                                    color: '#1c1917',
                                    margin: '8px 0',
                                    letterSpacing: '-0.05em'
                                }}
                            >
                                {stats.events}
                            </div>
                            <div
                                style={{
                                    fontSize: '12px',
                                    color: '#b45309',
                                    fontWeight: 800,
                                    backgroundColor: '#fef3c7',
                                    padding: '6px 18px',
                                    borderRadius: '24px',
                                    display: 'inline-block',
                                    border: '1px solid #fde68a',
                                    alignSelf: 'center'
                                }}
                            >
                                MongoDB Store
                            </div>
                        </div>

                        <div
                            style={{
                                backgroundColor: '#fff',
                                padding: '40px 28px',
                                borderRadius: '32px',
                                border: '1px solid #e7e5e4',
                                boxShadow: '0 20px 25px -5px rgba(0,0,0,0.03)',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: '20px'
                            }}
                        >
                            <div
                                style={{
                                    fontSize: '13px',
                                    fontWeight: 800,
                                    color: '#78716c',
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.1em'
                                }}
                            >
                                Messaging Bridge
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center'
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: '15px',
                                            color: '#57534e',
                                            fontWeight: 600
                                        }}
                                    >
                                        Queue Pending:
                                    </span>
                                    <span
                                        style={{
                                            fontSize: '22px',
                                            fontWeight: 900,
                                            color: stats.queue?.async > 0 ? '#b45309' : '#1c1917'
                                        }}
                                    >
                                        {stats.queue?.async ?? 0}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center'
                                    }}
                                >
                                    <span
                                        style={{
                                            fontSize: '15px',
                                            color: '#57534e',
                                            fontWeight: 600
                                        }}
                                    >
                                        Failed (DLQ):
                                    </span>
                                    <span
                                        style={{
                                            fontSize: '22px',
                                            fontWeight: 900,
                                            color: stats.queue?.failed > 0 ? '#ef4444' : '#1c1917',
                                            backgroundColor:
                                                stats.queue?.failed > 0 ? '#fff1f2' : 'transparent',
                                            padding: stats.queue?.failed > 0 ? '4px 12px' : '0',
                                            borderRadius: '10px'
                                        }}
                                    >
                                        {stats.queue?.failed ?? 0}
                                    </span>
                                </div>
                            </div>
                            <div
                                style={{
                                    fontSize: '12px',
                                    color: '#78716c',
                                    fontWeight: 800,
                                    backgroundColor: '#f5f5f4',
                                    padding: '6px 18px',
                                    borderRadius: '24px',
                                    display: 'inline-block',
                                    border: '1px solid #e7e5e4',
                                    alignSelf: 'flex-start'
                                }}
                            >
                                PostgreSQL Queue
                            </div>
                        </div>

                        <div
                            style={{
                                backgroundColor: '#fff',
                                padding: '40px 28px',
                                borderRadius: '32px',
                                border: isInconsistent ? '3px solid #b45309' : '1px solid #e7e5e4',
                                boxShadow: '0 25px 50px -12px rgba(0,0,0,0.06)',
                                transition: 'all 0.3s'
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
                                <h3
                                    style={{
                                        margin: 0,
                                        fontSize: '17px',
                                        fontWeight: 800,
                                        color: '#1c1917'
                                    }}
                                >
                                    Read Models
                                </h3>
                                <div style={{ textAlign: 'right' }}>
                                    <div
                                        style={{
                                            fontSize: '10px',
                                            color: '#a8a29e',
                                            fontWeight: 800
                                        }}
                                    >
                                        SNAPS
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '18px',
                                            fontWeight: 900,
                                            color: '#1c1917'
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
                                    <span style={{ color: '#78716c', fontWeight: 600 }}>
                                        Users:
                                    </span>
                                    <span
                                        style={{
                                            fontWeight: 900,
                                            color:
                                                stats.users < expectedUserRecords
                                                    ? '#ef4444'
                                                    : '#1c1917'
                                        }}
                                    >
                                        {stats.users} / {expectedUserRecords}
                                    </span>
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        fontSize: '14px'
                                    }}
                                >
                                    <span style={{ color: '#78716c', fontWeight: 600 }}>
                                        Bookings:
                                    </span>
                                    <span
                                        style={{
                                            fontWeight: 900,
                                            color:
                                                stats.bookings < expectedBookingRecords
                                                    ? '#ef4444'
                                                    : '#1c1917'
                                        }}
                                    >
                                        {stats.bookings} / {expectedBookingRecords}
                                    </span>
                                </div>
                            </div>
                            {isInconsistent && (
                                <button
                                    onClick={runRebuild}
                                    disabled={loading}
                                    style={{
                                        width: '100%',
                                        marginTop: '24px',
                                        padding: '14px',
                                        background:
                                            'linear-gradient(135deg, #b45309 0%, #92400e 100%)',
                                        color: 'white',
                                        border: 'none',
                                        borderRadius: '12px',
                                        cursor: 'pointer',
                                        fontSize: '14px',
                                        fontWeight: 800,
                                        boxShadow: '0 10px 15px -3px rgba(180, 83, 9, 0.3)'
                                    }}
                                >
                                    Repair & Sync
                                </button>
                            )}
                        </div>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                            <DataList
                                title="Event Store (Mongo)"
                                items={events}
                                columns={['eventType', 'occurredOn']}
                                emptyMsg="No events recorded."
                                badge={events.length}
                            />
                            <DataList
                                title="Projections Checkpoints"
                                items={checkpoints}
                                columns={['projectionName', 'lastEventId']}
                                emptyMsg="No checkpoints found."
                                badge={checkpoints.length}
                            />
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '32px' }}>
                            <DataList
                                title="Users Projection"
                                items={sortedUsers}
                                columns={['name', 'email', 'address']}
                                emptyMsg="No users projected."
                                badge={sortedUsers.length}
                            />
                            <DataList
                                title="Bookings Projection"
                                items={bookings}
                                columns={['data.clientName', 'data.clientEmail', 'createdAt']}
                                emptyMsg="No bookings projected."
                                badge={bookings.length}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
