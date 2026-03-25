import { useEffect, useState } from 'react';

type ToolInfo = {
    name: string;
    url: string;
    description: string;
    credentials?: string;
};

type QueueStats = {
    backend?: string;
    async?: number | null;
    failed?: number | null;
};

type RedisQueueMetric = {
    exists: boolean;
    type: string;
    size: number;
    value?: string;
};

type RedisStatusPayload = {
    status: string;
    host?: string;
    port?: number;
    database?: number;
    ping?: string;
    dbSize?: number;
    keyspace?: string;
    queueMetrics?: Record<string, RedisQueueMetric>;
    sampleBullKeys?: string[];
    sampleN8nCacheKeys?: string[];
    message?: string;
    generatedAt?: string;
};

type RedisCommandPayload = {
    status: string;
    command?: string;
    result?: string;
    matched?: number;
    deleted?: number;
    keys?: string[];
    message?: string;
    generatedAt?: string;
};

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
};

type PostgresExecutionRow = {
    id: number;
    status?: string | null;
    mode?: string | null;
    workflowId?: string | null;
    startedAt?: string | null;
    stoppedAt?: string | null;
};

type RedisJobsPayload = {
    status: string;
    redisJobs?: RedisJobRow[];
    postgresExecutions?: PostgresExecutionRow[];
    message?: string;
    generatedAt?: string;
};

type RedisCommand = {
    id: 'ping' | 'scan-bull' | 'scan-n8n-cache' | 'clear-n8n-cache';
    label: string;
    tone: 'default' | 'danger';
};

const REDIS_COMMANDS: RedisCommand[] = [
    { id: 'ping', label: 'PING Redis', tone: 'default' },
    { id: 'scan-bull', label: 'Scan bull:*', tone: 'default' },
    { id: 'scan-n8n-cache', label: 'Scan n8n:cache:*', tone: 'default' },
    { id: 'clear-n8n-cache', label: 'Clear n8n:cache:*', tone: 'danger' }
];

const TOOLS: ToolInfo[] = [
    {
        name: 'Supervisor Web UI',
        url: 'http://localhost:9001/',
        description:
            'Gestión y monitorización visual de los workers de mensajería (messenger-worker).',
        credentials: 'Sin autenticación (Local Dev)'
    },
    {
        name: 'Adminer (Multi-DB)',
        url: 'http://localhost:8081/',
        description: 'Gestor de base de datos para PostgreSQL (Dominio y Mensajería).',
        credentials:
            'Dominio (postgres-db): user / password. Colas (queue-db): queue_user / queue_password'
    },
    {
        name: 'RedisInsight',
        url: 'http://localhost:8084/',
        description: 'Observabilidad visual de Redis para n8n y mensajería.',
        credentials: 'Sin autenticación (Local Dev). Host Redis: queue-redis, puerto 6379.'
    },
    {
        name: 'Kafka UI',
        url: 'http://localhost:8085/',
        description: 'Inspección de topics, consumidores y lag en Kafka.',
        credentials: 'Sin autenticación (Local Dev). Cluster: local (kafka:9092).'
    },
    {
        name: 'Mongo Express',
        url: 'http://localhost:8082/',
        description: 'Visor web para MongoDB (Event Store, Snapshots, Checkpoints).',
        credentials: 'user / password'
    },
    {
        name: 'n8n Automation',
        url: 'http://localhost:5678/',
        description: 'Orquestador de Sagas y automatizaciones.',
        credentials: 'Personalización deshabilitada en esta POC.'
    },
    {
        name: 'Data Explorer',
        url: '/explorer',
        description: 'Herramienta interna para inspeccionar el almacenamiento físico híbrido.'
    },
    {
        name: 'Architecture Monitor',
        url: '/demo',
        description: 'Simulador de flujo y monitor de consistencia eventual.'
    },
    {
        name: 'API Platform Docs',
        url: '/docs',
        description: 'Documentación interactiva de la API (Swagger/OpenAPI).'
    }
];

