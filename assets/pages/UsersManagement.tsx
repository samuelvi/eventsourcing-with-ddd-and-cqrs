import { type CSSProperties, type FormEvent, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { v7 as uuidv7 } from 'uuid';

type UserRow = {
    id: string;
    name: string;
    email: string;
    createdAt?: string | null;
};

type HydraUsers = {
    'hydra:member'?: UserRow[];
};

type ApiErrorPayload = {
    'hydra:description'?: string;
    detail?: string;
    message?: string;
};

type UsersManagementMode = 'list' | 'create' | 'edit';

type UsersManagementProps = {
    mode: UsersManagementMode;
    userId: string | null;
    onNavigate: (path: string) => void;
};

const FLASH_STORAGE_KEY = 'users-management-flash';

export function UsersManagement({ mode, userId, onNavigate }: UsersManagementProps) {
    const queryClient = useQueryClient();
    const [message, setMessage] = useState<string>('');

    const [createName, setCreateName] = useState('');
    const [createEmail, setCreateEmail] = useState('');

    const [editName, setEditName] = useState('');
    const [editEmail, setEditEmail] = useState('');

    useEffect(() => {
        if (mode !== 'list') {
            return;
        }

        const flash = consumeFlashMessage();
        if (flash) {
            setMessage(flash);
        }
    }, [mode]);

    const usersQuery = useQuery<UserRow[]>({
        queryKey: ['users-management'],
        enabled: mode === 'list',
        refetchInterval: mode === 'list' ? 2000 : false,
        queryFn: async () => {
            const response = await fetch('/api/users?t=' + Date.now(), {
                headers: { Accept: 'application/ld+json' }
            });
            if (!response.ok) {
                throw new Error('Failed to load users.');
            }
            const payload = (await response.json()) as HydraUsers;
            return payload['hydra:member'] ?? [];
        }
    });

    const userQuery = useQuery<UserRow>({
        queryKey: ['users-management', userId],
        enabled: mode === 'edit' && !!userId,
        queryFn: async () => {
            const response = await fetch(`/api/users/${userId}`, {
                headers: { Accept: 'application/ld+json' }
            });

            if (!response.ok) {
                throw new Error('Unable to load user for editing.');
            }

            return (await response.json()) as UserRow;
        }
    });

    useEffect(() => {
        if (mode !== 'edit' || !userQuery.data) {
            return;
        }

        setEditName(userQuery.data.name);
        setEditEmail(userQuery.data.email);
    }, [mode, userQuery.data]);

    const sortedUsers = useMemo(
        () =>
            [...(usersQuery.data ?? [])].sort((a, b) => {
                const aDate = a.createdAt ?? '';
                const bDate = b.createdAt ?? '';
                return bDate.localeCompare(aDate);
            }),
        [usersQuery.data]
    );

    const createMutation = useMutation({
        mutationFn: async (payload: { id: string; name: string; email: string }) => {
            const response = await fetch('/api/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/ld+json',
                    Accept: 'application/ld+json'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw await toApiError(response, 'Unable to create user.');
            }
        },
        onSuccess: async (_data, payload) => {
            await waitForProjectedUser(payload.id, payload.name, payload.email);
            setFlashMessage('User created through event stream.');
            await invalidateUsersData(queryClient);
            onNavigate('/users');
        },
        onError: (error) => setMessage(getReadableError(error, 'Create failed.'))
    });

    const updateMutation = useMutation({
        mutationFn: async (payload: { id: string; name: string; email: string }) => {
            const response = await fetch(`/api/users/${payload.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/merge-patch+json',
                    Accept: 'application/ld+json'
                },
                body: JSON.stringify({ name: payload.name, email: payload.email })
            });

            if (!response.ok) {
                throw await toApiError(response, 'Unable to update user.');
            }
        },
        onSuccess: async (_data, payload) => {
            await waitForProjectedUser(payload.id, payload.name, payload.email);
            setFlashMessage('User updated through event stream.');
            await invalidateUsersData(queryClient);
            onNavigate('/users');
        },
        onError: (error) => setMessage(getReadableError(error, 'Update failed.'))
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            const response = await fetch(`/api/users/${id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/ld+json' }
            });

            if (!response.ok) {
                throw await toApiError(response, 'Unable to delete user.');
            }
        },
        onSuccess: async (_data, userId) => {
            await waitForUserAbsenceInProjection(userId);
            setMessage('User deleted through event stream.');
            await invalidateUsersData(queryClient);
        },
        onError: (error) => setMessage(getReadableError(error, 'Delete failed.'))
    });

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        setMessage('');

        createMutation.mutate({
            id: uuidv7(),
            name: createName.trim(),
            email: createEmail.trim().toLowerCase()
        });
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        setMessage('');

        if (!userId) {
            return;
        }

        updateMutation.mutate({
            id: userId,
            name: editName.trim(),
            email: editEmail.trim().toLowerCase()
        });
    };

    const deleteUser = (id: string) => {
        setMessage('');
        deleteMutation.mutate(id);
    };

    if (mode === 'create') {
        return (
            <div style={{ maxWidth: '820px', margin: '0 auto' }}>
                <div style={sectionHeaderStyle}>
                    <div>
                        <h1 style={titleStyle}>Create User</h1>
                        <p style={subtitleStyle}>
                            The client generates the UUID and sends it in the create command.
                        </p>
                    </div>
                    <button onClick={() => onNavigate('/users')} style={secondaryActionStyle}>
                        Back to Users
                    </button>
                </div>

                {message && <MessageCard text={message} />}

                <div style={cardStyle}>
                    <form onSubmit={submitCreate} style={{ display: 'grid', gap: '14px' }}>
                        <label style={labelStyle}>
                            Name
                            <input
                                value={createName}
                                onChange={(e) => setCreateName(e.target.value)}
                                required
                                minLength={2}
                                style={inputStyle}
                            />
                        </label>

                        <label style={labelStyle}>
                            Email
                            <input
                                type="email"
                                value={createEmail}
                                onChange={(e) => setCreateEmail(e.target.value)}
                                required
                                style={inputStyle}
                            />
                        </label>

                        <div style={{ display: 'flex', gap: '10px' }}>
                            <button
                                type="submit"
                                disabled={createMutation.isPending}
                                style={primaryActionStyle}
                            >
                                Create User
                            </button>
                            <button
                                type="button"
                                onClick={() => onNavigate('/users')}
                                style={secondaryActionStyle}
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        );
    }

    if (mode === 'edit') {
        return (
            <div style={{ maxWidth: '820px', margin: '0 auto' }}>
                <div style={sectionHeaderStyle}>
                    <div>
                        <h1 style={titleStyle}>Edit User</h1>
                        <p style={subtitleStyle}>
                            Update user data in a dedicated screen through event sourcing commands.
                        </p>
                    </div>
                    <button onClick={() => onNavigate('/users')} style={secondaryActionStyle}>
                        Back to Users
                    </button>
                </div>

                {message && <MessageCard text={message} />}

                <div style={cardStyle}>
                    {!userId ? (
                        <div style={errorStateStyle}>Missing user id.</div>
                    ) : userQuery.isLoading ? (
                        <div style={emptyStateStyle}>Loading user...</div>
                    ) : userQuery.error ? (
                        <div style={errorStateStyle}>Could not load user.</div>
                    ) : (
                        <form onSubmit={submitEdit} style={{ display: 'grid', gap: '14px' }}>
                            <label style={labelStyle}>
                                Name
                                <input
                                    value={editName}
                                    onChange={(e) => setEditName(e.target.value)}
                                    required
                                    minLength={2}
                                    style={inputStyle}
                                />
                            </label>

                            <label style={labelStyle}>
                                Email
                                <input
                                    type="email"
                                    value={editEmail}
                                    onChange={(e) => setEditEmail(e.target.value)}
                                    required
                                    style={inputStyle}
                                />
                            </label>

                            <div style={{ display: 'flex', gap: '10px' }}>
                                <button
                                    type="submit"
                                    disabled={updateMutation.isPending}
                                    style={primaryActionStyle}
                                >
                                    Save User Changes
                                </button>
                                <button
                                    type="button"
                                    onClick={() => onNavigate('/users')}
                                    style={secondaryActionStyle}
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
            <div style={sectionHeaderStyle}>
                <div>
                    <h1 style={titleStyle}>Users Management</h1>
                    <p style={subtitleStyle}>
                        Create, edit and delete users through event sourcing events.
                    </p>
                </div>

                <div style={{ display: 'flex', gap: '10px' }}>
                    <button
                        onClick={() => usersQuery.refetch()}
                        style={secondaryActionStyle}
                        type="button"
                    >
                        Refresh Users
                    </button>
                    <button
                        onClick={() => onNavigate('/users/new')}
                        style={primaryActionStyle}
                        type="button"
                    >
                        Create User
                    </button>
                </div>
            </div>

            {message && <MessageCard text={message} />}

            <div style={cardTableStyle}>
                {usersQuery.isLoading ? (
                    <div style={emptyStateStyle}>Loading users...</div>
                ) : usersQuery.error ? (
                    <div style={errorStateStyle}>Could not load users.</div>
                ) : sortedUsers.length === 0 ? (
                    <div style={emptyStateStyle}>No users in projection.</div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                            <thead>
                                <tr
                                    style={{
                                        borderBottom: '1px solid #e5e7eb',
                                        background: '#f9fafb'
                                    }}
                                >
                                    <th style={headerStyle}>ID</th>
                                    <th style={headerStyle}>Name</th>
                                    <th style={headerStyle}>Email</th>
                                    <th style={headerStyle}>Created At</th>
                                    <th style={headerStyle}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sortedUsers.map((user) => (
                                    <tr key={user.id} style={{ borderBottom: '1px solid #f3f4f6' }}>
                                        <td style={cellStyle}>
                                            <span
                                                style={{
                                                    fontFamily: 'monospace',
                                                    fontSize: '12px'
                                                }}
                                            >
                                                {user.id}
                                            </span>
                                        </td>
                                        <td style={cellStyle}>{user.name}</td>
                                        <td style={cellStyle}>{user.email}</td>
                                        <td style={cellStyle}>
                                            {user.createdAt
                                                ? new Date(user.createdAt).toLocaleString()
                                                : '-'}
                                        </td>
                                        <td style={cellStyle}>
                                            <div style={{ display: 'flex', gap: '8px' }}>
                                                <button
                                                    onClick={() =>
                                                        onNavigate(`/users/${user.id}/edit`)
                                                    }
                                                    style={secondaryActionStyle}
                                                    type="button"
                                                >
                                                    Edit User
                                                </button>
                                                <button
                                                    onClick={() => deleteUser(user.id)}
                                                    style={dangerActionStyle}
                                                    type="button"
                                                >
                                                    Delete User
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}

function MessageCard({ text }: { text: string }) {
    return (
        <div
            style={{
                marginBottom: '16px',
                borderRadius: '10px',
                border: '1px solid #d1fae5',
                backgroundColor: '#ecfdf5',
                color: '#065f46',
                padding: '10px 14px',
                fontSize: '14px',
                fontWeight: 500
            }}
        >
            {text}
        </div>
    );
}

function setFlashMessage(message: string): void {
    sessionStorage.setItem(FLASH_STORAGE_KEY, message);
}

function consumeFlashMessage(): string {
    const value = sessionStorage.getItem(FLASH_STORAGE_KEY);
    if (!value) {
        return '';
    }

    sessionStorage.removeItem(FLASH_STORAGE_KEY);
    return value;
}

async function invalidateUsersData(queryClient: ReturnType<typeof useQueryClient>): Promise<void> {
    await queryClient.invalidateQueries({ queryKey: ['users-management'] });
    await queryClient.invalidateQueries({ queryKey: ['users'] });
    await queryClient.invalidateQueries({ queryKey: ['events'] });
    await queryClient.invalidateQueries({ queryKey: ['snapshots'] });
}

async function toApiError(response: Response, fallbackMessage: string): Promise<Error> {
    try {
        const payload = (await response.json()) as ApiErrorPayload;
        const detail = payload['hydra:description'] ?? payload.detail ?? payload.message;
        if (detail && detail.trim().length > 0) {
            return new Error(detail.trim());
        }
    } catch {
        // Ignore payload parsing issues and fallback to default message.
    }

    return new Error(fallbackMessage);
}

function getReadableError(error: unknown, fallbackMessage: string): string {
    if (error instanceof Error && error.message.trim().length > 0) {
        return error.message;
    }

    return fallbackMessage;
}

async function waitForProjectedUser(
    userId: string,
    expectedName: string,
    expectedEmail: string,
    timeoutMs = 30000
): Promise<void> {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(`/api/users/${userId}?t=${Date.now()}`, {
                headers: { Accept: 'application/ld+json' }
            });

            if (response.ok) {
                const user = (await response.json()) as UserRow;
                if (user.name === expectedName && user.email === expectedEmail) {
                    return;
                }
            }
        } catch {
            // Ignore transient network/read-model errors while waiting for async projection.
        }

        await new Promise((resolve) => setTimeout(resolve, 300));
    }
}

async function waitForUserAbsenceInProjection(userId: string, timeoutMs = 30000): Promise<void> {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(`/api/users/${userId}?t=${Date.now()}`, {
                headers: { Accept: 'application/ld+json' }
            });

            if (response.status === 404) {
                return;
            }
        } catch {
            // Ignore transient network/read-model errors while waiting for async projection.
        }

        await new Promise((resolve) => setTimeout(resolve, 300));
    }
}

const sectionHeaderStyle: CSSProperties = {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '24px'
};

const titleStyle: CSSProperties = {
    margin: 0,
    fontSize: '28px',
    fontWeight: 700
};

const subtitleStyle: CSSProperties = {
    margin: '6px 0 0',
    color: '#6b7280'
};

const cardStyle: CSSProperties = {
    backgroundColor: '#fff',
    border: '1px solid #e5e7eb',
    borderRadius: '14px',
    padding: '20px'
};

const cardTableStyle: CSSProperties = {
    backgroundColor: '#fff',
    border: '1px solid #e5e7eb',
    borderRadius: '14px',
    overflow: 'hidden'
};

const headerStyle: CSSProperties = {
    textAlign: 'left',
    padding: '14px 18px',
    fontSize: '12px',
    textTransform: 'uppercase',
    letterSpacing: '0.04em',
    color: '#6b7280'
};

const cellStyle: CSSProperties = {
    padding: '14px 18px',
    fontSize: '14px',
    color: '#374151',
    verticalAlign: 'top'
};

const labelStyle: CSSProperties = {
    display: 'grid',
    gap: '6px',
    fontWeight: 600,
    color: '#374151',
    fontSize: '14px'
};

const inputStyle: CSSProperties = {
    border: '1px solid #e5e7eb',
    borderRadius: '8px',
    padding: '10px 12px',
    fontSize: '14px'
};

const primaryActionStyle: CSSProperties = {
    border: 'none',
    backgroundColor: '#111827',
    color: '#fff',
    borderRadius: '8px',
    padding: '10px 14px',
    cursor: 'pointer',
    fontWeight: 600
};

const secondaryActionStyle: CSSProperties = {
    border: '1px solid #d1d5db',
    backgroundColor: '#fff',
    color: '#374151',
    borderRadius: '8px',
    padding: '8px 12px',
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '13px'
};

const dangerActionStyle: CSSProperties = {
    border: '1px solid #fecaca',
    backgroundColor: '#fff1f2',
    color: '#b91c1c',
    borderRadius: '8px',
    padding: '8px 12px',
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '13px'
};

const emptyStateStyle: CSSProperties = {
    padding: '42px',
    color: '#6b7280',
    textAlign: 'center'
};

const errorStateStyle: CSSProperties = {
    padding: '42px',
    color: '#b91c1c',
    textAlign: 'center'
};
