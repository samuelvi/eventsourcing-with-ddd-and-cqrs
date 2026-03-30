import { useCallback, useEffect, useMemo, useState } from 'react';

type PostgresExecutionRow = {
    id: number;
    status?: string | null;
    healthState?: string | null;
    mode?: string | null;
    workflowId?: string | null;
    workflowName?: string | null;
    startedAt?: string | null;
    stoppedAt?: string | null;
    data?: {
        bookingId?: string | null;
        productId?: string | null;
        supplierId?: string | null;
        quoteId?: string | null;
        correlationId?: string | null;
        event?: string | null;
        derivationRunId?: string | null;
        lastNodeExecuted?: string | null;
        nodesExecutedCount?: number | null;
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
    const [executionIdFilter, setExecutionIdFilter] = useState('');
    const [bookingIdFilter, setBookingIdFilter] = useState('');
    const [quoteIdFilter, setQuoteIdFilter] = useState('');
    const [workflowIdFilter, setWorkflowIdFilter] = useState('');
    const [workflowNameFilter, setWorkflowNameFilter] = useState('');
    const [healthStateFilter, setHealthStateFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [modeFilter, setModeFilter] = useState('');
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
    const normalizedExecutionId = executionIdFilter.trim();
    const normalizedBookingId = bookingIdFilter.trim().toLowerCase();
    const normalizedQuoteId = quoteIdFilter.trim().toLowerCase();
    const normalizedWorkflowId = workflowIdFilter.trim().toLowerCase();
    const normalizedWorkflowName = workflowNameFilter.trim().toLowerCase();
    const normalizedHealthState = healthStateFilter.trim().toLowerCase();
    const normalizedStatus = statusFilter.trim().toLowerCase();
    const normalizedMode = modeFilter.trim().toLowerCase();

    const healthStateOptions = useMemo(
        () =>
            Array.from(
                new Set(
                    rows
                        .map((execution) => execution.healthState?.trim())
                        .filter((value): value is string => !!value)
                )
            ).sort((a, b) => a.localeCompare(b)),
        [rows]
    );

    const statusOptions = useMemo(
        () =>
            Array.from(
                new Set(
                    rows
                        .map((execution) => execution.status?.trim())
                        .filter((value): value is string => !!value)
                )
            ).sort((a, b) => a.localeCompare(b)),
        [rows]
    );

    const modeOptions = useMemo(
        () =>
            Array.from(
                new Set(
                    rows
                        .map((execution) => execution.mode?.trim())
                        .filter((value): value is string => !!value)
                )
            ).sort((a, b) => a.localeCompare(b)),
        [rows]
    );

    const filteredRows = useMemo(() => {
        return rows.filter((execution) => {
            const executionId = String(execution.id);
            const bookingId = execution.data?.bookingId?.trim().toLowerCase() ?? '';
            const quoteId = execution.data?.quoteId?.trim().toLowerCase() ?? '';
            const workflowId = execution.workflowId?.trim().toLowerCase() ?? '';
            const workflowName = execution.workflowName?.trim().toLowerCase() ?? '';
            const healthState = execution.healthState?.trim().toLowerCase() ?? '';
            const status = execution.status?.trim().toLowerCase() ?? '';
            const mode = execution.mode?.trim().toLowerCase() ?? '';

            const matchesExecutionId =
                normalizedExecutionId === '' || executionId === normalizedExecutionId;

            const matchesBookingId =
                normalizedBookingId === '' ||
                (bookingId !== '' && bookingId === normalizedBookingId);

            const matchesQuoteId =
                normalizedQuoteId === '' || (quoteId !== '' && quoteId === normalizedQuoteId);

            const matchesWorkflowId =
                normalizedWorkflowId === '' || workflowId.includes(normalizedWorkflowId);

            const matchesWorkflowName =
                normalizedWorkflowName === '' || workflowName.includes(normalizedWorkflowName);

            const matchesHealthState =
                normalizedHealthState === '' ||
                (healthState !== '' && healthState === normalizedHealthState);

            const matchesStatus =
                normalizedStatus === '' || (status !== '' && status === normalizedStatus);

            const matchesMode = normalizedMode === '' || (mode !== '' && mode === normalizedMode);

            return (
                matchesExecutionId &&
                matchesBookingId &&
                matchesQuoteId &&
                matchesWorkflowId &&
                matchesWorkflowName &&
                matchesHealthState &&
                matchesStatus &&
                matchesMode
            );
        });
    }, [
        rows,
        normalizedExecutionId,
        normalizedBookingId,
        normalizedQuoteId,
        normalizedWorkflowId,
        normalizedWorkflowName,
        normalizedHealthState,
        normalizedStatus,
        normalizedMode
    ]);

    const healthStateStyles: Record<string, { background: string; color: string; border: string }> =
        {
            ok_success: { background: '#f0fdf4', color: '#166534', border: '#86efac' },
            ok_waiting: { background: '#eff6ff', color: '#1d4ed8', border: '#93c5fd' },
            ok_running: { background: '#ecfeff', color: '#155e75', border: '#67e8f9' },
            warn_waiting_overdue: { background: '#fffbeb', color: '#92400e', border: '#fcd34d' },
            warn_waiting_without_wait_till: {
                background: '#fff7ed',
                color: '#9a3412',
                border: '#fdba74'
            },
            warn_running_long: { background: '#fff7ed', color: '#9a3412', border: '#fdba74' },
            warn_canceled: { background: '#fafaf9', color: '#57534e', border: '#d6d3d1' },
            error_failed: { background: '#fef2f2', color: '#991b1b', border: '#fca5a5' },
            error_crashed: { background: '#fee2e2', color: '#7f1d1d', border: '#f87171' },
            info_unknown: { background: '#f5f5f4', color: '#44403c', border: '#d6d3d1' }
        };

    const getHealthStateStyle = (value?: string | null) => {
        if (!value) {
            return healthStateStyles.info_unknown;
        }

        return healthStateStyles[value] ?? healthStateStyles.info_unknown;
    };

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
        if (execution.data?.correlationId) {
            chunks.push(`corr=${execution.data.correlationId}`);
        }
        if (execution.data?.event) {
            chunks.push(`event=${execution.data.event}`);
        }
        if (execution.data?.derivationRunId) {
            chunks.push(`runId=${execution.data.derivationRunId}`);
        }
        if (execution.data?.lastNodeExecuted) {
            chunks.push(`lastNode=${execution.data.lastNodeExecuted}`);
        }
        if (typeof execution.data?.nodesExecutedCount === 'number') {
            chunks.push(`nodes=${execution.data.nodesExecutedCount}`);
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
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, minmax(220px, 1fr))',
                        gap: '10px',
                        marginBottom: '14px'
                    }}
                >
                    <input
                        type="text"
                        placeholder="executionId (exact match)"
                        value={executionIdFilter}
                        onChange={(event) => setExecutionIdFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524'
                        }}
                    />
                    <input
                        type="text"
                        placeholder="bookingId (exact match)"
                        value={bookingIdFilter}
                        onChange={(event) => setBookingIdFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524'
                        }}
                    />
                    <input
                        type="text"
                        placeholder="quoteId (exact match)"
                        value={quoteIdFilter}
                        onChange={(event) => setQuoteIdFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524'
                        }}
                    />
                    <input
                        type="text"
                        placeholder="workflowId (contains)"
                        value={workflowIdFilter}
                        onChange={(event) => setWorkflowIdFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524'
                        }}
                    />
                    <input
                        type="text"
                        placeholder="workflowName (contains)"
                        value={workflowNameFilter}
                        onChange={(event) => setWorkflowNameFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524'
                        }}
                    />
                    <select
                        value={healthStateFilter}
                        onChange={(event) => setHealthStateFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524',
                            backgroundColor: '#fff'
                        }}
                    >
                        <option value="">Health: all</option>
                        {healthStateOptions.map((healthState) => (
                            <option key={healthState} value={healthState}>
                                {healthState}
                            </option>
                        ))}
                    </select>
                    <select
                        value={statusFilter}
                        onChange={(event) => setStatusFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524',
                            backgroundColor: '#fff'
                        }}
                    >
                        <option value="">Status: all</option>
                        {statusOptions.map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                    <select
                        value={modeFilter}
                        onChange={(event) => setModeFilter(event.target.value)}
                        style={{
                            padding: '10px 12px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '10px',
                            fontSize: '13px',
                            color: '#292524',
                            backgroundColor: '#fff'
                        }}
                    >
                        <option value="">Mode: all</option>
                        {modeOptions.map((mode) => (
                            <option key={mode} value={mode}>
                                {mode}
                            </option>
                        ))}
                    </select>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <button
                            onClick={() => {
                                setExecutionIdFilter('');
                                setBookingIdFilter('');
                                setQuoteIdFilter('');
                                setWorkflowIdFilter('');
                                setWorkflowNameFilter('');
                                setHealthStateFilter('');
                                setStatusFilter('');
                                setModeFilter('');
                            }}
                            style={{
                                padding: '10px 14px',
                                border: '1px solid #e7e5e4',
                                borderRadius: '10px',
                                backgroundColor: '#fafaf9',
                                color: '#44403c',
                                cursor: 'pointer',
                                fontWeight: 700
                            }}
                        >
                            Clear filters
                        </button>
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

                <div
                    style={{
                        marginBottom: '12px',
                        color: '#78716c',
                        fontSize: '12px'
                    }}
                >
                    Showing {filteredRows.length} of {rows.length} rows
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
                            minWidth: '1280px'
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
                                    Health
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
                                    Workflow Name
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
                                        colSpan={9}
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
                                            <span
                                                style={{
                                                    display: 'inline-block',
                                                    padding: '2px 8px',
                                                    borderRadius: '999px',
                                                    border: `1px solid ${getHealthStateStyle(execution.healthState).border}`,
                                                    backgroundColor: getHealthStateStyle(
                                                        execution.healthState
                                                    ).background,
                                                    color: getHealthStateStyle(
                                                        execution.healthState
                                                    ).color,
                                                    fontWeight: 700,
                                                    fontSize: '11px'
                                                }}
                                            >
                                                {execution.healthState ?? 'info_unknown'}
                                            </span>
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
                                                maxWidth: '260px'
                                            }}
                                        >
                                            <div
                                                style={{
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis'
                                                }}
                                                title={execution.workflowName ?? '—'}
                                            >
                                                {execution.workflowName ?? '—'}
                                            </div>
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px',
                                                borderBottom: '1px solid #f5f5f4',
                                                maxWidth: '520px',
                                                verticalAlign: 'top'
                                            }}
                                        >
                                            <div
                                                style={{
                                                    whiteSpace: 'normal',
                                                    overflowWrap: 'anywhere',
                                                    wordBreak: 'break-word',
                                                    lineHeight: '1.45',
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
