import React, { useState } from 'react';

const AccountList = ({ accounts, selectedAccount, onSelectAccount, onAccountsChange, detailed = false }) => {
    const [showAddForm, setShowAddForm] = useState(false);
    const [loading, setLoading] = useState(false);
    const [formData, setFormData] = useState({
        email_address: '',
        display_name: '',
        imap_host: '',
        imap_port: '993',
        imap_encryption: 'ssl',
        imap_username: '',
        imap_password: '',
        smtp_host: '',
        smtp_port: '587',
        smtp_encryption: 'tls',
        smtp_username: '',
        smtp_password: ''
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const response = await fetch('/api/accounts', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            if (!response.ok) {
                throw new Error('Failed to create account');
            }

            const data = await response.json();
            setShowAddForm(false);
            setFormData({
                email_address: '',
                display_name: '',
                imap_host: '',
                imap_port: '993',
                imap_encryption: 'ssl',
                imap_username: '',
                imap_password: '',
                smtp_host: '',
                smtp_port: '587',
                smtp_encryption: 'tls',
                smtp_username: '',
                smtp_password: ''
            });
            onAccountsChange();
        } catch (error) {
            alert('Error creating account: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    const testConnection = async (account, type) => {
        try {
            const endpoint = type === 'imap' ? 'test-connection' : 'test-smtp';
            const response = await fetch(`/api/accounts/${account.id}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();
            alert(data.success ? `${type.toUpperCase()} connection successful!` : `${type.toUpperCase()} connection failed: ${data.message}`);
        } catch (error) {
            alert(`Error testing ${type.toUpperCase()} connection: ` + error.message);
        }
    };

    if (!detailed) {
        return (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                <div className="p-4 border-b border-gray-200">
                    <div className="flex justify-between items-center">
                        <h3 className="font-medium text-gray-900">Accounts</h3>
                        <button
                            onClick={() => setShowAddForm(true)}
                            className="text-blue-600 hover:text-blue-700 text-sm font-medium"
                        >
                            + Add
                        </button>
                    </div>
                </div>
                <div className="divide-y divide-gray-200">
                    {accounts.map((account) => (
                        <div
                            key={account.id}
                            onClick={() => onSelectAccount(account)}
                            className={`p-4 cursor-pointer hover:bg-gray-50 transition-colors ${
                                selectedAccount?.id === account.id ? 'bg-blue-50 border-r-2 border-blue-500' : ''
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-gray-900">{account.display_name || account.email_address}</p>
                                    <p className="text-sm text-gray-500">{account.email_address}</p>
                                </div>
                                <div className={`w-2 h-2 rounded-full ${
                                    account.is_active ? 'bg-green-400' : 'bg-red-400'
                                }`}></div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Add Account Form */}
            {showAddForm && (
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="text-lg font-medium text-gray-900">Add Email Account</h3>
                        <button
                            onClick={() => setShowAddForm(false)}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>
                    
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address *
                                </label>
                                <input
                                    type="email"
                                    required
                                    value={formData.email_address}
                                    onChange={(e) => setFormData({...formData, email_address: e.target.value})}
                                    className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Display Name
                                </label>
                                <input
                                    type="text"
                                    value={formData.display_name}
                                    onChange={(e) => setFormData({...formData, display_name: e.target.value})}
                                    className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>

                        {/* IMAP Settings */}
                        <div className="border-t pt-4">
                            <h4 className="font-medium text-gray-900 mb-3">IMAP Settings (Incoming Mail)</h4>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        IMAP Host *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={formData.imap_host}
                                        onChange={(e) => setFormData({...formData, imap_host: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="imap.gmail.com"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Port *
                                    </label>
                                    <input
                                        type="number"
                                        required
                                        value={formData.imap_port}
                                        onChange={(e) => setFormData({...formData, imap_port: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* SMTP Settings */}
                        <div className="border-t pt-4">
                            <h4 className="font-medium text-gray-900 mb-3">SMTP Settings (Outgoing Mail)</h4>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        SMTP Host *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={formData.smtp_host}
                                        onChange={(e) => setFormData({...formData, smtp_host: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="smtp.gmail.com"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Port *
                                    </label>
                                    <input
                                        type="number"
                                        required
                                        value={formData.smtp_port}
                                        onChange={(e) => setFormData({...formData, smtp_port: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Credentials */}
                        <div className="border-t pt-4">
                            <h4 className="font-medium text-gray-900 mb-3">Authentication</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Username *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={formData.imap_username}
                                        onChange={(e) => setFormData({
                                            ...formData, 
                                            imap_username: e.target.value,
                                            smtp_username: e.target.value
                                        })}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Password *
                                    </label>
                                    <input
                                        type="password"
                                        required
                                        value={formData.imap_password}
                                        onChange={(e) => setFormData({
                                            ...formData, 
                                            imap_password: e.target.value,
                                            smtp_password: e.target.value
                                        })}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end space-x-3 pt-4">
                            <button
                                type="button"
                                onClick={() => setShowAddForm(false)}
                                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={loading}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                            >
                                {loading ? 'Adding...' : 'Add Account'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Accounts List */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                <div className="p-4 border-b border-gray-200">
                    <div className="flex justify-between items-center">
                        <h3 className="text-lg font-medium text-gray-900">Email Accounts</h3>
                        <button
                            onClick={() => setShowAddForm(true)}
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            + Add Account
                        </button>
                    </div>
                </div>
                
                <div className="divide-y divide-gray-200">
                    {accounts.length === 0 ? (
                        <div className="p-8 text-center">
                            <div className="text-gray-400 text-4xl mb-4">📧</div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">No Email Accounts</h3>
                            <p className="text-gray-600 mb-4">Add your first email account to get started.</p>
                            <button
                                onClick={() => setShowAddForm(true)}
                                className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Add Account
                            </button>
                        </div>
                    ) : (
                        accounts.map((account) => (
                            <div key={account.id} className="p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center space-x-3">
                                            <div className={`w-3 h-3 rounded-full ${
                                                account.is_active ? 'bg-green-400' : 'bg-red-400'
                                            }`}></div>
                                            <div>
                                                <h4 className="font-medium text-gray-900">
                                                    {account.display_name || account.email_address}
                                                </h4>
                                                <p className="text-sm text-gray-500">{account.email_address}</p>
                                            </div>
                                        </div>
                                        <div className="mt-2 text-sm text-gray-600">
                                            <p>IMAP: {account.imap_host}:{account.imap_port}</p>
                                            <p>SMTP: {account.smtp_host}:{account.smtp_port}</p>
                                        </div>
                                    </div>
                                    <div className="flex space-x-2">
                                        <button
                                            onClick={() => testConnection(account, 'imap')}
                                            className="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
                                        >
                                            Test IMAP
                                        </button>
                                        <button
                                            onClick={() => testConnection(account, 'smtp')}
                                            className="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
                                        >
                                            Test SMTP
                                        </button>
                                        <button
                                            onClick={() => onSelectAccount(account)}
                                            className="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors"
                                        >
                                            Select
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
};

export default AccountList;