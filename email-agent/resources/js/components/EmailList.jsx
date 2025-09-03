import React, { useState, useEffect } from 'react';

const EmailList = ({ selectedAccount, selectedEmail, onSelectEmail, onEmailsChange }) => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [filters, setFilters] = useState({
        status: 'all',
        category: 'all',
        priority: 'all',
        search: ''
    });
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0
    });

    useEffect(() => {
        if (selectedAccount) {
            fetchEmails();
        }
    }, [selectedAccount, filters, pagination.current_page]);

    const fetchEmails = async () => {
        if (!selectedAccount) return;
        
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({
                account_id: selectedAccount.id,
                page: pagination.current_page,
                per_page: pagination.per_page,
                ...filters
            });

            const response = await fetch(`/api/emails?${params}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch emails');
            }

            const data = await response.json();
            setEmails(data.data || []);
            setPagination({
                current_page: data.current_page || 1,
                last_page: data.last_page || 1,
                per_page: data.per_page || 20,
                total: data.total || 0
            });
        } catch (error) {
            setError(error.message);
        } finally {
            setLoading(false);
        }
    };

    const syncEmails = async () => {
        if (!selectedAccount) return;
        
        setLoading(true);
        try {
            const response = await fetch(`/api/emails/sync/${selectedAccount.id}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to sync emails');
            }

            await fetchEmails();
            if (onEmailsChange) onEmailsChange();
        } catch (error) {
            setError(error.message);
        } finally {
            setLoading(false);
        }
    };

    const markAsRead = async (emailId) => {
        try {
            const response = await fetch(`/api/emails/${emailId}/mark-read`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                setEmails(emails.map(email => 
                    email.id === emailId ? { ...email, is_read: true } : email
                ));
            }
        } catch (error) {
            console.error('Failed to mark email as read:', error);
        }
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 1) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else if (diffDays <= 7) {
            return date.toLocaleDateString([], { weekday: 'short' });
        } else {
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
    };

    const getPriorityColor = (priority) => {
        switch (priority) {
            case 'high': return 'text-red-600 bg-red-100';
            case 'medium': return 'text-yellow-600 bg-yellow-100';
            case 'low': return 'text-green-600 bg-green-100';
            default: return 'text-gray-600 bg-gray-100';
        }
    };

    const getCategoryColor = (category) => {
        switch (category) {
            case 'urgent': return 'text-red-600 bg-red-100';
            case 'important': return 'text-orange-600 bg-orange-100';
            case 'support': return 'text-blue-600 bg-blue-100';
            case 'sales': return 'text-green-600 bg-green-100';
            case 'marketing': return 'text-purple-600 bg-purple-100';
            default: return 'text-gray-600 bg-gray-100';
        }
    };

    if (!selectedAccount) {
        return (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <div className="text-gray-400 text-4xl mb-4">📬</div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">No Account Selected</h3>
                <p className="text-gray-600">Select an email account to view emails.</p>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            {/* Header */}
            <div className="p-4 border-b border-gray-200">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-medium text-gray-900">
                        Emails - {selectedAccount.display_name || selectedAccount.email_address}
                    </h3>
                    <button
                        onClick={syncEmails}
                        disabled={loading}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                    >
                        {loading ? 'Syncing...' : '🔄 Sync'}
                    </button>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap gap-4">
                    <div>
                        <input
                            type="text"
                            placeholder="Search emails..."
                            value={filters.search}
                            onChange={(e) => setFilters({...filters, search: e.target.value})}
                            className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <select
                        value={filters.status}
                        onChange={(e) => setFilters({...filters, status: e.target.value})}
                        className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="all">All Status</option>
                        <option value="unread">Unread</option>
                        <option value="read">Read</option>
                        <option value="processed">Processed</option>
                        <option value="escalated">Escalated</option>
                    </select>
                    <select
                        value={filters.category}
                        onChange={(e) => setFilters({...filters, category: e.target.value})}
                        className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="all">All Categories</option>
                        <option value="urgent">Urgent</option>
                        <option value="important">Important</option>
                        <option value="support">Support</option>
                        <option value="sales">Sales</option>
                        <option value="marketing">Marketing</option>
                    </select>
                    <select
                        value={filters.priority}
                        onChange={(e) => setFilters({...filters, priority: e.target.value})}
                        className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="all">All Priorities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
            </div>

            {/* Email List */}
            <div className="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                {error && (
                    <div className="p-4 bg-red-50 border-l-4 border-red-400">
                        <p className="text-red-700">Error: {error}</p>
                    </div>
                )}

                {loading && emails.length === 0 ? (
                    <div className="p-8 text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p className="text-gray-600">Loading emails...</p>
                    </div>
                ) : emails.length === 0 ? (
                    <div className="p-8 text-center">
                        <div className="text-gray-400 text-4xl mb-4">📭</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No Emails Found</h3>
                        <p className="text-gray-600">Try syncing or adjusting your filters.</p>
                    </div>
                ) : (
                    emails.map((email) => (
                        <div
                            key={email.id}
                            onClick={() => {
                                onSelectEmail(email);
                                if (!email.is_read) {
                                    markAsRead(email.id);
                                }
                            }}
                            className={`p-4 cursor-pointer hover:bg-gray-50 transition-colors ${
                                selectedEmail?.id === email.id ? 'bg-blue-50 border-r-2 border-blue-500' : ''
                            } ${!email.is_read ? 'bg-blue-25' : ''}`}
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center space-x-2 mb-1">
                                        <p className={`font-medium truncate ${
                                            !email.is_read ? 'text-gray-900' : 'text-gray-700'
                                        }`}>
                                            {email.sender_name || email.sender_email}
                                        </p>
                                        {!email.is_read && (
                                            <div className="w-2 h-2 bg-blue-600 rounded-full"></div>
                                        )}
                                    </div>
                                    <p className={`text-sm truncate mb-1 ${
                                        !email.is_read ? 'text-gray-900 font-medium' : 'text-gray-600'
                                    }`}>
                                        {email.subject}
                                    </p>
                                    <p className="text-sm text-gray-500 truncate">
                                        {email.preview || 'No preview available'}
                                    </p>
                                    
                                    {/* Tags and Status */}
                                    <div className="flex items-center space-x-2 mt-2">
                                        {email.category && (
                                            <span className={`px-2 py-1 text-xs rounded-full ${
                                                getCategoryColor(email.category)
                                            }`}>
                                                {email.category}
                                            </span>
                                        )}
                                        {email.priority && (
                                            <span className={`px-2 py-1 text-xs rounded-full ${
                                                getPriorityColor(email.priority)
                                            }`}>
                                                {email.priority}
                                            </span>
                                        )}
                                        {email.status && email.status !== 'unprocessed' && (
                                            <span className="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                                {email.status}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                
                                <div className="flex flex-col items-end space-y-1">
                                    <span className="text-xs text-gray-500">
                                        {formatDate(email.received_at)}
                                    </span>
                                    {email.has_attachments && (
                                        <div className="text-gray-400 text-sm">📎</div>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Pagination */}
            {pagination.last_page > 1 && (
                <div className="p-4 border-t border-gray-200">
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-700">
                            Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of {pagination.total} emails
                        </div>
                        <div className="flex space-x-2">
                            <button
                                onClick={() => setPagination({...pagination, current_page: pagination.current_page - 1})}
                                disabled={pagination.current_page === 1}
                                className="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors disabled:opacity-50"
                            >
                                Previous
                            </button>
                            <span className="px-3 py-1 text-sm text-gray-700">
                                Page {pagination.current_page} of {pagination.last_page}
                            </span>
                            <button
                                onClick={() => setPagination({...pagination, current_page: pagination.current_page + 1})}
                                disabled={pagination.current_page === pagination.last_page}
                                className="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors disabled:opacity-50"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmailList;