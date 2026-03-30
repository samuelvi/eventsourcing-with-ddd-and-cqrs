import { type CSSProperties, useCallback, useEffect, useMemo, useState } from 'react';

type RedisJobRow = {
    id: string;
    state: string;
    name: string;
    attemptsMade: number;
    key: string;
    payload?: string | null;
    timestamp?: string | null;
    processedOn?: string | null;
    finishedOn?: string | null;
    workflowId?: string | null;
    executionId?: string | null;
    restartExecutionId?: string | null;
    bookingId?: string | null;
    quoteId?: string | null;
    correlationId?: string | null;
};

type RedisJobStats = {
    total: number;
    stateCounts: Record<string, number>;
    oldestJobAgeSeconds?: number | null;
    oldestJobCreatedAt?: string | null;
};

type RedisJobsPayload = {
    status: string;
    redisJobs?: RedisJobRow[];
    redisJobStats?: RedisJobStats;
    message?: string;
    generatedAt?: string;
};

type RedisQueueMetric = {
    exists: boolean;
    type: string;
    size: number;
    value?: string;
};

type RedisStatusPayload = {
    status: string;
    ping?: string;
    dbSize?: number;
    queueMetrics?: Record<string, RedisQueueMetric>;
    message?: string;
};

const DEFAULT_STATE_COUNTS: Record<string, number> = {
    wait: 0,
    active: 0,
    delayed: 0,
    completed: 0,
    failed: 0,
    unknown: 0
};

