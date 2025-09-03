@extends('layouts.app')

@section('title', 'Dashboard - Email Agent')
@section('page-title', 'Dashboard')

@section('content')
<div class="grid grid-cols-1 grid-cols-4 gap-4 mb-4">
    <!-- Stats Cards -->
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem;">
                <i class="fas fa-envelope"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;" id="total-emails">0</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Total Emails</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;" id="processed-emails">0</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Processed</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; color: #f59e0b; margin-bottom: 0.5rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;" id="escalations">0</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Escalations</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; color: #8b5cf6; margin-bottom: 0.5rem;">
                <i class="fas fa-user-cog"></i>
            </div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1f2937;" id="accounts">0</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Accounts</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 grid-cols-2 gap-4">
    <!-- Recent Emails -->
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h3>Recent Emails</h3>
            <a href="{{ route('inbox') }}" class="btn btn-primary">
                <i class="fas fa-inbox"></i>
                View All
            </a>
        </div>
        <div class="card-body">
            <div id="recent-emails">
                <div class="text-center" style="padding: 2rem; color: #6b7280;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem;"></i>
                    <div>Loading recent emails...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Escalations -->
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h3>Recent Escalations</h3>
            <a href="{{ route('escalations.index') }}" class="btn btn-primary">
                <i class="fas fa-exclamation-triangle"></i>
                View All
            </a>
        </div>
        <div class="card-body">
            <div id="recent-escalations">
                <div class="text-center" style="padding: 2rem; color: #6b7280;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem;"></i>
                    <div>Loading recent escalations...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 mt-4">
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="flex gap-4">
                <a href="{{ route('accounts.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Email Account
                </a>
                <a href="{{ route('rules.create') }}" class="btn btn-secondary">
                    <i class="fas fa-plus"></i>
                    Create Rule
                </a>
                <button onclick="fetchEmails()" class="btn btn-secondary" id="fetch-btn">
                    <i class="fas fa-sync-alt"></i>
                    Fetch Emails
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Load dashboard data
    async function loadDashboardData() {
        try {
            // Load stats
            const statsResponse = await fetch('/api/escalations/stats', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (statsResponse.ok) {
                const stats = await statsResponse.json();
                document.getElementById('escalations').textContent = stats.total || 0;
            }

            // Load recent emails
            const emailsResponse = await fetch('/api/emails?limit=5', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (emailsResponse.ok) {
                const emails = await emailsResponse.json();
                displayRecentEmails(emails.data || emails);
                document.getElementById('total-emails').textContent = emails.total || emails.length || 0;
                document.getElementById('processed-emails').textContent = emails.total || emails.length || 0;
            } else {
                document.getElementById('recent-emails').innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No emails found. <a href="' + '{{ route("accounts.create") }}' + '" class="text-blue-600">Add an email account</a> to get started.</div>';
            }

            // Load recent escalations
            const escalationsResponse = await fetch('/api/escalations?limit=5', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (escalationsResponse.ok) {
                const escalations = await escalationsResponse.json();
                displayRecentEscalations(escalations.data || escalations);
            } else {
                document.getElementById('recent-escalations').innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No escalations found.</div>';
            }

            // Load accounts count
            const accountsResponse = await fetch('/api/accounts', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (accountsResponse.ok) {
                const accounts = await accountsResponse.json();
                document.getElementById('accounts').textContent = accounts.length || 0;
            }

        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    function displayRecentEmails(emails) {
        const container = document.getElementById('recent-emails');
        
        if (!emails || emails.length === 0) {
            container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No emails found.</div>';
            return;
        }

        const emailsHtml = emails.slice(0, 5).map(email => `
            <div style="padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb; last-child:border-bottom: none;">
                <div style="font-weight: 500; color: #1f2937; margin-bottom: 0.25rem;">${email.subject || 'No Subject'}</div>
                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">From: ${email.sender || 'Unknown'}</div>
                <div style="font-size: 0.75rem; color: #9ca3af;">${new Date(email.received_at || email.created_at).toLocaleString()}</div>
            </div>
        `).join('');

        container.innerHTML = emailsHtml;
    }

    function displayRecentEscalations(escalations) {
        const container = document.getElementById('recent-escalations');
        
        if (!escalations || escalations.length === 0) {
            container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #6b7280;">No escalations found.</div>';
            return;
        }

        const escalationsHtml = escalations.slice(0, 5).map(escalation => `
            <div style="padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb; last-child:border-bottom: none;">
                <div class="flex justify-between items-center">
                    <div>
                        <div style="font-weight: 500; color: #1f2937; margin-bottom: 0.25rem;">${escalation.subject || 'No Subject'}</div>
                        <div style="font-size: 0.875rem; color: #6b7280;">${escalation.reason || 'No reason provided'}</div>
                    </div>
                    <span class="badge badge-${escalation.status === 'resolved' ? 'success' : escalation.status === 'assigned' ? 'info' : 'warning'}">
                        ${escalation.status || 'pending'}
                    </span>
                </div>
            </div>
        `).join('');

        container.innerHTML = escalationsHtml;
    }

    async function fetchEmails() {
        const btn = document.getElementById('fetch-btn');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
        btn.disabled = true;

        try {
            const response = await fetch('/api/emails/fetch', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                alert('Emails fetched successfully!');
                loadDashboardData(); // Reload dashboard data
            } else {
                const error = await response.json();
                alert('Error fetching emails: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error fetching emails: ' + error.message);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // Load data when page loads
    document.addEventListener('DOMContentLoaded', loadDashboardData);

    // Auto-refresh every 30 seconds
    setInterval(loadDashboardData, 30000);
</script>
@endpush