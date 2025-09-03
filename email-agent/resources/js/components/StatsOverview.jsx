import React, { useState, useEffect } from 'react';

const StatsOverview = ({ selectedAccount }) => {
    const [stats, setStats] = useState(null);
    const [llmStats, setLlmStats] = useState(null);
    const [smtpStats, setSmtpStats] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [timeRange, setTimeRange] = useState('7d');

    useEffect(() => {
        fetchStats();
    }, [selectedAccount, timeRange]);

    const fetchStats = async () => {
        setLoading(true);
        setError(null);

        try {
            // Fetch email stats
            const emailParams = new URLSearchParams({ range: timeRange });
            if (selectedAccount) {
                emailParams.append('account_id', selectedAccount.id);
            }
            
            const emailResponse = await fetch(`/api/emails/stats?${emailParams}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            // Fetch LLM stats
            const llmResponse = await fetch(`/api/llm/stats?${emailParams}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            // Fetch SMTP stats
            const smtpResponse = await fetch(`/api/smtp/stats?${emailParams}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            if (emailResponse.ok) {
                const emailData = await emailResponse.json();
                setStats(emailData);
            }

            if (llmResponse.ok) {
                const llmData = await llmResponse.json();
                setLlmStats(llmData);
            }

            if (smtpResponse.ok) {
                const smtpData = await smtpResponse.json();
                setSmtpStats(smtpData);
            }
        } catch (error) {
            setError(error.message);
        } finally {
            setLoading(false);
        }
    };

    const formatNumber = (num) => {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num?.toString() || '0';
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 4
        }).format(amount || 0);
    };

    const getPercentageChange = (current, previous) => {
        if (!previous || previous === 0) return 0;
        return ((current - previous) / previous * 100).toFixed(1);
    };

    const StatCard = ({ title, value, change, icon, color = 'blue', subtitle }) => (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-600">{title}</p>
                    <p className="text-2xl font-semibold text-gray-900">{value}</p>
                    {subtitle && (
                        <p className="text-sm text-gray-500">{subtitle}</p>
                    )}
                </div>
                <div className={`p-3 rounded-full bg-${color}-100`}>
                    <span className={`text-${color}-600 text-xl`}>{icon}</span>
                </div>
            </div>
            {change !== undefined && (
                <div className="mt-4">
                    <span className={`text-sm font-medium ${
                        change >= 0 ? 'text-green-600' : 'text-red-600'
                    }`}>
                        {change >= 0 ? '↗' : '↘'} {Math.abs(change)}%
                    </span>
                    <span className="text-sm text-gray-500 ml-1">vs previous period</span>
                </div>
            )}
        </div>
    );

    if (loading && !stats) {
        return (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p className="text-gray-600">Loading statistics...</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <h2 className="text-2xl font-semibold text-gray-900">
                    {selectedAccount ? `${selectedAccount.display_name || selectedAccount.email_address} - Statistics` : 'Overall Statistics'}
                </h2>
                <select
                    value={timeRange}
                    onChange={(e) => setTimeRange(e.target.value)}
                    className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="1d">Last 24 Hours</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                </select>
            </div>

            {error && (
                <div className="bg-red-50 border-l-4 border-red-400 p-4">
                    <p className="text-red-700">Error loading statistics: {error}</p>
                </div>
            )}

            {/* Email Statistics */}
            {stats && (
                <div>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Email Processing</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <StatCard
                            title="Total Emails"
                            value={formatNumber(stats.total_emails)}
                            change={getPercentageChange(stats.total_emails, stats.previous_total_emails)}
                            icon="📧"
                            color="blue"
                        />
                        <StatCard
                            title="Processed"
                            value={formatNumber(stats.processed_emails)}
                            change={getPercentageChange(stats.processed_emails, stats.previous_processed_emails)}
                            icon="✅"
                            color="green"
                            subtitle={`${((stats.processed_emails / stats.total_emails) * 100 || 0).toFixed(1)}% of total`}
                        />
                        <StatCard
                            title="Unread"
                            value={formatNumber(stats.unread_emails)}
                            change={getPercentageChange(stats.unread_emails, stats.previous_unread_emails)}
                            icon="📬"
                            color="yellow"
                            subtitle={`${((stats.unread_emails / stats.total_emails) * 100 || 0).toFixed(1)}% of total`}
                        />
                        <StatCard
                            title="Escalated"
                            value={formatNumber(stats.escalated_emails)}
                            change={getPercentageChange(stats.escalated_emails, stats.previous_escalated_emails)}
                            icon="🚨"
                            color="red"
                            subtitle={`${((stats.escalated_emails / stats.total_emails) * 100 || 0).toFixed(1)}% of total`}
                        />
                    </div>
                </div>
            )}

            {/* Category Breakdown */}
            {stats?.categories && (
                <div>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Email Categories</h3>
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                            {Object.entries(stats.categories).map(([category, count]) => (
                                <div key={category} className="text-center">
                                    <div className="text-2xl font-semibold text-gray-900">{count}</div>
                                    <div className="text-sm text-gray-600 capitalize">{category}</div>
                                    <div className="text-xs text-gray-500">
                                        {((count / stats.total_emails) * 100 || 0).toFixed(1)}%
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* LLM Statistics */}
            {llmStats && (
                <div>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">AI Processing</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <StatCard
                            title="Classifications"
                            value={formatNumber(llmStats.total_requests)}
                            change={getPercentageChange(llmStats.total_requests, llmStats.previous_total_requests)}
                            icon="🤖"
                            color="purple"
                        />
                        <StatCard
                            title="Responses Generated"
                            value={formatNumber(llmStats.response_generations)}
                            change={getPercentageChange(llmStats.response_generations, llmStats.previous_response_generations)}
                            icon="💬"
                            color="indigo"
                        />
                        <StatCard
                            title="Sentiment Analysis"
                            value={formatNumber(llmStats.sentiment_analysis)}
                            change={getPercentageChange(llmStats.sentiment_analysis, llmStats.previous_sentiment_analysis)}
                            icon="😊"
                            color="pink"
                        />
                        <StatCard
                            title="AI Cost"
                            value={formatCurrency(llmStats.total_cost)}
                            change={getPercentageChange(llmStats.total_cost, llmStats.previous_total_cost)}
                            icon="💰"
                            color="green"
                            subtitle={`Avg: ${formatCurrency(llmStats.average_cost_per_request)}/req`}
                        />
                    </div>
                </div>
            )}

            {/* SMTP Statistics */}
            {smtpStats && (
                <div>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Email Sending</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <StatCard
                            title="Emails Sent"
                            value={formatNumber(smtpStats.total_sent)}
                            change={getPercentageChange(smtpStats.total_sent, smtpStats.previous_total_sent)}
                            icon="📤"
                            color="blue"
                        />
                        <StatCard
                            title="Replies"
                            value={formatNumber(smtpStats.replies_sent)}
                            change={getPercentageChange(smtpStats.replies_sent, smtpStats.previous_replies_sent)}
                            icon="↩️"
                            color="green"
                        />
                        <StatCard
                            title="Forwards"
                            value={formatNumber(smtpStats.forwards_sent)}
                            change={getPercentageChange(smtpStats.forwards_sent, smtpStats.previous_forwards_sent)}
                            icon="↪️"
                            color="yellow"
                        />
                        <StatCard
                            title="Failed Sends"
                            value={formatNumber(smtpStats.failed_sends)}
                            change={getPercentageChange(smtpStats.failed_sends, smtpStats.previous_failed_sends)}
                            icon="❌"
                            color="red"
                            subtitle={`${((smtpStats.failed_sends / smtpStats.total_sent) * 100 || 0).toFixed(1)}% failure rate`}
                        />
                    </div>
                </div>
            )}

            {/* Response Time Chart */}
            {stats?.response_times && (
                <div>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Response Times</h3>
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="text-center">
                                <div className="text-2xl font-semibold text-gray-900">
                                    {stats.response_times.average || 0}min
                                </div>
                                <div className="text-sm text-gray-600">Average Response Time</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-semibold text-gray-900">
                                    {stats.response_times.fastest || 0}min
                                </div>
                                <div className="text-sm text-gray-600">Fastest Response</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-semibold text-gray-900">
                                    {stats.response_times.slowest || 0}min
                                </div>
                                <div className="text-sm text-gray-600">Slowest Response</div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Recent Activity */}
            {stats?.recent_activity && (
                <div>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div className="divide-y divide-gray-200">
                            {stats.recent_activity.map((activity, index) => (
                                <div key={index} className="p-4">
                                    <div className="flex items-center space-x-3">
                                        <div className="flex-shrink-0">
                                            <span className="text-lg">
                                                {activity.type === 'email_received' ? '📥' :
                                                 activity.type === 'email_sent' ? '📤' :
                                                 activity.type === 'email_processed' ? '⚙️' :
                                                 activity.type === 'rule_triggered' ? '🎯' : '📋'}
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900">
                                                {activity.description}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                {new Date(activity.timestamp).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Refresh Button */}
            <div className="flex justify-center">
                <button
                    onClick={fetchStats}
                    disabled={loading}
                    className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                >
                    {loading ? 'Refreshing...' : '🔄 Refresh Statistics'}
                </button>
            </div>
        </div>
    );
};

export default StatsOverview;