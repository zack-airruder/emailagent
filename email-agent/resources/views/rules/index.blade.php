@extends('layouts.app')

@section('title', 'Email Rules - Email Agent')
@section('page-title', 'Email Rules')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <p style="color: #6b7280;">Manage automated email processing rules and actions.</p>
    </div>
    <a href="{{ route('rules.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        Add New Rule
    </a>
</div>

<div class="card">
    <div class="card-header">
        <div class="flex justify-between items-center">
            <h3>Email Processing Rules</h3>
            <div class="flex gap-2">
                <select id="account-filter" class="form-select" style="width: auto;" onchange="loadRules()">
                    <option value="">All Accounts</option>
                </select>
                <select id="status-filter" class="form-select" style="width: auto;" onchange="loadRules()">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div id="loading" class="text-center" style="padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 1rem; color: #3b82f6;"></i>
            <div>Loading rules...</div>
        </div>
        
        <div id="rules-container" class="hidden">
            <div id="rules-list"></div>
            
            <div id="no-rules" class="text-center hidden" style="padding: 3rem; color: #6b7280;">
                <i class="fas fa-cogs" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <h3 style="margin-bottom: 0.5rem;">No rules configured</h3>
                <p style="margin-bottom: 1.5rem;">Create your first email processing rule to automate email handling.</p>
                <a href="{{ route('rules.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create First Rule
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Rule Actions Modal -->
<div id="actions-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 500px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3>Rule Actions</h3>
            <button onclick="closeActionsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.5rem;">
            <div id="rule-actions">
                <!-- Actions will be populated here -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 400px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
            <h3>Confirm Delete</h3>
        </div>
        <div style="padding: 1.5rem;">
            <p>Are you sure you want to delete this rule? This action cannot be undone.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Delete Rule
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let rules = [];
    let accounts = [];
    let selectedRuleId = null;
    
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
        
        // Clear existing options except "All Accounts"
        select.innerHTML = '<option value="">All Accounts</option>';
        
        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = account.email;
            select.appendChild(option);
        });
        
        select.value = currentValue;
    }
    
    async function loadRules() {
        const accountFilter = document.getElementById('account-filter').value;
        const statusFilter = document.getElementById('status-filter').value;
        
        document.getElementById('loading').classList.remove('hidden');
        document.getElementById('rules-container').classList.add('hidden');
        
        try {
            let url = '/api/rules';
            const params = new URLSearchParams();
            
            if (accountFilter) params.append('account_id', accountFilter);
            if (statusFilter !== '') params.append('is_active', statusFilter);
            
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
                rules = await response.json();
                displayRules();
            } else {
                showError('Failed to load rules');
            }
        } catch (error) {
            console.error('Error loading rules:', error);
            showError('Error loading rules');
        } finally {
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('rules-container').classList.remove('hidden');
        }
    }
    
    function displayRules() {
        const container = document.getElementById('rules-list');
        const noRules = document.getElementById('no-rules');
        
        if (rules.length === 0) {
            container.innerHTML = '';
            noRules.classList.remove('hidden');
            return;
        }
        
        noRules.classList.add('hidden');
        
        container.innerHTML = rules.map(rule => {
            const account = accounts.find(acc => acc.id === rule.account_id);
            const accountEmail = account ? account.email : 'Unknown Account';
            
            return `
                <div class="border border-gray-200 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h4 style="font-size: 1.125rem; font-weight: 600; margin: 0;">${rule.name}</h4>
                                <div class="flex items-center gap-2">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: ${rule.is_active ? '#10b981' : '#ef4444'};"></div>
                                    <span style="font-size: 0.75rem; color: #6b7280;">${rule.is_active ? 'Active' : 'Inactive'}</span>
                                </div>
                            </div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-envelope"></i>
                                ${accountEmail}
                            </div>
                            ${rule.description ? `<p style="color: #6b7280; font-size: 0.875rem; margin: 0;">${rule.description}</p>` : ''}
                        </div>
                        <div class="flex gap-2">
                            <button onclick="toggleRule(${rule.id}, ${!rule.is_active})" class="btn ${rule.is_active ? 'btn-secondary' : 'btn-primary'}" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                <i class="fas fa-${rule.is_active ? 'pause' : 'play'}"></i>
                                ${rule.is_active ? 'Disable' : 'Enable'}
                            </button>
                            <button onclick="editRule(${rule.id})" class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <button onclick="deleteRule(${rule.id})" class="btn btn-danger" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">Conditions</div>
                            <div class="space-y-1">
                                ${rule.conditions ? JSON.parse(rule.conditions).map(condition => `
                                    <div style="font-size: 0.875rem; color: #374151;">
                                        <span style="font-weight: 500;">${condition.field}</span>
                                        <span style="color: #6b7280;">${condition.operator}</span>
                                        <span style="font-style: italic;">${condition.value}</span>
                                    </div>
                                `).join('') : '<div style="color: #9ca3af; font-size: 0.875rem;">No conditions</div>'}
                            </div>
                        </div>
                        
                        <div>
                            <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">Actions</div>
                            <div class="space-y-1">
                                ${rule.actions ? JSON.parse(rule.actions).map(action => `
                                    <div style="font-size: 0.875rem; color: #374151;">
                                        <span style="font-weight: 500;">${action.type}</span>
                                        ${action.value ? `<span style="color: #6b7280;">: ${action.value}</span>` : ''}
                                    </div>
                                `).join('') : '<div style="color: #9ca3af; font-size: 0.875rem;">No actions</div>'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mt-3 pt-3" style="border-top: 1px solid #e5e7eb; font-size: 0.75rem; color: #9ca3af;">
                        <div>Priority: ${rule.priority || 0}</div>
                        <div>Created: ${new Date(rule.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    async function toggleRule(ruleId, newStatus) {
        try {
            const response = await fetch(`/api/rules/${ruleId}`, {
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    is_active: newStatus
                })
            });
            
            if (response.ok) {
                showSuccess(`Rule ${newStatus ? 'enabled' : 'disabled'} successfully!`);
                loadRules();
            } else {
                const error = await response.json();
                showError('Error updating rule: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error updating rule: ' + error.message);
        }
    }
    
    function editRule(ruleId) {
        window.location.href = `/rules/${ruleId}/edit`;
    }
    
    function deleteRule(ruleId) {
        selectedRuleId = ruleId;
        document.getElementById('delete-modal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        selectedRuleId = null;
        document.getElementById('delete-modal').classList.add('hidden');
    }
    
    async function confirmDelete() {
        if (!selectedRuleId) return;
        
        try {
            const response = await fetch(`/api/rules/${selectedRuleId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                showSuccess('Rule deleted successfully!');
                closeDeleteModal();
                loadRules();
            } else {
                const error = await response.json();
                showError('Error deleting rule: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error deleting rule: ' + error.message);
        }
    }
    
    function closeActionsModal() {
        document.getElementById('actions-modal').classList.add('hidden');
    }
    
    function showSuccess(message) {
        // You can implement a toast notification here
        alert(message);
    }
    
    function showError(message) {
        // You can implement a toast notification here
        alert(message);
    }
    
    // Load data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadAccounts();
        loadRules();
    });
</script>
@endpush