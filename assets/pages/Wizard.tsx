import React, { useState } from 'react';
import { v4 as uuidv4 } from 'uuid';

interface FormData {
    bookingId: string;
    pax: number;
    budget: number;
    clientName: string;
    clientEmail: string;
}

export function Wizard() {
    const [formData, setFormData] = useState<FormData>({
        bookingId: uuidv4(),
        pax: 2,
        budget: 50,
        clientName: '',
        clientEmail: '',
    });
    const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');

    const resetForm = () => {
        setFormData({
            bookingId: uuidv4(),
            pax: 2,
            budget: 50,
            clientName: '',
            clientEmail: '',
        });
        setStatus('idle');
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: name === 'pax' || name === 'budget' ? Number(value) : value
        }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setStatus('submitting');

        try {
            const response = await fetch('/api/booking-wizard', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
            });

            if (!response.ok) throw new Error('Submission failed');
            setStatus('success');
        } catch (error) {
            console.error(error);
            setStatus('error');
        }
    };

    if (status === 'success') {
        return (
            <div style={{ maxWidth: '500px', margin: '100px auto', textAlign: 'center', backgroundColor: '#fff', padding: '48px', borderRadius: '24px', boxShadow: '0 10px 25px -5px rgba(0,0,0,0.05)' }}>
                <div style={{ color: '#10b981', marginBottom: '24px' }}>
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h2 style={{ fontSize: '24px', fontWeight: 700, marginBottom: '12px' }}>Request Received</h2>
                <p style={{ color: '#6b7280', marginBottom: '32px', lineHeight: '1.6' }}>Your booking event has been recorded in the store. Our projectors are syncing the state now.</p>
                <button 
                    onClick={resetForm} 
                    style={{ 
                        padding: '12px 24px', 
                        backgroundColor: '#6366f1', 
                        color: 'white', 
                        border: 'none', 
                        borderRadius: '10px', 
                        cursor: 'pointer',
                        fontWeight: 600
                    }}
                >
                    Create Another Booking
                </button>
            </div>
        );
    }

    const inputStyle = {
        width: '100%',
        padding: '12px 16px',
        borderRadius: '10px',
        border: '1px solid #e5e7eb',
        fontSize: '15px',
        outline: 'none',
        transition: 'border-color 0.2s',
        boxSizing: 'border-box' as const,
        marginTop: '6px'
    };

    const labelStyle = {
        fontSize: '14px',
        fontWeight: 600,
        color: '#374151'
    };

    return (
        <div style={{ maxWidth: '500px', margin: '40px auto' }}>
            <div style={{ backgroundColor: '#fff', padding: '40px', borderRadius: '24px', boxShadow: '0 4px 20px rgba(0,0,0,0.05)', border: '1px solid #f3f4f6' }}>
                <h1 style={{ margin: '0 0 8px', fontSize: '24px', fontWeight: 700 }}>New Booking</h1>
                <p style={{ margin: '0 0 32px', color: '#6b7280', fontSize: '15px' }}>Fill in the details to emit a new domain event.</p>
                
                <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                    <div>
                        <label style={labelStyle}>Client Name</label>
                        <input type="text" name="clientName" value={formData.clientName} onChange={handleChange} required style={inputStyle} placeholder="e.g. John Doe" />
                    </div>

                    <div>
                        <label style={labelStyle}>Email Address</label>
                        <input type="email" name="clientEmail" value={formData.clientEmail} onChange={handleChange} required style={inputStyle} placeholder="john@example.com" />
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        <div>
                            <label style={labelStyle}>People (Pax)</label>
                            <input type="number" name="pax" min="1" value={formData.pax} onChange={handleChange} required style={inputStyle} />
                        </div>
                        <div>
                            <label style={labelStyle}>Budget (€)</label>
                            <input type="number" name="budget" min="10" value={formData.budget} onChange={handleChange} required style={inputStyle} />
                        </div>
                    </div>

                    <div style={{ marginTop: '12px' }}>
                        <button
                            type="submit"
                            disabled={status === 'submitting'}
                            style={{
                                width: '100%',
                                padding: '14px',
                                backgroundColor: '#111827',
                                color: 'white',
                                border: 'none',
                                borderRadius: '12px',
                                cursor: status === 'submitting' ? 'not-allowed' : 'pointer',
                                fontSize: '16px',
                                fontWeight: 600,
                                transition: 'opacity 0.2s'
                            }}
                        >
                            {status === 'submitting' ? 'Processing...' : 'Submit Booking'}
                        </button>
                    </div>

                    {status === 'error' && (
                        <div style={{ padding: '12px', backgroundColor: '#fff1f2', color: '#e11d48', borderRadius: '8px', fontSize: '14px', textAlign: 'center', fontWeight: 500 }}>
                            Something went wrong. Please try again.
                        </div>
                    )}
                </form>
            </div>
            
            <div style={{ marginTop: '24px', textAlign: 'center' }}>
                <span style={{ fontSize: '12px', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Client Side ID</span>
                <div style={{ fontSize: '11px', color: '#6b7280', fontFamily: 'monospace', marginTop: '4px' }}>{formData.bookingId}</div>
            </div>
        </div>
    );
}