@extends('layouts.app')

@section('title', 'Statistics - Email Agent')
@section('page-title', 'Statistics & Analytics')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <p style="color: #6b7280;">Monitor email processing performance and system analytics.</p>
    </div>
    <div class="flex gap-2">
        <select id="date-range" class="form-select" style="width: auto;" onchange="loadAllData()">
            <option value="7">Last 7 days</option>
            <option value="30" selected>Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="365">Last year</option>
        </select>
        <button onclick="exportData()" class="btn btn-secondary">
            <i class="fas fa-download"></i>
            Export
        </button>
        <button onclick="refreshData()" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i>
            Refresh
        </button>
    </div>
</div>

<!-- Overview Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #3b82f6;" id="total-emails">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Total Emails</div>
            <div style="font-size: 0.75rem; color: #10b981;" id="emails-change">-</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #10b981;" id="processed-emails">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Processed</div>
            <div style="font-size: 0.75rem; color: #10b981;" id="processed-change">-</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;" id="total-escalations">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Escalations</div>
            <div style="font-size: 0.75rem; color: #ef4444;" id="escalations-change">-</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #8b5cf6;" id="active-accounts">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Active Accounts</div>
            <div style="font-size: 0.75rem; color: #10b981;" id="accounts-change">-</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Email Volume Chart -->
    <div class="card">
        <div class="card-header">
            <h3>Email Volume Over Time</h3>
        </div>
        <div class="card-body">
            <canvas id="email-volume-chart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Processing Status Chart -->
    <div class="card">
        <div class="card-header">
            <h3>Processing Status Distribution</h3>
        </div>
        <div class="card-body">
            <canvas id="status-chart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Performance Metrics -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Account Performance -->
    <div class="card">
        <div class="card-header">
            <h3>Account Performance</h3>
        </div>
        <div class="card-body">
            <div id="account-performance">
                <div class="text-center" style="padding: 2rem; color: #6b7280;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem;"></i>
                    <div>Loading account performance...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rule Effectiveness -->
    <div class="card">
        <div class="card-header">
            <h3>Rule Effectiveness</h3>
        </div>
        <div class="card-body">
            <div id="rule-effectiveness">
                <div class="text-center" style="padding: 2rem; color: #6b7280;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem;"></i>
                    <div>Loading rule effectiveness...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SMTP Statistics -->
<div class="card mb-6">
    <div class="card-header">
        <div class="flex justify-between items-center">
            <h3>SMTP Sending Statistics</h3>
            <button onclick="loadSMTPLogs()" class="btn btn-secondary btn-sm">
                <i class="fas fa-list"></i>
                View Logs
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center">
                <div style="font-size: 1.5rem; font-weight: bold; color: #3b82f6;" id="smtp-sent">-</div>
                <div style="color: #6b7280; font-size: 0.875rem;">Emails Sent</div>
            </div>
            <div class="text-center">
                <div style="font-size: 1.5rem; font-weight: bold; color: #ef4444;" id="smtp-failed">-</div>
                <div style="color: #6b7280; font-size: 0.875rem;">Failed</div>
            </div>
            <div class="text-center">
                <div style="font-size: 1.5rem; font-weight: bold; color: #f59e0b;" id="smtp-pending">-</div>
                <div style="color: #6b7280; font-size: 0.875rem;">Pending</div>
            </div>
            <div class="text-center">
                <div style="font-size: 1.5rem; font-weight: bold; color: #10b981;" id="smtp-success-rate">-</div>
                <div style="color: #6b7280; font-size: 0.875rem;">Success Rate</div>
            </div>
        </div>
        
        <div id="smtp-chart-container">
            <canvas id="smtp-chart" width="400" height="100"></canvas>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h3>Recent Activity</h3>
    </div>
    <div class="card-body">
        <div id="recent-activity">
            <div class="text-center" style="padding: 2rem; color: #6b7280;">
                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem;"></i>
                <div>Loading recent activity...</div>
            </div>
        </div>
    </div>
</div>

