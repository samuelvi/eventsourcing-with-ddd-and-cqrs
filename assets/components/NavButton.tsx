import React from 'react';

export type PageType = 'home' | 'wizard' | 'explorer' | 'demo' | 'users' | 'panel';

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
            gap: '10px',
            padding: '10px 20px',
            background: 'none',
            border: 'none',
            cursor: 'pointer',
            borderRadius: '12px',
            color: currentPage === target ? '#b45309' : '#78716c',
            backgroundColor: currentPage === target ? '#fffbeb' : 'transparent',
            fontWeight: currentPage === target ? 700 : 600,
            transition: 'all 0.2s',
            fontSize: '14px'
        }}
    >
        <Icon /> {label}
    </button>
);
