import React from 'react';

export type PageType = 'home' | 'wizard' | 'explorer' | 'demo';

export const NavButton = ({
    target,
    label,
    icon: Icon,
    currentPage,
    onNavigate
}: {
    target: PageType;
    label: string;
    icon: React.FC;
    currentPage: string;
    onNavigate: (page: PageType) => void;
}) => (
    <button
        onClick={() => onNavigate(target)}
        style={{
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            padding: '8px 16px',
            background: 'none',
            border: 'none',
            cursor: 'pointer',
            borderRadius: '8px',
            color: currentPage === target ? '#111827' : '#6b7280',
            backgroundColor: currentPage === target ? '#f3f4f6' : 'transparent',
            fontWeight: currentPage === target ? 600 : 500,
            transition: 'all 0.2s',
            fontSize: '14px'
        }}
    >
        <Icon /> {label}
    </button>
);
