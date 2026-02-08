import React, { useState } from 'react';

interface FormData {
    pax: number;
    budget: number;
    clientName: string;
    clientEmail: string;
}

export function Wizard() {
    const [formData, setFormData] = useState<FormData>({
        pax: 2,
        budget: 50,
        clientName: '',
        clientEmail: '',
    });
    const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');

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
                headers: {
                    'Content-Type': 'application/ld+json',
                },
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
            <div style={{ maxWidth: '600px', margin: '40px auto', textAlign: 'center' }}>
                <h2>🎉 Request Received!</h2>
                <p>We are processing your booking request. You will receive an email shortly.</p>
                <button onClick={() => setStatus('idle')} style={{ padding: '10px 20px', cursor: 'pointer' }}>
                    New Booking
                </button>
            </div>
        );
    }

    return (
        <div style={{ maxWidth: '600px', margin: '40px auto', fontFamily: 'sans-serif' }}>
            <h1>📅 Booking Wizard</h1>
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: '5px' }}>Number of People (Pax)</label>
                    <input
                        type="number"
                        name="pax"
                        min="1"
                        value={formData.pax}
                        onChange={handleChange}
                        required
                        style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                    />
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: '5px' }}>Budget per Person (€)</label>
                    <input
                        type="number"
                        name="budget"
                        min="10"
                        value={formData.budget}
                        onChange={handleChange}
                        required
                        style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                    />
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: '5px' }}>Your Name</label>
                    <input
                        type="text"
                        name="clientName"
                        value={formData.clientName}
                        onChange={handleChange}
                        required
                        style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                    />
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: '5px' }}>Email Address</label>
                    <input
                        type="email"
                        name="clientEmail"
                        value={formData.clientEmail}
                        onChange={handleChange}
                        required
                        style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                    />
                </div>

                <button
                    type="submit"
                    disabled={status === 'submitting'}
                    style={{
                        padding: '12px',
                        backgroundColor: '#007bff',
                        color: 'white',
                        border: 'none',
                        borderRadius: '4px',
                        cursor: status === 'submitting' ? 'not-allowed' : 'pointer',
                        fontSize: '16px'
                    }}
                >
                    {status === 'submitting' ? 'Processing...' : 'Submit Request'}
                </button>

                {status === 'error' && (
                    <p style={{ color: 'red', textAlign: 'center' }}>Something went wrong. Please try again.</p>
                )}
            </form>
        </div>
    );
}
