import { useCallback, useEffect, useMemo, useState } from 'react';

type PostgresExecutionRow = {
    id: number;
    status?: string | null;
    mode?: string | null;
    workflowId?: string | null;
    startedAt?: string | null;
    stoppedAt?: string | null;
    data?: {
        bookingId?: string | null;
        productId?: string | null;
        supplierId?: string | null;
        quoteId?: string | null;
    };
};

type ExecutionsPayload = {
    status: string;
    postgresExecutions?: PostgresExecutionRow[];
    message?: string;
    generatedAt?: string;
};

export function PostgresExecutionsPage() {
    const [loading, setLoading] = useState(false);
    const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [payload, setPayload] = useState<ExecutionsPayload | null>(null);

    const loadExecutions = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch('/api/redis-control/jobs?limit=100');
            const json = (await res.json()) as ExecutionsPayload;
            setPayload(json);
        } catch {
            setPayload({
                status: 'error',
                message: 'No se pudo cargar postgres executions.'
            });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadExecutions();
    }, [loadExecutions]);

    useEffect(() => {
        if (!autoRefreshEnabled) {
            return;
        }

        const interval = window.setInterval(() => {
            void loadExecutions();
        }, 4000);

        return () => {
            window.clearInterval(interval);
        };
    }, [autoRefreshEnabled, loadExecutions]);

    const rows = payload?.postgresExecutions ?? [];
    const normalizedSearch = searchTerm.trim().toLowerCase();

    const filteredRows = useMemo(() => {
        if (normalizedSearch === '') {
            return rows;
        }

        return rows.filter((execution) => {
            const fields = [
                execution.data?.bookingId,
                execution.data?.quoteId,
                execution.data?.productId,
                execution.data?.supplierId,
                execution.workflowId,
                execution.status,
                execution.mode,
                String(execution.id)
            ];

            return fields
                .filter(
                    (value): value is string => typeof value === 'string' && value.trim() !== ''
                )
                .some((value) => value.toLowerCase().includes(normalizedSearch));
        });
    }, [normalizedSearch, rows]);

    const formatTs = (value?: string | null) => {
        if (!value) {
            return '—';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString();
    };

    const formatExecutionData = (execution: PostgresExecutionRow) => {
        const bookingId = execution.data?.bookingId;
        if (!bookingId) {
            return '—';
        }

        const chunks = [`bookingId=${bookingId}`];
        if (execution.data?.productId) {
            chunks.push(`productId=${execution.data.productId}`);
        }
        if (execution.data?.supplierId) {
            chunks.push(`supplierId=${execution.data.supplierId}`);
        }
        if (execution.data?.quoteId) {
            chunks.push(`quoteId=${execution.data.quoteId}`);
        }

        return chunks.join(' | ');
    };

    return (
        <div style={{ maxWidth: '1200px', margin: '0 auto', paddingBottom: '60px' }}>
            <div style={{ marginBottom: '24px' }}>
                <h1
                    style={{
                        fontSize: '36px',
                        fontWeight: 900,
                        color: '#1c1917',
                        letterSpacing: '-0.04em',
                        marginBottom: '12px'
                    }}
                >
                    Postgres Executions
                </h1>
                <p style={{ color: '#78716c', fontSize: '18px' }}>
                    Vista dedicada para inspeccionar las ejecuciones persistidas de n8n.
                </p>
            </div>

            <section
                style={{
                    backgroundColor: '#fff',
                    borderRadius: '24px',
                    border: '1px solid #e7e5e4',
                    padding: '24px',
                    boxShadow: '0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -2px rgba(0,0,0,0.02)'
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: '12px',
                        marginBottom: '14px'
                    }}
                >
                    <input
                        type="text"
                        placeholder="Filtrar por bookingId, quoteId, workflowId..."
                        value={searchTerm}
                        onChange={(event) => setSearchTerm(event.target.value)}
                        style={{
                            minWidth: '320px',
                            flex: 1,
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524'
                        }}
                    />
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <button
                            onClick={() => setAutoRefreshEnabled((current) => !current)}
                            style={{
                                padding: '10px 14px',
                                border: `1px solid ${autoRefreshEnabled ? '#86efac' : '#e7e5e4'}`,
                                borderRadius: '10px',
                                backgroundColor: autoRefreshEnabled ? '#f0fdf4' : '#fafaf9',
                                color: autoRefreshEnabled ? '#166534' : '#44403c',
                                cursor: 'pointer',
                                fontWeight: 700
                            }}
                        >
                            Auto refresh {autoRefreshEnabled ? 'ON' : 'OFF'}
                        </button>
                        <button
                            onClick={() => void loadExecutions()}
                            disabled={loading}
                            style={{
                                padding: '10px 14px',
                                border: '1px solid #e7e5e4',
                                borderRadius: '10px',
                                backgroundColor: '#fafaf9',
                                color: '#44403c',
                                cursor: loading ? 'wait' : 'pointer',
                                fontWeight: 700
                            }}
                        >
                            {loading ? 'Refreshing...' : 'Refresh'}
                        </button>
                    </div>
                </div>

                {payload?.message && (
                    <div
                        style={{
                            marginBottom: '12px',
                            backgroundColor: '#fff7ed',
                            border: '1px solid #fed7aa',
                            borderRadius: '10px',
                            padding: '10px 12px',
                            color: '#9a3412',
                            fontSize: '13px'
                        }}
                    >
                        {payload.message}
                    </div>
                )}

                <div style={{ overflowX: 'auto' }}>
                    <table
                        style={{
                            width: '100%',
                            borderCollapse: 'collapse',
                            fontSize: '12px',
                            minWidth: '1120px'
                        }}
                    >
                        <thead>
                            <tr style={{ backgroundColor: '#fafaf9', color: '#44403c' }}>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    ID
                                </th>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    Status
                                </th>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    Mode
                                </th>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    Workflow ID
                                </th>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    Data
                                </th>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    Started
                                </th>
                                <th
                                    style={{
                                        textAlign: 'left',
                                        padding: '8px',
                                        borderBottom: '1px solid #e7e5e4'
                                    }}
                                >
                                    Stopped
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredRows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        style={{
                                            padding: '12px',
                                            color: '#78716c',
                                            borderBottom: '1px solid #f5f5f4'
                                        }}
                                    >
                                        {rows.length === 0
                                            ? 'No rows found in execution_entity yet.'
                                            : 'No rows match the current filter.'}
                                    </td>
                                </tr>
                            ) : (
                                filteredRows.map((execution) => (
                                    <tr key={execution.id}>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4'
                                            }}
                                        >
                                            {execution.id}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4'
                                            }}
                                        >
                                            {execution.status ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4'
                                            }}
                                        >
                                            {execution.mode ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4'
                                            }}
                                        >
                                            {execution.workflowId ?? '—'}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4',
                                                maxWidth: '380px'
                                            }}
                                        >
                                            <div
                                                style={{
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    fontFamily: 'JetBrains Mono, monospace'
                                                }}
                                                title={formatExecutionData(execution)}
                                            >
                                                {formatExecutionData(execution)}
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4'
                                            }}
                                        >
                                            {formatTs(execution.startedAt)}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4'
                                            }}
                                        >
                                            {formatTs(execution.stoppedAt)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}
