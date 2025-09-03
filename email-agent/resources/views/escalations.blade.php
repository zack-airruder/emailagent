@extends('layouts.app')

@section('title', 'Escalations - Email Agent')
@section('page-title', 'Escalation Queue')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <p style="color: #6b7280;">Manage escalated emails and support tickets that require attention.</p>
    </div>
    <div class="flex gap-2">
        <button onclick="refreshEscalations()" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i>
            Refresh
        </button>
        <button onclick="markAllAsRead()" class="btn btn-secondary">
            <i class="fas fa-check-double"></i>
            Mark All Read
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #ef4444;" id="total-escalations">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Total Escalations</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;" id="pending-escalations">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Pending</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #3b82f6;" id="assigned-escalations">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Assigned</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 2rem; font-weight: bold; color: #10b981;" id="resolved-escalations">-</div>
            <div style="color: #6b7280; font-size: 0.875rem;">Resolved</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-6">
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="status-filter" class="form-select" onchange="loadEscalations()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select id="priority-filter" class="form-select" onchange="loadEscalations()">
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Account</label>
                <select id="account-filter" class="form-select" onchange="loadEscalations()">
                    <option value="">All Accounts</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned To</label>
                <select id="assignee-filter" class="form-select" onchange="loadEscalations()">
                    <option value="">All Users</option>
                    <option value="me">Assigned to Me</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Search</label>
                <input type="text" id="search-input" class="form-input" placeholder="Search escalations..." onkeyup="debounceSearch()">
            </div>
        </div>
    </div>
</div>

<!-- Escalations List -->
<div class="card">
    <div class="card-header">
        <div class="flex justify-between items-center">
            <h3>Escalations</h3>
            <div class="flex gap-2">
                <select id="sort-by" class="form-select" style="width: auto;" onchange="loadEscalations()">
                    <option value="created_at">Sort by Date</option>
                    <option value="priority">Sort by Priority</option>
                    <option value="status">Sort by Status</option>
                </select>
                <select id="sort-order" class="form-select" style="width: auto;" onchange="loadEscalations()">
                    <option value="desc">Newest First</option>
                    <option value="asc">Oldest First</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div id="loading" class="text-center" style="padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem; color: #3b82f6;"></i>
            <div>Loading escalations...</div>
        </div>
        
        <div id="escalations-container" class="hidden">
            <div id="escalations-list"></div>
            
            <div id="no-escalations" class="text-center hidden" style="padding: 3rem; color: #6b7280;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <h3 style="margin-bottom: 0.5rem;">No escalations found</h3>
                <p style="margin-bottom: 1.5rem;">There are no escalations matching your current filters.</p>
            </div>
        </div>
    </div>
</div>

