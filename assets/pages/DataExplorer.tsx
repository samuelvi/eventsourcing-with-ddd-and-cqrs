import React, { useState, useEffect } from 'react';

type EntityType = 'users' | 'bookings' | 'products' | 'suppliers' | 'event-store';

export function DataExplorer() {
    const [activeTab, setActiveTab] = useState<EntityType>('event-store');
    const [data, setData] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);

    const fetchData = async (type: EntityType) => {
        setLoading(true);
        try {
            const response = await fetch(`/api/${type}`, {
                headers: { 'Accept': 'application/ld+json' }
            });
            const result = await response.json();
            // API Platform returns collections in 'hydra:member'
            setData(result['hydra:member'] || []);
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

    return (
        <div style={{ fontFamily: 'sans-serif' }}>
            <h1>🔍 Data Explorer</h1>
            
            <div style={{ display: 'flex', gap: '10px', marginBottom: '20px', borderBottom: '2px solid #eee', paddingBottom: '10px' }}>
                {(['event-store', 'users', 'bookings', 'products', 'suppliers'] as EntityType[]).map(tab => (
                    <button 
                        key={tab}
                        onClick={() => setActiveTab(tab)}
                        style={{
                            padding: '8px 16px',
                            cursor: 'pointer',
                            backgroundColor: activeTab === tab ? '#007bff' : '#f8f9fa',
                            color: activeTab === tab ? 'white' : 'black',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            textTransform: 'capitalize'
                        }}
                    >
                        {tab.replace('-', ' ')}
                    </button>
                ))}
                <button 
                    onClick={() => fetchData(activeTab)} 
                    style={{ marginLeft: 'auto', padding: '8px', cursor: 'pointer' }}
                >
                    🔄 Refresh
                </button>
            </div>

            {loading ? (
                <p>Loading...</p>
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    {data.length === 0 ? (
                        <p>No records found.</p>
                    ) : (
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '14px' }}>
                            <thead>
                                <tr style={{ backgroundColor: '#f2f2f2' }}>
                                    {Object.keys(data[0]).filter(k => !k.startsWith('@')).map(key => (
                                        <th key={key} style={{ padding: '12px', border: '1px solid #ddd', textAlign: 'left' }}>{key}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((row, i) => (
                                    <tr key={i}>
                                        {Object.entries(row).filter(([k]) => !k.startsWith('@')).map(([key, value], j) => (
                                            <td key={j} style={{ padding: '12px', border: '1px solid #ddd' }}>
                                                {typeof value === 'object' ? (
                                                    <pre style={{ margin: 0, fontSize: '11px' }}>{JSON.stringify(value, null, 2)}</pre>
                                                ) : String(value)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}
        </div>
    );
}