export function DashboardPanel() {
    const [queueStats, setQueueStats] = useState<QueueStats | null>(null);
    const [redisStatus, setRedisStatus] = useState<RedisStatusPayload | null>(null);
    const [redisStatusLoading, setRedisStatusLoading] = useState(false);
    const [redisCommandLoading, setRedisCommandLoading] = useState<RedisCommand['id'] | null>(null);
    const [redisCommandResult, setRedisCommandResult] = useState<RedisCommandPayload | null>(null);
    const [redisJobsLoading, setRedisJobsLoading] = useState(false);
    const [redisJobsData, setRedisJobsData] = useState<RedisJobsPayload | null>(null);

    const loadRedisStatus = async () => {
        setRedisStatusLoading(true);
        try {
            const res = await fetch('/api/redis-control/status');
            const payload = (await res.json()) as RedisStatusPayload;
            setRedisStatus(payload);
        } catch {
            setRedisStatus({
                status: 'error',
                message: 'No se pudo cargar el estado de Redis.'
            });
        } finally {
            setRedisStatusLoading(false);
        }
    };

    const runRedisCommand = async (command: RedisCommand['id']) => {
        if (
            command === 'clear-n8n-cache' &&
            !window.confirm('Esto borrará las claves n8n:cache:*. ¿Continuar?')
        ) {
            return;
        }

        setRedisCommandLoading(command);
        try {
            const res = await fetch(`/api/redis-control/command/${command}`, {
                method: 'POST'
            });
            const payload = (await res.json()) as RedisCommandPayload;
            setRedisCommandResult(payload);
        } catch {
            setRedisCommandResult({
                status: 'error',
                command,
                message: 'No se pudo ejecutar el comando.'
            });
        } finally {
            setRedisCommandLoading(null);
            await loadRedisStatus();
            await loadRedisJobs();
        }
    };

    const loadRedisJobs = async () => {
        setRedisJobsLoading(true);
        try {
            const res = await fetch('/api/redis-control/jobs?limit=25');
            const payload = (await res.json()) as RedisJobsPayload;
            setRedisJobsData(payload);
        } catch {
            setRedisJobsData({
                status: 'error',
                message: 'No se pudo cargar el listado de jobs.'
            });
        } finally {
            setRedisJobsLoading(false);
        }
    };

    useEffect(() => {
        const loadStats = async () => {
            try {
                const res = await fetch('/api/demo/stats');
                if (!res.ok) {
                    return;
                }
                const payload = (await res.json()) as { queue?: QueueStats };
                setQueueStats(payload.queue ?? null);
            } catch {
                // Panel should keep rendering even if stats endpoint is unavailable.
            }
        };

        void loadStats();
        void loadRedisStatus();
        void loadRedisJobs();
    }, []);

    const queueMetricRows = Object.entries(redisStatus?.queueMetrics ?? {}) as Array<
        [string, RedisQueueMetric]
    >;
    const redisJobs = redisJobsData?.redisJobs ?? [];
    const postgresExecutions = redisJobsData?.postgresExecutions ?? [];
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

    return (
        <div style={{ maxWidth: '1100px', margin: '0 auto', paddingBottom: '60px' }}>
            <div style={{ marginBottom: '56px' }}>
                <h1
                    style={{
                        fontSize: '36px',
                        fontWeight: 900,
                        color: '#1c1917',
                        letterSpacing: '-0.04em',
                        marginBottom: '12px'
                    }}
                >
                    System Control Panel
                </h1>
                <p style={{ color: '#78716c', fontSize: '19px' }}>
                    Centralized access to infrastructure, monitoring, and debugging tools.
                </p>
                <div
                    style={{
                        marginTop: '20px',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '16px',
                        background: '#fafaf9',
                        border: '1px solid #e7e5e4',
                        borderRadius: '14px',
                        padding: '10px 14px',
                        fontFamily: 'JetBrains Mono, monospace',
                        color: '#44403c',
                        fontSize: '13px'
                    }}
                >
                    <span>queue.backend={queueStats?.backend ?? 'unknown'}</span>
                    <span>async={queueStats?.async ?? 'n/a'}</span>
                    <span>failed={queueStats?.failed ?? 'n/a'}</span>
                </div>
            </div>

            <section
                style={{
                    backgroundColor: '#fff',
                    borderRadius: '32px',
                    border: '1px solid #e7e5e4',
                    padding: '32px',
                    marginBottom: '32px',
                    boxShadow: '0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -2px rgba(0,0,0,0.02)'
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        gap: '16px',
                        flexWrap: 'wrap',
                        marginBottom: '20px'
                    }}
                >
                    <div>
                        <h2 style={{ margin: 0, fontSize: '24px', color: '#1c1917' }}>
                            Redis Commands
                        </h2>
                        <p style={{ margin: '4px 0 0', color: '#78716c' }}>
                            Ejecuta acciones útiles para inspeccionar n8n queue mode desde
                            localhost.
                        </p>
                    </div>
                    <button
                        onClick={async () => {
                            await loadRedisStatus();
                            await loadRedisJobs();
                        }}
                        disabled={redisStatusLoading || redisJobsLoading}
                        style={{
                            padding: '10px 16px',
                            border: '1px solid #e7e5e4',
                            borderRadius: '12px',
                            backgroundColor: '#fafaf9',
                            color: '#44403c',
                            cursor: redisStatusLoading || redisJobsLoading ? 'wait' : 'pointer',
                            fontWeight: 700
                        }}
                    >
                        {redisStatusLoading || redisJobsLoading
                            ? 'Refreshing...'
                            : 'Refresh Redis Status'}
                    </button>
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(210px, 1fr))',
                        gap: '10px',
                        marginBottom: '20px'
                    }}
                >
                    {REDIS_COMMANDS.map((command) => (
                        <button
                            key={command.id}
                            onClick={() => void runRedisCommand(command.id)}
                            disabled={redisCommandLoading !== null}
                            style={{
                                padding: '12px 14px',
                                borderRadius: '12px',
                                border:
                                    command.tone === 'danger'
                                        ? '1px solid #fecaca'
                                        : '1px solid #e7e5e4',
                                backgroundColor:
                                    command.tone === 'danger'
                                        ? '#fef2f2'
                                        : redisCommandLoading === command.id
                                          ? '#f5f5f4'
                                          : '#fff',
                                color: command.tone === 'danger' ? '#b91c1c' : '#1c1917',
                                cursor: redisCommandLoading !== null ? 'wait' : 'pointer',
                                fontWeight: 700
                            }}
                        >
                            {redisCommandLoading === command.id ? 'Running...' : command.label}
                        </button>
                    ))}
                </div>

                <div
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '12px',
                        backgroundColor: redisStatus?.status === 'ok' ? '#ecfdf5' : '#fff7ed',
                        border: `1px solid ${redisStatus?.status === 'ok' ? '#bbf7d0' : '#fed7aa'}`,
                        borderRadius: '10px',
                        padding: '8px 12px',
                        color: redisStatus?.status === 'ok' ? '#166534' : '#9a3412',
                        fontFamily: 'JetBrains Mono, monospace',
                        fontSize: '12px',
                        marginBottom: '20px'
                    }}
                >
                    <span>
                        {redisStatus?.status === 'ok' ? 'REDIS ONLINE' : 'REDIS CHECK REQUIRED'}
                    </span>
                    <span>ping={redisStatus?.ping ?? 'n/a'}</span>
                    <span>dbSize={redisStatus?.dbSize ?? 'n/a'}</span>
                    <span>
                        target={redisStatus?.host ?? 'redis'}:{redisStatus?.port ?? 6379}/db
                        {redisStatus?.database ?? 0}
                    </span>
                </div>

                {redisStatus?.message && (
                    <div
                        style={{
                            marginBottom: '20px',
                            backgroundColor: '#fff7ed',
                            border: '1px solid #fed7aa',
                            borderRadius: '12px',
                            padding: '12px 14px',
                            color: '#9a3412',
                            fontSize: '13px'
                        }}
                    >
                        {redisStatus.message}
                    </div>
                )}

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: '14px',
                        marginBottom: '20px'
                    }}
                >
                    {queueMetricRows.map(([key, metric]) => (
                        <div
                            key={key}
                            style={{
                                border: '1px solid #e7e5e4',
                                borderRadius: '14px',
                                padding: '12px',
                                backgroundColor: metric.exists ? '#fffbeb' : '#fafaf9'
                            }}
                        >
                            <div
                                style={{
                                    fontFamily: 'JetBrains Mono, monospace',
                                    fontSize: '12px',
                                    color: '#1c1917',
                                    marginBottom: '8px'
                                }}
                            >
                                {key}
                            </div>
                            <div style={{ fontSize: '12px', color: '#57534e' }}>
                                type={metric.type} | size={metric.size}
                                {metric.value !== undefined ? ` | value=${metric.value}` : ''}
                            </div>
                        </div>
                    ))}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: '14px',
                        marginBottom: '20px'
                    }}
                >
                    <div
                        style={{
                            border: '1px solid #e7e5e4',
                            borderRadius: '14px',
                            padding: '12px',
                            minHeight: '120px'
                        }}
                    >
                        <div style={{ fontWeight: 700, marginBottom: '8px', color: '#1c1917' }}>
                            Sample bull:* keys
                        </div>
                        <pre
                            style={{
                                margin: 0,
                                fontSize: '12px',
                                maxHeight: '140px',
                                overflowY: 'auto',
                                color: '#57534e',
                                fontFamily: 'JetBrains Mono, monospace'
                            }}
                        >
                            {(redisStatus?.sampleBullKeys ?? []).join('\n') || 'No keys found.'}
                        </pre>
                    </div>
                    <div
                        style={{
                            border: '1px solid #e7e5e4',
                            borderRadius: '14px',
                            padding: '12px',
                            minHeight: '120px'
                        }}
                    >
                        <div style={{ fontWeight: 700, marginBottom: '8px', color: '#1c1917' }}>
                            Sample n8n:cache:* keys
                        </div>
                        <pre
                            style={{
                                margin: 0,
                                fontSize: '12px',
                                maxHeight: '140px',
                                overflowY: 'auto',
                                color: '#57534e',
                                fontFamily: 'JetBrains Mono, monospace'
                            }}
                        >
                            {(redisStatus?.sampleN8nCacheKeys ?? []).join('\n') || 'No keys found.'}
                        </pre>
                    </div>
                </div>

                <div
                    style={{
                        border: '1px solid #e7e5e4',
                        borderRadius: '14px',
                        padding: '12px',
                        backgroundColor: '#fafaf9'
                    }}
                >
                    <div style={{ fontWeight: 700, marginBottom: '8px', color: '#1c1917' }}>
                        Last command result
                    </div>
                    <pre
                        style={{
                            margin: 0,
                            fontSize: '12px',
                            maxHeight: '240px',
                            overflowY: 'auto',
                            color: '#57534e',
                            fontFamily: 'JetBrains Mono, monospace'
                        }}
                    >
                        {redisCommandResult
                            ? JSON.stringify(redisCommandResult, null, 2)
                            : 'Run a command to see results.'}
                    </pre>
                </div>

                <div
                    style={{
                        marginTop: '20px',
                        display: 'grid',
                        gridTemplateColumns: '1fr',
                        gap: '14px'
                    }}
                >
                    <div
                        style={{
                            border: '1px solid #e7e5e4',
                            borderRadius: '14px',
                            padding: '14px'
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: '10px'
                            }}
                        >
                            <div style={{ fontWeight: 700, color: '#1c1917' }}>
                                Redis jobs (queue transient)
                            </div>
                            <div style={{ fontSize: '12px', color: '#78716c' }}>
                                {redisJobsLoading ? 'Loading...' : `${redisJobs.length} rows`}
                            </div>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    fontSize: '12px',
                                    minWidth: '960px'
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
                                            State
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '8px',
                                                borderBottom: '1px solid #e7e5e4'
                                            }}
                                        >
                                            Name
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '8px',
                                                borderBottom: '1px solid #e7e5e4'
                                            }}
                                        >
                                            Attempts
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '8px',
                                                borderBottom: '1px solid #e7e5e4'
                                            }}
                                        >
                                            Payload
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '8px',
                                                borderBottom: '1px solid #e7e5e4'
                                            }}
                                        >
                                            Created
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '8px',
                                                borderBottom: '1px solid #e7e5e4'
                                            }}
                                        >
                                            Processed
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '8px',
                                                borderBottom: '1px solid #e7e5e4'
                                            }}
                                        >
                                            Finished
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {redisJobs.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                style={{
                                                    padding: '12px',
                                                    color: '#78716c',
                                                    borderBottom: '1px solid #f5f5f4'
                                                }}
                                            >
                                                No Redis job hashes found right now (normal when
                                                queue is empty/fast).
                                            </td>
                                        </tr>
                                    ) : (
                                        redisJobs.map((job) => (
                                            <tr key={job.key}>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {job.id}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {job.state}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {job.name}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {job.attemptsMade}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4',
                                                        maxWidth: '340px'
                                                    }}
                                                >
                                                    <div
                                                        style={{
                                                            whiteSpace: 'nowrap',
                                                            overflow: 'hidden',
                                                            textOverflow: 'ellipsis',
                                                            fontFamily: 'JetBrains Mono, monospace'
                                                        }}
                                                        title={job.payload ?? ''}
                                                    >
                                                        {job.payload ?? '—'}
                                                    </div>
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {formatTs(job.timestamp)}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {formatTs(job.processedOn)}
                                                </td>
                                                <td
                                                    style={{
                                                        padding: '8px',
                                                        borderBottom: '1px solid #f5f5f4'
                                                    }}
                                                >
                                                    {formatTs(job.finishedOn)}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        style={{
                            border: '1px solid #e7e5e4',
                            borderRadius: '14px',
                            padding: '14px'
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: '10px'
                            }}
                        >
                            <div style={{ fontWeight: 700, color: '#1c1917' }}>
                                Postgres executions (persistent)
                            </div>
                            <div style={{ fontSize: '12px', color: '#78716c' }}>
                                {redisJobsLoading
                                    ? 'Loading...'
                                    : `${postgresExecutions.length} rows`}
                            </div>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table
                                style={{
                                    width: '100%',
                                    borderCollapse: 'collapse',
                                    fontSize: '12px',
                                    minWidth: '860px'
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
                                    {postgresExecutions.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                style={{
                                                    padding: '12px',
                                                    color: '#78716c',
                                                    borderBottom: '1px solid #f5f5f4'
                                                }}
                                            >
                                                No rows found in execution_entity yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        postgresExecutions.map((execution) => (
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
                    </div>
                </div>
            </section>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(480px, 1fr))',
                    gap: '32px'
                }}
            >
                {TOOLS.map((tool, index) => (
                    <div
                        key={index}
                        style={{
                            backgroundColor: '#fff',
                            borderRadius: '32px',
                            border: '1px solid #e7e5e4',
                            padding: '40px',
                            display: 'flex',
                            flexDirection: 'column',
                            justifyContent: 'space-between',
                            boxShadow:
                                '0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -2px rgba(0,0,0,0.02)',
                            transition: 'transform 0.2s, box-shadow 0.2s'
                        }}
                    >
                        <div>
                            <h3
                                style={{
                                    fontSize: '24px',
                                    fontWeight: 800,
                                    margin: '0 0 16px',
                                    color: '#1c1917',
                                    letterSpacing: '-0.02em'
                                }}
                            >
                                {tool.name}
                            </h3>
                            <p
                                style={{
                                    fontSize: '16px',
                                    color: '#57534e',
                                    margin: '0 0 24px',
                                    lineHeight: '1.6'
                                }}
                            >
                                {tool.description}
                            </p>

                            {tool.credentials && (
                                <div
                                    style={{
                                        backgroundColor: '#fafaf9',
                                        padding: '20px',
                                        borderRadius: '16px',
                                        fontSize: '14px',
                                        fontFamily: 'JetBrains Mono, monospace',
                                        marginBottom: '32px',
                                        border: '1px solid #e7e5e4',
                                        color: '#44403c'
                                    }}
                                >
                                    <span
                                        style={{
                                            fontWeight: 800,
                                            color: '#b45309',
                                            display: 'block',
                                            marginBottom: '8px',
                                            fontSize: '12px',
                                            textTransform: 'uppercase',
                                            letterSpacing: '0.1em'
                                        }}
                                    >
                                        Access Credentials
                                    </span>
                                    {tool.credentials}
                                </div>
                            )}
                        </div>

                        <a
                            href={tool.url}
                            target={tool.url.startsWith('http') ? '_blank' : '_self'}
                            rel="noopener noreferrer"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                padding: '14px 28px',
                                background: 'linear-gradient(135deg, #1c1917 0%, #44403c 100%)',
                                color: '#fff',
                                borderRadius: '14px',
                                fontSize: '15px',
                                fontWeight: 800,
                                textDecoration: 'none',
                                transition: 'all 0.2s',
                                boxShadow: '0 10px 15px -3px rgba(0,0,0,0.2)'
                            }}
                        >
                            Open Tool{' '}
                            {tool.url.startsWith('http') && (
                                <span style={{ marginLeft: '10px', fontSize: '18px' }}>↗</span>
                            )}
                        </a>
                    </div>
                ))}
            </div>
        </div>
    );
}
