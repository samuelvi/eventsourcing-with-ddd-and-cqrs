import React from 'react';

type ToolInfo = {
    name: string;
    url: string;
    description: string;
    credentials?: string;
};

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
            </div>

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
