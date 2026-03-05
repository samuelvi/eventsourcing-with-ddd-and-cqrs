import { useState, useEffect, useCallback } from 'react';

type EntityType =
    | 'users'
    | 'bookings'
    | 'products'
    | 'products-catalog'
    | 'suppliers'
    | 'menus'
    | 'quotes'
    | 'event-store'
    | 'checkpoints'
    | 'snapshots'
    | 'messages-async'
    | 'messages-failed';

const ALL_TABS: EntityType[] = [
    'event-store',
    'checkpoints',
    'snapshots',
    'messages-async',
    'messages-failed',
    'users',
    'bookings',
    'suppliers',
    'quotes',
    'products',
    'menus',
    'products-catalog'
];

export function DataExplorer() {
    const [activeTab, setActiveTab] = useState<EntityType>('event-store');
    const [data, setData] = useState<Record<string, unknown>[]>([]);
    const [counts, setCounts] = useState<Partial<Record<EntityType, number>>>({});
    const [loading, setLoading] = useState(false);
    const [eventCorrelationFilter, setEventCorrelationFilter] = useState('');
    const [eventSortOrder, setEventSortOrder] = useState<'desc' | 'asc'>('desc');

    const fetchData = async (type: EntityType) => {
        setLoading(true);
        try {
            const response = await fetch(`/api/${type}?t=${Date.now()}`, {
                headers: { Accept: 'application/ld+json' }
            });
            const result = await response.json();
            setData((result['hydra:member'] as Record<string, unknown>[]) || []);
        } catch (error) {
            console.error('Error fetching data:', error);
            setData([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData(activeTab);
    }, [activeTab]);

    const fetchCount = async (type: EntityType): Promise<number> => {
        const response = await fetch(`/api/${type}?t=${Date.now()}`, {
            headers: { Accept: 'application/ld+json' }
        });

        if (!response.ok) {
            return 0;
        }

        const result = await response.json();
        if (typeof result['hydra:totalItems'] === 'number') {
            return result['hydra:totalItems'] as number;
        }

        return Array.isArray(result['hydra:member']) ? result['hydra:member'].length : 0;
    };

    const fetchAllCounts = useCallback(async () => {
        try {
            const entries = await Promise.all(
                ALL_TABS.map(async (type) => [type, await fetchCount(type)] as const)
            );
            setCounts(Object.fromEntries(entries) as Partial<Record<EntityType, number>>);
        } catch (error) {
            console.error('Error fetching counts:', error);
        }
    }, []);

    const normalizedEventCorrelationFilter = eventCorrelationFilter.trim().toLowerCase();
    const visibleData =
        activeTab !== 'event-store'
            ? data
            : [...data]
                  .filter((row) => {
                      if (normalizedEventCorrelationFilter === '') {
                          return true;
                      }

                      const payload = row.payload;
                      const payloadCorrelationId =
                          payload && typeof payload === 'object' && !Array.isArray(payload)
                              ? String((payload as Record<string, unknown>).correlationId || '')
                              : '';
                      const topLevelCorrelationId = String(row.correlationId || '');

                      return (
                          payloadCorrelationId
                              .toLowerCase()
                              .includes(normalizedEventCorrelationFilter) ||
                          topLevelCorrelationId
                              .toLowerCase()
                              .includes(normalizedEventCorrelationFilter)
                      );
                  })
                  .sort((a, b) => {
                      const occurredOnA = new Date(String(a.occurredOn || '')).getTime();
                      const occurredOnB = new Date(String(b.occurredOn || '')).getTime();

                      if (Number.isNaN(occurredOnA) || Number.isNaN(occurredOnB)) {
                          return 0;
                      }

                      return eventSortOrder === 'asc'
                          ? occurredOnA - occurredOnB
                          : occurredOnB - occurredOnA;
                  });

    useEffect(() => {
        fetchAllCounts();
    }, [fetchAllCounts]);

    const mongoTabs: Partial<Record<EntityType, string>> = {
        'event-store': 'Event Store',
        checkpoints: 'Checkpoints',
        snapshots: 'Snapshots'
    };

    const messagingTabs: Partial<Record<EntityType, string>> = {
        'messages-async': 'Async Queue',
        'messages-failed': 'Failed (DLQ)'
    };

    const postgresTabs: Partial<Record<EntityType, string>> = {
        users: 'Users',
        bookings: 'Bookings',
        suppliers: 'Suppliers',
        quotes: 'Quotes',
        products: 'Products',
        menus: 'Menus',
        'products-catalog': 'Products Catalog'
    };

    const TabGroup = ({ title, tabs }: { title: string; tabs: Record<string, string> }) => (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            <span
                style={{
                    fontSize: '11px',
                    fontWeight: 700,
                    color: '#9ca3af',
                    textTransform: 'uppercase',
                    letterSpacing: '0.05em',
                    paddingLeft: '4px'
                }}
            >
                {title}
            </span>
            <div
                style={{
                    display: 'flex',
                    gap: '2px',
                    backgroundColor: '#f3f4f6',
                    padding: '3px',
                    borderRadius: '12px'
                }}
            >
                {Object.entries(tabs).map(([id, label]) => (
                    <button
                        key={id}
                        onClick={() => setActiveTab(id as EntityType)}
                        style={{
                            padding: '6px 10px',
                            cursor: 'pointer',
                            backgroundColor: activeTab === id ? '#fff' : 'transparent',
                            color: activeTab === id ? '#111827' : '#6b7280',
                            border: 'none',
                            borderRadius: '8px',
                            fontSize: '13px',
                            fontWeight: activeTab === id ? 600 : 500,
                            boxShadow: activeTab === id ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
                            transition: 'all 0.2s',
                            whiteSpace: 'nowrap'
                        }}
                    >
                        <span>{label}</span>
                        <span
                            style={{
                                marginLeft: '6px',
                                backgroundColor: activeTab === id ? '#f3f4f6' : '#e5e7eb',
                                color: '#374151',
                                borderRadius: '999px',
                                padding: '1px 6px',
                                fontSize: '10px',
                                fontWeight: 700
                            }}
                        >
                            {counts[id as EntityType] ?? 0}
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );

    return (
        <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: '32px'
                }}
            >
                <div>
                    <h1 style={{ margin: 0, fontSize: '28px', fontWeight: 700 }}>Data Explorer</h1>
                    <p style={{ margin: '4px 0 0', color: '#6b7280' }}>
                        Inspect physical storage across the hybrid architecture.
                    </p>
                </div>
                <button
                    onClick={() => {
                        fetchData(activeTab);
                        fetchAllCounts();
                    }}
                    style={{
                        padding: '10px 16px',
                        cursor: 'pointer',
                        backgroundColor: '#fff',
                        border: '1px solid #e5e7eb',
                        borderRadius: '8px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        fontSize: '14px',
                        fontWeight: 500
                    }}
                >
                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <path d="M23 4v6h-6" />
                        <path d="M1 20v-6h6" />
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                    </svg>
                    Refresh View
                </button>
            </div>

            {/* Grouped Tabs */}
            <div style={{ display: 'flex', gap: '32px', marginBottom: '32px', flexWrap: 'wrap' }}>
                <TabGroup title="MongoDB (Source of Truth)" tabs={mongoTabs} />
                <TabGroup title="PostgreSQL (Messaging)" tabs={messagingTabs} />
                <TabGroup title="PostgreSQL (Read Models)" tabs={postgresTabs} />
            </div>

            {activeTab === 'event-store' && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '10px',
                        marginBottom: '16px',
                        flexWrap: 'wrap'
                    }}
                >
                    <input
                        type="text"
                        placeholder="Filter by correlationId"
                        value={eventCorrelationFilter}
                        onChange={(event) => setEventCorrelationFilter(event.target.value)}
                        style={{
                            height: '36px',
                            padding: '0 12px',
                            borderRadius: '10px',
                            border: '1px solid #d1d5db',
                            fontSize: '13px',
                            minWidth: '260px',
                            backgroundColor: '#fff'
                        }}
                    />
                    <select
                        value={eventSortOrder}
                        onChange={(event) =>
                            setEventSortOrder(event.target.value as 'asc' | 'desc')
                        }
                        style={{
                            height: '36px',
                            padding: '0 12px',
                            borderRadius: '10px',
                            border: '1px solid #d1d5db',
                            fontSize: '13px',
                            backgroundColor: '#fff',
                            cursor: 'pointer'
                        }}
                    >
                        <option value="desc">OccurredOn desc</option>
                        <option value="asc">OccurredOn asc</option>
                    </select>
                </div>
            )}

            {loading ? (
                <div style={{ padding: '60px', textAlign: 'center', color: '#9ca3af' }}>
                    Loading dataset...
                </div>
            ) : (
                <div
                    style={{
                        backgroundColor: '#fff',
                        borderRadius: '16px',
                        border: '1px solid #e5e7eb',
                        overflow: 'hidden',
                        boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)'
                    }}
                >
                    {visibleData.length === 0 ? (
                        <div style={{ padding: '60px', textAlign: 'center', color: '#9ca3af' }}>
                            No records found in this collection.
                        </div>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    textAlign: 'left',
                                    fontSize: '13px'
                                }}
                            >
                                <thead>
                                    <tr
                                        style={{
                                            borderBottom: '1px solid #e5e7eb',
                                            backgroundColor: '#f9fafb'
                                        }}
                                    >
                                        {Object.keys(visibleData[0])
                                            .filter((k) => !k.startsWith('@'))
                                            .map((key) => (
                                                <th
                                                    key={key}
                                                    style={{
                                                        padding: '16px 24px',
                                                        fontWeight: 600,
                                                        color: '#374151',
                                                        textTransform: 'capitalize'
                                                    }}
                                                >
                                                    {key.replace(/([A-Z])/g, ' $1')}
                                                </th>
                                            ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleData.map((row, i) => (
                                        <tr
                                            key={i}
                                            style={{
                                                borderBottom:
                                                    i === visibleData.length - 1
                                                        ? 'none'
                                                        : '1px solid #f3f4f6'
                                            }}
                                        >
                                            {Object.entries(row)
                                                .filter(([k]) => !k.startsWith('@'))
                                                .map(([key, value], j) => (
                                                    <td
                                                        key={j}
                                                        style={{
                                                            padding: '16px 24px',
                                                            verticalAlign: 'top',
                                                            color: '#4b5563'
                                                        }}
                                                    >
                                                        {typeof value === 'object' ? (
                                                            <pre
                                                                style={{
                                                                    margin: 0,
                                                                    fontSize: '11px',
                                                                    backgroundColor: '#f9fafb',
                                                                    padding: '12px',
                                                                    borderRadius: '8px',
                                                                    fontFamily:
                                                                        'JetBrains Mono, monospace',
                                                                    border: '1px solid #f3f4f6'
                                                                }}
                                                            >
                                                                {JSON.stringify(value, null, 2)}
                                                            </pre>
                                                        ) : (
                                                            <span
                                                                style={{
                                                                    fontFamily: key
                                                                        .toLowerCase()
                                                                        .includes('id')
                                                                        ? 'monospace'
                                                                        : 'inherit',
                                                                    fontSize: key
                                                                        .toLowerCase()
                                                                        .includes('id')
                                                                        ? '12px'
                                                                        : '13px'
                                                                }}
                                                            >
                                                                {String(value)}
                                                            </span>
                                                        )}
                                                    </td>
                                                ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
