import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

type QuoteItem = {
    id?: string;
    supplierId?: string;
    productId?: string;
    bookingId?: string;
    price?: number;
    status?: string;
    createdAt?: string;
};

export function SupplierResponses() {
    const queryClient = useQueryClient();

    const { data: quotes = [] } = useQuery<QuoteItem[]>({
        queryKey: ['quotes'],
        queryFn: async () => {
            const res = await fetch(`/api/quotes?t=${Date.now()}`);
            if (!res.ok) {
                return [];
            }
            const data = await res.json();
            return data['hydra:member'] || (Array.isArray(data) ? data : []);
        },
        refetchInterval: 3000
    });

    const simulateSupplierResponseMutation = useMutation({
        mutationFn: async (quoteId: string) => {
            const res = await fetch(`/api/quotes/${quoteId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/merge-patch+json',
                    Accept: 'application/ld+json'
                },
                body: JSON.stringify({ status: 'quoted' })
            });
            if (!res.ok) {
                throw new Error('Supplier response failed');
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['quotes'] });
            queryClient.invalidateQueries({ queryKey: ['events'] });
        }
    });

    const pendingQuotes = [...quotes]
        .filter((quote) => String(quote.status || '') === 'pending')
        .sort((a, b) => String(b.createdAt || '').localeCompare(String(a.createdAt || '')));

    return (
        <div style={{ maxWidth: '980px', margin: '0 auto', paddingBottom: '80px' }}>
            <header style={{ marginBottom: '36px' }}>
                <h1
                    style={{
                        margin: 0,
                        fontSize: '34px',
                        fontWeight: 900,
                        color: '#1c1917',
                        letterSpacing: '-0.03em'
                    }}
                >
                    Supplier Response Simulator
                </h1>
                <p style={{ marginTop: '10px', color: '#78716c', fontSize: '17px' }}>
                    Simulate provider feedback by moving pending quotes to quoted.
                </p>
            </header>

            <section
                style={{
                    backgroundColor: '#fff',
                    borderRadius: '24px',
                    border: '1px solid #e7e5e4',
                    boxShadow: '0 12px 20px -8px rgba(0,0,0,0.08)',
                    overflow: 'hidden'
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        padding: '16px 20px',
                        backgroundColor: '#fffbeb',
                        borderBottom: '1px solid #fef3c7'
                    }}
                >
                    <strong style={{ color: '#92400e', fontSize: '13px' }}>Pending Quotes</strong>
                    <span
                        style={{
                            fontSize: '12px',
                            fontWeight: 700,
                            color: '#a16207',
                            backgroundColor: '#fef3c7',
                            borderRadius: '999px',
                            padding: '4px 10px'
                        }}
                    >
                        {pendingQuotes.length}
                    </span>
                </div>

                {pendingQuotes.length === 0 ? (
                    <div style={{ padding: '26px 20px', color: '#a8a29e', fontSize: '14px' }}>
                        No pending quotes available.
                    </div>
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        {pendingQuotes.map((quote) => {
                            const quoteId = String(quote.id || '');
                            const supplierId = String(quote.supplierId || '');
                            const productId = String(quote.productId || '');
                            const bookingId = String(quote.bookingId || '');
                            const price = Number(quote.price || 0);
                            const isSending =
                                simulateSupplierResponseMutation.isPending &&
                                simulateSupplierResponseMutation.variables === quoteId;

                            return (
                                <div
                                    key={quoteId}
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center',
                                        gap: '12px',
                                        padding: '16px 20px',
                                        borderBottom: '1px solid #f5f5f4'
                                    }}
                                >
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexWrap: 'wrap',
                                                gap: '10px',
                                                alignItems: 'center'
                                            }}
                                        >
                                            <strong style={{ fontSize: '14px', color: '#1c1917' }}>
                                                Quote ...{quoteId.slice(-6)}
                                            </strong>
                                            <span
                                                style={{
                                                    fontSize: '12px',
                                                    color: '#78716c',
                                                    backgroundColor: '#fafaf9',
                                                    padding: '2px 8px',
                                                    borderRadius: '999px',
                                                    border: '1px solid #e7e5e4'
                                                }}
                                            >
                                                Booking ...{bookingId.slice(-6)}
                                            </span>
                                            <span
                                                style={{
                                                    fontWeight: 800,
                                                    color: '#166534',
                                                    fontSize: '13px'
                                                }}
                                            >
                                                EUR {price.toFixed(2)}
                                            </span>
                                        </div>
                                        <div style={{ color: '#78716c', fontSize: '12px' }}>
                                            Supplier ...{supplierId.slice(-6)} · Product ...
                                            {productId.slice(-6)}
                                        </div>
                                    </div>

                                    <button
                                        onClick={() => simulateSupplierResponseMutation.mutate(quoteId)}
                                        disabled={!quoteId || simulateSupplierResponseMutation.isPending}
                                        style={{
                                            border: 'none',
                                            backgroundColor: '#166534',
                                            color: '#fff',
                                            padding: '10px 16px',
                                            borderRadius: '10px',
                                            fontWeight: 800,
                                            cursor: 'pointer',
                                            minWidth: '84px'
                                        }}
                                    >
                                        {isSending ? 'Sending' : 'Send'}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}
            </section>
        </div>
    );
}