<!-- SMTP Logs Modal -->
<div id="smtp-logs-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 1000px; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3>SMTP Send Logs</h3>
            <button onclick="closeSMTPLogsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.5rem;">
            <div id="smtp-logs-content">
                <!-- Logs will be populated here -->
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let emailVolumeChart = null;
    let statusChart = null;
    let smtpChart = null;
    
    async function loadAllData() {
        const dateRange = document.getElementById('date-range').value;
        
        await Promise.all([
            loadOverviewStats(dateRange),
            loadEmailVolumeChart(dateRange),
            loadStatusChart(dateRange),
            loadAccountPerformance(dateRange),
            loadRuleEffectiveness(dateRange),
            loadSMTPStats(dateRange),
            loadRecentActivity()
        ]);
    }
    
    async function loadOverviewStats(days = 30) {
        try {
            const response = await fetch(`/api/statistics/overview?days=${days}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const stats = await response.json();
                
                document.getElementById('total-emails').textContent = stats.total_emails || 0;
                document.getElementById('processed-emails').textContent = stats.processed_emails || 0;
                document.getElementById('total-escalations').textContent = stats.total_escalations || 0;
                document.getElementById('active-accounts').textContent = stats.active_accounts || 0;
                
                // Show percentage changes
                document.getElementById('emails-change').textContent = formatChange(stats.emails_change);
                document.getElementById('processed-change').textContent = formatChange(stats.processed_change);
                document.getElementById('escalations-change').textContent = formatChange(stats.escalations_change);
                document.getElementById('accounts-change').textContent = formatChange(stats.accounts_change);
            }
        } catch (error) {
            console.error('Error loading overview stats:', error);
        }
    }
    
    async function loadEmailVolumeChart(days = 30) {
        try {
            const response = await fetch(`/api/statistics/email-volume?days=${days}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                renderEmailVolumeChart(data);
            }
        } catch (error) {
            console.error('Error loading email volume chart:', error);
        }
    }
    
    async function loadStatusChart(days = 30) {
        try {
            const response = await fetch(`/api/statistics/status-distribution?days=${days}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                renderStatusChart(data);
            }
        } catch (error) {
            console.error('Error loading status chart:', error);
        }
    }
    
    async function loadAccountPerformance(days = 30) {
        try {
            const response = await fetch(`/api/statistics/account-performance?days=${days}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                renderAccountPerformance(data);
            }
        } catch (error) {
            console.error('Error loading account performance:', error);
        }
    }
    
    async function loadRuleEffectiveness(days = 30) {
        try {
            const response = await fetch(`/api/statistics/rule-effectiveness?days=${days}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                renderRuleEffectiveness(data);
            }
        } catch (error) {
            console.error('Error loading rule effectiveness:', error);
        }
    }
    
    async function loadSMTPStats(days = 30) {
        try {
            const response = await fetch(`/api/smtp/stats?days=${days}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const stats = await response.json();
                
                document.getElementById('smtp-sent').textContent = stats.sent || 0;
                document.getElementById('smtp-failed').textContent = stats.failed || 0;
                document.getElementById('smtp-pending').textContent = stats.pending || 0;
                
                const successRate = stats.sent > 0 ? ((stats.sent / (stats.sent + stats.failed)) * 100).toFixed(1) + '%' : '0%';
                document.getElementById('smtp-success-rate').textContent = successRate;
                
                if (stats.daily_stats) {
                    renderSMTPChart(stats.daily_stats);
                }
            }
        } catch (error) {
            console.error('Error loading SMTP stats:', error);
        }
    }
    
    async function loadRecentActivity() {
        try {
            const response = await fetch('/api/statistics/recent-activity', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const activities = await response.json();
                renderRecentActivity(activities);
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
        }
    }
    
    function renderEmailVolumeChart(data) {
        const ctx = document.getElementById('email-volume-chart').getContext('2d');
        
        if (emailVolumeChart) {
            emailVolumeChart.destroy();
        }
        
        emailVolumeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Emails Received',
                    data: data.received || [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Emails Processed',
                    data: data.processed || [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function renderStatusChart(data) {
        const ctx = document.getElementById('status-chart').getContext('2d');
        
        if (statusChart) {
            statusChart.destroy();
        }
        
        statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels || ['Processed', 'Pending', 'Failed'],
                datasets: [{
                    data: data.values || [0, 0, 0],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    function renderSMTPChart(data) {
        const ctx = document.getElementById('smtp-chart').getContext('2d');
        
        if (smtpChart) {
            smtpChart.destroy();
        }
        
        smtpChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Sent',
                    data: data.sent || [],
                    backgroundColor: '#10b981'
                }, {
                    label: 'Failed',
                    data: data.failed || [],
                    backgroundColor: '#ef4444'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function renderAccountPerformance(data) {
        const container = document.getElementById('account-performance');
        
        if (!data || data.length === 0) {
            container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No account data available</div>';
            return;
        }
        
        container.innerHTML = data.map(account => `
            <div class="flex justify-between items-center py-3 border-b border-gray-200">
                <div>
                    <div style="font-weight: 500;">${account.email}</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">${account.total_emails} emails</div>
                </div>
                <div class="text-right">
                    <div style="font-weight: 500; color: #10b981;">${account.processed_rate}%</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">processed</div>
                </div>
            </div>
        `).join('');
    }
    
    function renderRuleEffectiveness(data) {
        const container = document.getElementById('rule-effectiveness');
        
        if (!data || data.length === 0) {
            container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No rule data available</div>';
            return;
        }
        
        container.innerHTML = data.map(rule => `
            <div class="flex justify-between items-center py-3 border-b border-gray-200">
                <div>
                    <div style="font-weight: 500;">${rule.name}</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">${rule.matches} matches</div>
                </div>
                <div class="text-right">
                    <div style="font-weight: 500; color: ${rule.is_active ? '#10b981' : '#6b7280'};">
                        ${rule.is_active ? 'Active' : 'Inactive'}
                    </div>
                    <div style="font-size: 0.875rem; color: #6b7280;">${rule.success_rate}% success</div>
                </div>
            </div>
        `).join('');
    }
    
    function renderRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        
        if (!activities || activities.length === 0) {
            container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No recent activity</div>';
            return;
        }
        
        container.innerHTML = activities.map(activity => {
            const iconMap = {
                email_received: 'fa-envelope',
                email_processed: 'fa-check-circle',
                escalation_created: 'fa-exclamation-triangle',
                rule_triggered: 'fa-cogs',
                account_added: 'fa-plus-circle'
            };
            
            const colorMap = {
                email_received: '#3b82f6',
                email_processed: '#10b981',
                escalation_created: '#f59e0b',
                rule_triggered: '#8b5cf6',
                account_added: '#06b6d4'
            };
            
            return `
                <div class="flex items-center py-3 border-b border-gray-200">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: ${colorMap[activity.type] || '#6b7280'}; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                        <i class="fas ${iconMap[activity.type] || 'fa-info'} text-white"></i>
                    </div>
                    <div class="flex-1">
                        <div style="font-weight: 500;">${activity.description}</div>
                        <div style="font-size: 0.875rem; color: #6b7280;">${new Date(activity.created_at).toLocaleString()}</div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    async function loadSMTPLogs() {
        try {
            const response = await fetch('/api/smtp/send-logs', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const logs = await response.json();
                renderSMTPLogs(logs);
                document.getElementById('smtp-logs-modal').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading SMTP logs:', error);
        }
    }
    
    function renderSMTPLogs(logs) {
        const container = document.getElementById('smtp-logs-content');
        
        if (!logs || logs.length === 0) {
            container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No SMTP logs available</div>';
            return;
        }
        
        container.innerHTML = `
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <th style="padding: 0.75rem; text-align: left; font-weight: 500;">Date</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 500;">To</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 500;">Subject</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 500;">Status</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 500;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${logs.map(log => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${new Date(log.created_at).toLocaleString()}</td>
                                <td style="padding: 0.75rem;">${log.to_email}</td>
                                <td style="padding: 0.75rem;">${log.subject || 'No Subject'}</td>
                                <td style="padding: 0.75rem;">
                                    <span style="background: ${log.status === 'sent' ? '#10b981' : log.status === 'failed' ? '#ef4444' : '#f59e0b'}; color: white; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem;">
                                        ${log.status}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem;">
                                    ${log.status === 'failed' ? `<button onclick="retryEmail(${log.id})" class="btn btn-sm btn-secondary">Retry</button>` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    function closeSMTPLogsModal() {
        document.getElementById('smtp-logs-modal').classList.add('hidden');
    }
    
    async function retryEmail(logId) {
        try {
            const response = await fetch(`/api/smtp/retry/${logId}`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                showSuccess('Email retry initiated');
                loadSMTPLogs();
            } else {
                showError('Failed to retry email');
            }
        } catch (error) {
            showError('Error retrying email');
        }
    }
    
    function formatChange(change) {
        if (!change) return '+0%';
        const sign = change > 0 ? '+' : '';
        return `${sign}${change}%`;
    }
    
    function refreshData() {
        loadAllData();
    }
    
    function exportData() {
        const dateRange = document.getElementById('date-range').value;
        window.open(`/api/statistics/export?days=${dateRange}`, '_blank');
    }
    
    function showSuccess(message) {
        alert(message);
    }
    
    function showError(message) {
        alert(message);
    }
    
    // Load data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadAllData();
        
        // Auto-refresh every 5 minutes
        setInterval(() => {
            loadAllData();
        }, 300000);
    });
</script>
@endpush