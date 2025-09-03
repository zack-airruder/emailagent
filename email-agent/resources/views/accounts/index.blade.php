@extends('layouts.app')

@section('title', 'Email Accounts - Email Agent')
@section('page-title', 'Email Accounts')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <p style="color: #6b7280;">Manage your email accounts and connection settings.</p>
    </div>
    <a href="{{ route('accounts.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        Add Email Account
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Connected Accounts</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div id="accounts-container">
            <div class="text-center" style="padding: 3rem; color: #6b7280;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <div>Loading accounts...</div>
            </div>
        </div>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="test-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 500px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3>Test Connection</h3>
            <button onclick="closeTestModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.5rem;">
            <div id="test-results">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; color: #3b82f6;"></i>
                    <div>Testing connection...</div>
                </div>
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
            <p>Are you sure you want to delete this email account? This action cannot be undone.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let accounts = [];
    let accountToDelete = null;

    // Load accounts
    async function loadAccounts() {
        try {
            const response = await fetch('/api/accounts', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const result = await response.json();
                accounts = result.data || result;
                displayAccounts(accounts);
            } else if (response.status === 401) {
                // Unauthorized - redirect to login
                window.location.href = '/login';
            } else {
                const error = await response.json().catch(() => ({ message: 'Unknown error' }));
                console.error('API Error:', error);
                displayNoAccounts('Failed to load accounts. Please try again.');
            }
        } catch (error) {
            console.error('Error loading accounts:', error);
            displayNoAccounts('Failed to load accounts. Please check your connection.');
        }
    }

    function displayAccounts(accounts) {
        const container = document.getElementById('accounts-container');
        
        if (!accounts || accounts.length === 0) {
            displayNoAccounts('No email accounts configured. Add your first account to get started.');
            return;
        }

        const accountsHtml = accounts.map(account => `
            <div class="account-item" style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                <div class="flex justify-between items-start">
                    <div style="flex: 1;">
                        <div class="flex items-center gap-3 mb-3">
                            <div style="width: 40px; height: 40px; background: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                ${account.email ? account.email.charAt(0).toUpperCase() : 'A'}
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #1f2937; font-size: 1.125rem;">${account.email || 'Unknown Email'}</div>
                                <div style="font-size: 0.875rem; color: #6b7280;">${account.provider || 'Unknown Provider'}</div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">IMAP Server</div>
                                <div style="font-size: 0.875rem; color: #374151;">${account.imap_host || 'Not configured'}:${account.imap_port || 'N/A'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; font-weight: 500; margin-bottom: 0.25rem;">SMTP Server</div>
                                <div style="font-size: 0.875rem; color: #374151;">${account.smtp_host || 'Not configured'}:${account.smtp_port || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: ${account.is_active ? '#10b981' : '#ef4444'};"></div>
                                <span style="font-size: 0.875rem; color: #6b7280;">${account.is_active ? 'Active' : 'Inactive'}</span>
                            </div>
                            <div style="font-size: 0.875rem; color: #6b7280;">
                                Added ${new Date(account.created_at).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button onclick="testConnection(${account.id})" class="btn btn-secondary" style="padding: 0.5rem;" title="Test Connection">
                            <i class="fas fa-plug"></i>
                        </button>
                        <a href="/accounts/${account.id}/edit" class="btn btn-primary" style="padding: 0.5rem;" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="deleteAccount(${account.id})" class="btn btn-danger" style="padding: 0.5rem;" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = accountsHtml;
    }

    function displayNoAccounts(message) {
        const container = document.getElementById('accounts-container');
        container.innerHTML = `
            <div class="text-center" style="padding: 3rem; color: #6b7280;">
                <i class="fas fa-user-cog" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <div style="font-size: 1.125rem; margin-bottom: 1rem;">${message}</div>
                <a href="{{ route('accounts.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Your First Account
                </a>
            </div>
        `;
    }

    async function testConnection(accountId) {
        const account = accounts.find(a => a.id === accountId);
        if (!account) return;
        
        document.getElementById('test-modal').classList.remove('hidden');
        document.getElementById('test-results').innerHTML = `
            <div class="text-center">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; color: #3b82f6;"></i>
                <div>Testing connection to ${account.email}...</div>
            </div>
        `;
        
        try {
            const response = await fetch(`/api/accounts/${accountId}/test-connection`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            
            if (response.status === 401) {
                // Unauthorized - redirect to login
                window.location.href = '/login';
                return;
            }
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                document.getElementById('test-results').innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem; color: #10b981;"></i>
                        <div style="color: #10b981; font-weight: 600; margin-bottom: 0.5rem;">Connection Successful!</div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Both IMAP and SMTP connections are working properly.</div>
                    </div>
                `;
            } else {
                document.getElementById('test-results').innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem; color: #ef4444;"></i>
                        <div style="color: #ef4444; font-weight: 600; margin-bottom: 0.5rem;">Connection Failed</div>
                        <div style="color: #6b7280; font-size: 0.875rem;">${result.message || 'Unable to connect to the email server. Please check your settings.'}</div>
                    </div>
                `;
            }
        } catch (error) {
            document.getElementById('test-results').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem; color: #ef4444;"></i>
                    <div style="color: #ef4444; font-weight: 600; margin-bottom: 0.5rem;">Connection Error</div>
                    <div style="color: #6b7280; font-size: 0.875rem;">Failed to test connection: ${error.message}</div>
                </div>
            `;
        }
    }

    function closeTestModal() {
        document.getElementById('test-modal').classList.add('hidden');
    }

    function deleteAccount(accountId) {
        accountToDelete = accountId;
        document.getElementById('delete-modal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('delete-modal').classList.add('hidden');
        accountToDelete = null;
    }

    async function confirmDelete() {
        if (!accountToDelete) return;
        
        try {
            const response = await fetch(`/api/accounts/${accountToDelete}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                console.log('Account deleted successfully');
                await loadAccounts(); // Reload accounts
                closeDeleteModal();
            } else if (response.status === 401) {
                // Unauthorized - redirect to login
                window.location.href = '/login';
            } else {
                const error = await response.json().catch(() => ({ message: 'Unknown error' }));
                console.error('Error deleting account:', error);
                closeDeleteModal();
            }
        } catch (error) {
            console.error('Error deleting account:', error);
            closeDeleteModal();
        }
    }

    function showError(message) {
        // Log errors to console instead of showing alert
        console.error('Error:', message);
    }

    // Load accounts when page loads
    document.addEventListener('DOMContentLoaded', loadAccounts);
</script>
@endpush