<!-- Escalation Detail Modal -->
<div id="detail-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3>Escalation Details</h3>
            <button onclick="closeDetailModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.5rem;">
            <div id="escalation-details">
                <!-- Details will be populated here -->
            </div>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div id="assign-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 400px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
            <h3>Assign Escalation</h3>
        </div>
        <div style="padding: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Assign to User</label>
                <select id="assign-user" class="form-select">
                    <option value="">Select user</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Notes (Optional)</label>
                <textarea id="assign-notes" class="form-input" rows="3" placeholder="Add assignment notes..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button onclick="closeAssignModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmAssign()" class="btn btn-primary">
                    <i class="fas fa-user-check"></i>
                    Assign
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let escalations = [];
    let accounts = [];
    let users = [];
    let selectedEscalationId = null;
    let searchTimeout = null;
    
    async function loadStats() {
        try {
            const response = await fetch('/api/escalations/stats', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const stats = await response.json();
                document.getElementById('total-escalations').textContent = stats.total || 0;
                document.getElementById('pending-escalations').textContent = stats.pending || 0;
                document.getElementById('assigned-escalations').textContent = stats.assigned || 0;
                document.getElementById('resolved-escalations').textContent = stats.resolved || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    async function loadAccounts() {
        try {
            const response = await fetch('/api/accounts', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                accounts = await response.json();
                populateAccountFilter();
            }
        } catch (error) {
            console.error('Error loading accounts:', error);
        }
    }
    
    function populateAccountFilter() {
        const select = document.getElementById('account-filter');
        const currentValue = select.value;
        
        select.innerHTML = '<option value="">All Accounts</option>';
        
        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = account.email;
            select.appendChild(option);
        });
        
        select.value = currentValue;
    }
    
    async function loadEscalations() {
        const statusFilter = document.getElementById('status-filter').value;
        const priorityFilter = document.getElementById('priority-filter').value;
        const accountFilter = document.getElementById('account-filter').value;
        const assigneeFilter = document.getElementById('assignee-filter').value;
        const searchInput = document.getElementById('search-input').value;
        const sortBy = document.getElementById('sort-by').value;
        const sortOrder = document.getElementById('sort-order').value;
        
        document.getElementById('loading').classList.remove('hidden');
        document.getElementById('escalations-container').classList.add('hidden');
        
        try {
            let url = '/api/escalations';
            const params = new URLSearchParams();
            
            if (statusFilter) params.append('status', statusFilter);
            if (priorityFilter) params.append('priority', priorityFilter);
            if (accountFilter) params.append('account_id', accountFilter);
            if (assigneeFilter) {
                if (assigneeFilter === 'me') {
                    params.append('assigned_to_me', '1');
                } else if (assigneeFilter === 'unassigned') {
                    params.append('unassigned', '1');
                }
            }
            if (searchInput) params.append('search', searchInput);
            if (sortBy) params.append('sort_by', sortBy);
            if (sortOrder) params.append('sort_order', sortOrder);
            
            if (params.toString()) {
                url += '?' + params.toString();
            }
            
            const response = await fetch(url, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                escalations = await response.json();
                displayEscalations();
            } else {
                showError('Failed to load escalations');
            }
        } catch (error) {
            console.error('Error loading escalations:', error);
            showError('Error loading escalations');
        } finally {
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('escalations-container').classList.remove('hidden');
        }
    }
    
    function displayEscalations() {
        const container = document.getElementById('escalations-list');
        const noEscalations = document.getElementById('no-escalations');
        
        if (escalations.length === 0) {
            container.innerHTML = '';
            noEscalations.classList.remove('hidden');
            return;
        }
        
        noEscalations.classList.add('hidden');
        
        container.innerHTML = escalations.map(escalation => {
            const account = accounts.find(acc => acc.id === escalation.account_id);
            const accountEmail = account ? account.email : 'Unknown Account';
            
            const priorityColors = {
                low: '#10b981',
                medium: '#f59e0b',
                high: '#ef4444',
                urgent: '#dc2626'
            };
            
            const statusColors = {
                pending: '#f59e0b',
                assigned: '#3b82f6',
                resolved: '#10b981'
            };
            
            return `
                <div class="border border-gray-200 rounded-lg p-4 mb-4 hover:shadow-md transition-shadow cursor-pointer" onclick="viewEscalation(${escalation.id})">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h4 style="font-size: 1.125rem; font-weight: 600; margin: 0;">${escalation.subject || 'No Subject'}</h4>
                                <div class="flex items-center gap-2">
                                    <span style="background: ${priorityColors[escalation.priority] || '#6b7280'}; color: white; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-transform: uppercase;">
                                        ${escalation.priority || 'medium'}
                                    </span>
                                    <span style="background: ${statusColors[escalation.status] || '#6b7280'}; color: white; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; text-transform: uppercase;">
                                        ${escalation.status || 'pending'}
                                    </span>
                                </div>
                            </div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-envelope"></i>
                                ${accountEmail} • ${escalation.from_email || 'Unknown Sender'}
                            </div>
                            ${escalation.reason ? `<p style="color: #6b7280; font-size: 0.875rem; margin: 0;">${escalation.reason}</p>` : ''}
                        </div>
                        <div class="flex gap-2" onclick="event.stopPropagation();">
                            ${escalation.status !== 'resolved' ? `
                                <button onclick="assignEscalation(${escalation.id})" class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                    <i class="fas fa-user-plus"></i>
                                    Assign
                                </button>
                                <button onclick="resolveEscalation(${escalation.id})" class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                    <i class="fas fa-check"></i>
                                    Resolve
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">Assigned To</div>
                            <div style="font-size: 0.875rem;">${escalation.assigned_to_name || 'Unassigned'}</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">Created</div>
                            <div style="font-size: 0.875rem;">${new Date(escalation.created_at).toLocaleString()}</div>
                        </div>
                        
                        <div>
                            <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">Last Updated</div>
                            <div style="font-size: 0.875rem;">${new Date(escalation.updated_at).toLocaleString()}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    async function viewEscalation(escalationId) {
        try {
            const response = await fetch(`/api/escalations/${escalationId}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const escalation = await response.json();
                displayEscalationDetails(escalation);
                document.getElementById('detail-modal').classList.remove('hidden');
            } else {
                showError('Failed to load escalation details');
            }
        } catch (error) {
            showError('Error loading escalation details');
        }
    }
    
    function displayEscalationDetails(escalation) {
        const container = document.getElementById('escalation-details');
        const account = accounts.find(acc => acc.id === escalation.account_id);
        
        container.innerHTML = `
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Escalation Information</h4>
                        <div class="space-y-3">
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">Subject</div>
                                <div style="font-weight: 500;">${escalation.subject || 'No Subject'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">Status</div>
                                <div>${escalation.status || 'pending'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">Priority</div>
                                <div>${escalation.priority || 'medium'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">Account</div>
                                <div>${account ? account.email : 'Unknown Account'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Email Information</h4>
                        <div class="space-y-3">
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">From</div>
                                <div>${escalation.from_email || 'Unknown'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">To</div>
                                <div>${escalation.to_email || 'Unknown'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">Assigned To</div>
                                <div>${escalation.assigned_to_name || 'Unassigned'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500;">Created</div>
                                <div>${new Date(escalation.created_at).toLocaleString()}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${escalation.reason ? `
                    <div>
                        <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Escalation Reason</h4>
                        <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
                            ${escalation.reason}
                        </div>
                    </div>
                ` : ''}
                
                ${escalation.email_body ? `
                    <div>
                        <h4 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Email Content</h4>
                        <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; max-height: 300px; overflow-y: auto;">
                            ${escalation.email_body}
                        </div>
                    </div>
                ` : ''}
                
                <div class="flex gap-2">
                    ${escalation.status !== 'resolved' ? `
                        <button onclick="assignEscalation(${escalation.id}); closeDetailModal();" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i>
                            Assign
                        </button>
                        <button onclick="resolveEscalation(${escalation.id}); closeDetailModal();" class="btn btn-primary">
                            <i class="fas fa-check"></i>
                            Resolve
                        </button>
                    ` : ''}
                    <button onclick="closeDetailModal()" class="btn btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        `;
    }
    
    function closeDetailModal() {
        document.getElementById('detail-modal').classList.add('hidden');
    }
    
    function assignEscalation(escalationId) {
        selectedEscalationId = escalationId;
        document.getElementById('assign-modal').classList.remove('hidden');
    }
    
    function closeAssignModal() {
        selectedEscalationId = null;
        document.getElementById('assign-modal').classList.add('hidden');
        document.getElementById('assign-user').value = '';
        document.getElementById('assign-notes').value = '';
    }
    
    async function confirmAssign() {
        const userId = document.getElementById('assign-user').value;
        const notes = document.getElementById('assign-notes').value;
        
        if (!userId) {
            showError('Please select a user to assign to.');
            return;
        }
        
        try {
            const response = await fetch(`/api/escalations/${selectedEscalationId}/assign`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    assigned_to: userId,
                    notes: notes
                })
            });
            
            if (response.ok) {
                showSuccess('Escalation assigned successfully!');
                closeAssignModal();
                loadEscalations();
                loadStats();
            } else {
                const error = await response.json();
                showError('Error assigning escalation: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error assigning escalation: ' + error.message);
        }
    }
    
    async function resolveEscalation(escalationId) {
        if (!confirm('Are you sure you want to resolve this escalation?')) {
            return;
        }
        
        try {
            const response = await fetch(`/api/escalations/${escalationId}/resolve`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                showSuccess('Escalation resolved successfully!');
                loadEscalations();
                loadStats();
            } else {
                const error = await response.json();
                showError('Error resolving escalation: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error resolving escalation: ' + error.message);
        }
    }
    
    async function markAllAsRead() {
        if (!confirm('Mark all escalations as read?')) {
            return;
        }
        
        try {
            const response = await fetch('/api/escalations/mark-all-read', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                showSuccess('All escalations marked as read!');
                loadEscalations();
            } else {
                showError('Error marking escalations as read');
            }
        } catch (error) {
            showError('Error marking escalations as read');
        }
    }
    
    function refreshEscalations() {
        loadEscalations();
        loadStats();
    }
    
    function debounceSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadEscalations();
        }, 500);
    }
    
    function showSuccess(message) {
        alert(message);
    }
    
    function showError(message) {
        alert(message);
    }
    
    // Load data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadStats();
        loadAccounts();
        loadEscalations();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            loadStats();
            loadEscalations();
        }, 30000);
    });
</script>
@endpush