export function RedisJobsPage() {
    const [loading, setLoading] = useState(false);
    const [autoRefreshEnabled, setAutoRefreshEnabled] = useState(true);
    const [stateFilter, setStateFilter] = useState('');
    const [executionIdFilter, setExecutionIdFilter] = useState('');
    const [workflowIdFilter, setWorkflowIdFilter] = useState('');
    const [bookingIdFilter, setBookingIdFilter] = useState('');
    const [quoteIdFilter, setQuoteIdFilter] = useState('');
    const [correlationIdFilter, setCorrelationIdFilter] = useState('');
    const [payload, setPayload] = useState<RedisJobsPayload | null>(null);
    const [redisStatus, setRedisStatus] = useState<RedisStatusPayload | null>(null);

    const loadJobs = useCallback(async () => {
        setLoading(true);
        try {
            const [jobsResponse, statusResponse] = await Promise.all([
                fetch('/api/redis-control/jobs?limit=200'),
                fetch('/api/redis-control/status')
            ]);

            const json = (await jobsResponse.json()) as RedisJobsPayload;
            const statusJson = (await statusResponse.json()) as RedisStatusPayload;

            setPayload(json);
            setRedisStatus(statusJson);

            if (!jobsResponse.ok && json.status !== 'ok') {
                setPayload({
                    status: 'error',
                    message: json.message ?? 'No se pudieron cargar los jobs de Redis.'
                });
            }

            if (!statusResponse.ok && statusJson.status !== 'ok') {
                setRedisStatus({
                    status: 'error',
                    message: statusJson.message ?? 'No se pudo cargar el estado de Redis.'
                });
            }
        } catch {
            setPayload({ status: 'error', message: 'No se pudieron cargar los jobs de Redis.' });
            setRedisStatus({ status: 'error', message: 'No se pudo cargar el estado de Redis.' });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadJobs();
    }, [loadJobs]);

    useEffect(() => {
        if (!autoRefreshEnabled) {
            return;
        }

        const interval = window.setInterval(() => {
            void loadJobs();
        }, 4000);

        return () => window.clearInterval(interval);
    }, [autoRefreshEnabled, loadJobs]);

    const rows = payload?.redisJobs ?? [];
    const stateCounts = payload?.redisJobStats?.stateCounts ?? DEFAULT_STATE_COUNTS;

    const redisFailedQueueSize = redisStatus?.queueMetrics?.['bull:jobs:failed']?.size ?? 0;

    const redisHealth = useMemo(() => {
        if (redisStatus?.status === 'error') {
            return {
                level: 'critical' as const,
                label: 'Redis error',
                message: redisStatus.message ?? 'No se puede consultar Redis en este momento.'
            };
        }

        if (payload?.status === 'error') {
            return {
                level: 'critical' as const,
                label: 'Jobs endpoint error',
                message: payload.message ?? 'No se pueden consultar jobs de Redis en este momento.'
            };
        }

        if (redisFailedQueueSize > 0) {
            return {
                level: 'warning' as const,
                label: 'Redis queue warnings',
                message: `Hay ${redisFailedQueueSize} jobs en cola fallida (bull:jobs:failed).`
            };
        }

        return {
            level: 'ok' as const,
            label: 'Redis healthy',
            message: 'Sin errores visibles de Redis ni jobs fallidos en este momento.'
        };
    }, [
        payload?.message,
        payload?.status,
        redisFailedQueueSize,
        redisStatus?.message,
        redisStatus?.status
    ]);

    const normalizedState = stateFilter.trim().toLowerCase();
    const normalizedExecutionId = executionIdFilter.trim();
    const normalizedWorkflowId = workflowIdFilter.trim().toLowerCase();
    const normalizedBookingId = bookingIdFilter.trim().toLowerCase();
    const normalizedQuoteId = quoteIdFilter.trim().toLowerCase();
    const normalizedCorrelationId = correlationIdFilter.trim().toLowerCase();

    const stateOptions = useMemo(
        () =>
            Array.from(
                new Set(rows.map((row) => row.state.trim()).filter((value) => value !== ''))
            ).sort((a, b) => a.localeCompare(b)),
        [rows]
    );

    const filteredRows = useMemo(() => {
        return rows.filter((row) => {
            const state = row.state.trim().toLowerCase();
            const workflowId = (row.workflowId ?? '').trim().toLowerCase();
            const executionId = (row.executionId ?? '').trim();
            const bookingId = (row.bookingId ?? '').trim().toLowerCase();
            const quoteId = (row.quoteId ?? '').trim().toLowerCase();
            const correlationId = (row.correlationId ?? '').trim().toLowerCase();

            const matchesState = normalizedState === '' || state === normalizedState;
            const matchesExecutionId =
                normalizedExecutionId === '' ||
                (executionId !== '' && executionId === normalizedExecutionId);
            const matchesWorkflowId =
                normalizedWorkflowId === '' || workflowId.includes(normalizedWorkflowId);
            const matchesBookingId =
                normalizedBookingId === '' ||
                (bookingId !== '' && bookingId === normalizedBookingId);
            const matchesQuoteId =
                normalizedQuoteId === '' || (quoteId !== '' && quoteId === normalizedQuoteId);
            const matchesCorrelationId =
                normalizedCorrelationId === '' || correlationId.includes(normalizedCorrelationId);

            return (
                matchesState &&
                matchesExecutionId &&
                matchesWorkflowId &&
                matchesBookingId &&
                matchesQuoteId &&
                matchesCorrelationId
            );
        });
    }, [
        rows,
        normalizedState,
        normalizedExecutionId,
        normalizedWorkflowId,
        normalizedBookingId,
        normalizedQuoteId,
        normalizedCorrelationId
    ]);

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

    const formatAge = (seconds?: number | null) => {
        if (seconds === null || seconds === undefined) {
            return '—';
        }
        if (seconds < 60) {
            return `${seconds}s`;
        }
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        return `${minutes}m ${remainder}s`;
    };

    return (
        <div style={{ maxWidth: '1360px', margin: '0 auto', paddingBottom: '60px' }}>
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
                    Redis Jobs
                </h1>
                <p style={{ color: '#78716c', fontSize: '18px' }}>
                    Monitorizacion en tiempo real de cola n8n (transitorio).
                </p>
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))',
                    gap: '10px',
                    marginBottom: '16px'
                }}
            >
                {[
                    { label: 'Waiting', value: stateCounts.wait ?? 0 },
                    { label: 'Active', value: stateCounts.active ?? 0 },
                    { label: 'Delayed', value: stateCounts.delayed ?? 0 },
                    { label: 'Failed', value: stateCounts.failed ?? 0 },
                    { label: 'Total', value: payload?.redisJobStats?.total ?? rows.length },
                    {
                        label: 'Oldest age',
                        value: formatAge(payload?.redisJobStats?.oldestJobAgeSeconds)
                    }
                ].map((metric) => (
                    <div
                        key={metric.label}
                        style={{
                            backgroundColor: '#fff',
                            border: '1px solid #e7e5e4',
                            borderRadius: '14px',
                            padding: '12px 14px'
                        }}
                    >
                        <div style={{ fontSize: '12px', color: '#78716c', marginBottom: '6px' }}>
                            {metric.label}
                        </div>
                        <div style={{ fontWeight: 800, fontSize: '24px', color: '#1c1917' }}>
                            {String(metric.value)}
                        </div>
                    </div>
                ))}
            </div>

            <section
                style={{
                    backgroundColor: '#fff',
                    borderRadius: '24px',
                    border: '1px solid #e7e5e4',
                    padding: '22px',
                    boxShadow: '0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -2px rgba(0,0,0,0.02)'
                }}
            >
                <div
                    style={{
                        marginBottom: '12px',
                        borderRadius: '10px',
                        padding: '10px 12px',
                        border:
                            redisHealth.level === 'critical'
                                ? '1px solid #fca5a5'
                                : redisHealth.level === 'warning'
                                  ? '1px solid #fdba74'
                                  : '1px solid #86efac',
                        backgroundColor:
                            redisHealth.level === 'critical'
                                ? '#fef2f2'
                                : redisHealth.level === 'warning'
                                  ? '#fff7ed'
                                  : '#f0fdf4',
                        color:
                            redisHealth.level === 'critical'
                                ? '#991b1b'
                                : redisHealth.level === 'warning'
                                  ? '#9a3412'
                                  : '#166534',
                        fontSize: '13px'
                    }}
                >
                    <strong style={{ marginRight: '8px' }}>{redisHealth.label}</strong>
                    <span>{redisHealth.message}</span>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, minmax(220px, 1fr))',
                        gap: '10px',
                        marginBottom: '12px'
                    }}
                >
                    <select
                        value={stateFilter}
                        onChange={(event) => setStateFilter(event.target.value)}
                        style={inputStyle}
                    >
                        <option value="">State: all</option>
                        {stateOptions.map((state) => (
                            <option key={state} value={state}>
                                {state}
                            </option>
                        ))}
                    </select>
                    <input
                        type="text"
                        placeholder="executionId (exact match)"
                        value={executionIdFilter}
                        onChange={(event) => setExecutionIdFilter(event.target.value)}
                        style={inputStyle}
                    />
                    <input
                        type="text"
                        placeholder="workflowId (contains)"
                        value={workflowIdFilter}
                        onChange={(event) => setWorkflowIdFilter(event.target.value)}
                        style={inputStyle}
                    />
                    <input
                        type="text"
                        placeholder="bookingId (exact match)"
                        value={bookingIdFilter}
                        onChange={(event) => setBookingIdFilter(event.target.value)}
                        style={inputStyle}
                    />
                    <input
                        type="text"
                        placeholder="quoteId (exact match)"
                        value={quoteIdFilter}
                        onChange={(event) => setQuoteIdFilter(event.target.value)}
                        style={inputStyle}
                    />
                    <input
                        type="text"
                        placeholder="correlationId (contains)"
                        value={correlationIdFilter}
                        onChange={(event) => setCorrelationIdFilter(event.target.value)}
                        style={inputStyle}
                    />
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        marginBottom: '12px'
                    }}
                >
                    <button
                        onClick={() => {
                            setStateFilter('');
                            setExecutionIdFilter('');
                            setWorkflowIdFilter('');
                            setBookingIdFilter('');
                            setQuoteIdFilter('');
                            setCorrelationIdFilter('');
                        }}
                        style={secondaryButtonStyle}
                    >
                        Clear filters
                    </button>
                    <button
                        onClick={() => setAutoRefreshEnabled((value) => !value)}
                        style={{
                            ...secondaryButtonStyle,
                            borderColor: autoRefreshEnabled ? '#86efac' : '#e7e5e4',
                            backgroundColor: autoRefreshEnabled ? '#f0fdf4' : '#fafaf9',
                            color: autoRefreshEnabled ? '#166534' : '#44403c'
                        }}
                    >
                        Auto refresh {autoRefreshEnabled ? 'ON' : 'OFF'}
                    </button>
                    <button
                        onClick={() => void loadJobs()}
                        disabled={loading}
                        style={secondaryButtonStyle}
                    >
                        {loading ? 'Refreshing...' : 'Refresh'}
                    </button>
                    <span style={{ marginLeft: 'auto', fontSize: '12px', color: '#78716c' }}>
                        Showing {filteredRows.length} of {rows.length} rows
                    </span>
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
                            minWidth: '1720px'
                        }}
                    >
                        <thead>
                            <tr style={{ backgroundColor: '#fafaf9', color: '#44403c' }}>
                                {[
                                    'Job ID',
                                    'State',
                                    'Name',
                                    'Attempts',
                                    'Workflow ID',
                                    'Execution ID',
                                    'Restart ID',
                                    'Booking ID',
                                    'Quote ID',
                                    'Correlation ID',
                                    'Created',
                                    'Processed',
                                    'Finished',
                                    'Payload'
                                ].map((header) => (
                                    <th
                                        key={header}
                                        style={{
                                            textAlign: 'left',
                                            padding: '8px',
                                            borderBottom: '1px solid #e7e5e4'
                                        }}
                                    >
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filteredRows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={14}
                                        style={{
                                            padding: '12px',
                                            color: '#78716c',
                                            borderBottom: '1px solid #f5f5f4'
                                        }}
                                    >
                                        {rows.length === 0
                                            ? 'No Redis jobs found right now (normal when queue is fast or empty).'
                                            : 'No rows match current filters.'}
                                    </td>
                                </tr>
                            ) : (
                                filteredRows.map((job) => (
                                    <tr key={job.key}>
                                        <td style={tdStyle}>{job.id}</td>
                                        <td style={tdStyle}>{job.state}</td>
                                        <td style={tdStyle}>{job.name}</td>
                                        <td style={tdStyle}>{job.attemptsMade}</td>
                                        <td style={tdStyle}>{job.workflowId ?? '—'}</td>
                                        <td style={tdStyle}>
                                            {job.executionId ? (
                                                <a
                                                    href={`http://localhost:5678/execution/${job.executionId}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    style={{
                                                        color: '#1d4ed8',
                                                        textDecoration: 'none'
                                                    }}
                                                >
                                                    {job.executionId}
                                                </a>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td style={tdStyle}>{job.restartExecutionId ?? '—'}</td>
                                        <td style={tdStyle}>{job.bookingId ?? '—'}</td>
                                        <td style={tdStyle}>{job.quoteId ?? '—'}</td>
                                        <td style={tdStyle}>{job.correlationId ?? '—'}</td>
                                        <td style={tdStyle}>{formatTs(job.timestamp)}</td>
                                        <td style={tdStyle}>{formatTs(job.processedOn)}</td>
                                        <td style={tdStyle}>{formatTs(job.finishedOn)}</td>
                                        <td style={{ ...tdStyle, maxWidth: '360px' }}>
                                            <div
                                                style={{
                                                    whiteSpace: 'normal',
                                                    overflowWrap: 'anywhere',
                                                    wordBreak: 'break-word',
                                                    lineHeight: '1.35',
                                                    fontFamily: 'JetBrains Mono, monospace'
                                                }}
                                                title={job.payload ?? ''}
                                            >
                                                {job.payload ?? '—'}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div
                    style={{
                        marginTop: '12px',
                        fontSize: '12px',
                        color: '#78716c',
                        backgroundColor: '#fafaf9',
                        border: '1px solid #e7e5e4',
                        borderRadius: '10px',
                        padding: '8px 10px'
                    }}
                >
                    Redis jobs son transitorios: usa esta vista para tiempo real y /executions para
                    historico persistente.
                </div>
            </section>
        </div>
    );
}

const inputStyle: CSSProperties = {
    padding: '10px 12px',
    border: '1px solid #e7e5e4',
    borderRadius: '10px',
    fontSize: '13px',
    color: '#292524',
    backgroundColor: '#fff'
};

const secondaryButtonStyle: CSSProperties = {
    padding: '10px 14px',
    border: '1px solid #e7e5e4',
    borderRadius: '10px',
    backgroundColor: '#fafaf9',
    color: '#44403c',
    cursor: 'pointer',
    fontWeight: 700
};

const tdStyle: CSSProperties = {
    padding: '8px',
    borderBottom: '1px solid #f5f5f4',
    verticalAlign: 'top'
};
