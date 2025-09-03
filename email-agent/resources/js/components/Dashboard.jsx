import React, { useState, useEffect } from 'react';
import Layout from './Layout';
import AccountList from './AccountList';
import EmailList from './EmailList';
import EmailDetail from './EmailDetail';
import RulesList from './RulesList';
import StatsOverview from './StatsOverview';

const Dashboard = () => {
    const [activeTab, setActiveTab] = useState('emails');
    const [selectedAccount, setSelectedAccount] = useState(null);
    const [selectedEmail, setSelectedEmail] = useState(null);
    const [accounts, setAccounts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Fetch accounts on component mount
    useEffect(() => {
        fetchAccounts();
    }, []);

    const fetchAccounts = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/accounts', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch accounts');
            }
            
            const data = await response.json();
            setAccounts(data.accounts || []);
            
            // Auto-select first account if available
            if (data.accounts && data.accounts.length > 0) {
                setSelectedAccount(data.accounts[0]);
            }
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const tabs = [
        { id: 'emails', name: 'Emails', icon: '📧' },
        { id: 'rules', name: 'Rules', icon: '⚙️' },
        { id: 'stats', name: 'Statistics', icon: '📊' },
        { id: 'accounts', name: 'Accounts', icon: '👤' },
    ];

    if (loading) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Loading dashboard...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="text-red-500 text-6xl mb-4">⚠️</div>
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Error Loading Dashboard</h2>
                    <p className="text-gray-600 mb-4">{error}</p>
                    <button 
                        onClick={fetchAccounts}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    return (
        <Layout>
            {/* Account Status */}
            <div className="mb-6">
                <div className="flex justify-between items-center">
                    <div className="text-sm text-gray-600">
                        {accounts.length} account{accounts.length !== 1 ? 's' : ''} connected
                    </div>
                    {selectedAccount && (
                        <div className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                            {selectedAccount.email_address}
                        </div>
                    )}
                </div>
            </div>

            {/* Navigation Tabs */}
            <nav className="bg-white border border-gray-200 rounded-lg mb-6">
                <div className="px-6">
                    <div className="flex space-x-8">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                                    activeTab === tab.id
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                <span className="mr-2">{tab.icon}</span>
                                {tab.name}
                            </button>
                        ))}
                    </div>
                </div>
            </nav>

            <div className="flex gap-6">
                    {/* Sidebar */}
                    <div className="w-80 flex-shrink-0">
                        {activeTab === 'accounts' ? (
                            <AccountList 
                                accounts={accounts}
                                selectedAccount={selectedAccount}
                                onSelectAccount={setSelectedAccount}
                                onAccountsChange={fetchAccounts}
                            />
                        ) : (
                            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                                <h3 className="font-medium text-gray-900 mb-3">Account Selection</h3>
                                <select 
                                    value={selectedAccount?.id || ''}
                                    onChange={(e) => {
                                        const account = accounts.find(acc => acc.id === parseInt(e.target.value));
                                        setSelectedAccount(account);
                                    }}
                                    className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Select an account...</option>
                                    {accounts.map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.email_address}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {/* Main Content Area */}
                    <div className="flex-1">
                        {!selectedAccount && activeTab !== 'accounts' ? (
                            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                                <div className="text-gray-400 text-6xl mb-4">📧</div>
                                <h3 className="text-lg font-medium text-gray-900 mb-2">Select an Account</h3>
                                <p className="text-gray-600">Choose an email account from the sidebar to get started.</p>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                {activeTab === 'emails' && (
                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <EmailList 
                                            account={selectedAccount}
                                            selectedEmail={selectedEmail}
                                            onSelectEmail={setSelectedEmail}
                                        />
                                        {selectedEmail && (
                                            <EmailDetail 
                                                email={selectedEmail}
                                                account={selectedAccount}
                                            />
                                        )}
                                    </div>
                                )}
                                
                                {activeTab === 'rules' && (
                                    <RulesList account={selectedAccount} />
                                )}
                                
                                {activeTab === 'stats' && (
                                    <StatsOverview account={selectedAccount} />
                                )}
                                
                                {activeTab === 'accounts' && (
                                    <AccountList 
                                        accounts={accounts}
                                        selectedAccount={selectedAccount}
                                        onSelectAccount={setSelectedAccount}
                                        onAccountsChange={fetchAccounts}
                                        detailed={true}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                </div>
        </Layout>
    );
};

export default Dashboard;