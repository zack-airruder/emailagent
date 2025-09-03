@extends('layouts.app')

@section('title', 'Add Email Account - Email Agent')
@section('page-title', 'Add Email Account')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <p style="color: #6b7280;">Connect a new email account to start processing emails automatically.</p>
    </div>
    <a href="{{ route('accounts.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Back to Accounts
    </a>
</div>

<div class="grid grid-cols-1 grid-cols-2 gap-6">
    <!-- Account Form -->
    <div class="card">
        <div class="card-header">
            <h3>Account Details</h3>
        </div>
        <div class="card-body">
            <form id="account-form">
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" id="email" class="form-input" required placeholder="your.email@example.com">
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">The email address you want to monitor</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" id="password" class="form-input" required placeholder="Your email password or app password">
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Use an app-specific password for better security</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Provider</label>
                    <select id="provider" class="form-select" onchange="updateServerSettings()">
                        <option value="">Select a provider or configure manually</option>
                        <option value="gmail">Gmail</option>
                        <option value="outlook">Outlook/Hotmail</option>
                        <option value="yahoo">Yahoo Mail</option>
                        <option value="icloud">iCloud Mail</option>
                        <option value="custom">Custom Configuration</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">IMAP Host *</label>
                        <input type="text" id="imap_host" class="form-input" required placeholder="imap.example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">IMAP Port *</label>
                        <input type="number" id="imap_port" class="form-input" required placeholder="993">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">SMTP Host *</label>
                        <input type="text" id="smtp_host" class="form-input" required placeholder="smtp.example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Port *</label>
                        <input type="number" id="smtp_port" class="form-input" required placeholder="587">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="flex items-center">
                            <input type="checkbox" id="imap_encryption" checked style="margin-right: 0.5rem;">
                            <span class="form-label" style="margin-bottom: 0;">IMAP SSL/TLS</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="flex items-center">
                            <input type="checkbox" id="smtp_encryption" checked style="margin-right: 0.5rem;">
                            <span class="form-label" style="margin-bottom: 0;">SMTP SSL/TLS</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="flex items-center">
                        <input type="checkbox" id="is_active" checked style="margin-right: 0.5rem;">
                        <span class="form-label" style="margin-bottom: 0;">Active</span>
                    </label>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Enable this account for email processing</div>
                </div>

                <div class="flex gap-2">
                    <button type="button" onclick="testConnection()" class="btn btn-secondary" id="test-btn">
                        <i class="fas fa-plug"></i>
                        Test Connection
                    </button>
                    <button type="submit" class="btn btn-primary" id="save-btn">
                        <i class="fas fa-save"></i>
                        Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Help & Tips -->
    <div class="card">
        <div class="card-header">
            <h3>Setup Help</h3>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <h4 style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">Common Providers</h4>
                <div style="font-size: 0.875rem; color: #6b7280; line-height: 1.6;">
                    Select your email provider from the dropdown to automatically configure server settings.
                </div>
            </div>

            <div class="mb-4">
                <h4 style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">App Passwords</h4>
                <div style="font-size: 0.875rem; color: #6b7280; line-height: 1.6;">
                    For Gmail and other providers with 2FA enabled, you'll need to generate an app-specific password instead of using your regular password.
                </div>
            </div>

            <div class="mb-4">
                <h4 style="font-size: 1rem; font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">Security</h4>
                <div style="font-size: 0.875rem; color: #6b7280; line-height: 1.6;">
                    Always use SSL/TLS encryption for both IMAP and SMTP connections to ensure your emails are transmitted securely.
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Tip:</strong> Test your connection before saving to ensure all settings are correct.
            </div>
        </div>
    </div>
</div>

<!-- Test Results Modal -->
<div id="test-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 500px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3>Connection Test Results</h3>
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
@endsection

@push('scripts')
<script>
    const providerSettings = {
        gmail: {
            imap_host: 'imap.gmail.com',
            imap_port: 993,
            smtp_host: 'smtp.gmail.com',
            smtp_port: 587,
            imap_encryption: true,
            smtp_encryption: true
        },
        outlook: {
            imap_host: 'outlook.office365.com',
            imap_port: 993,
            smtp_host: 'smtp-mail.outlook.com',
            smtp_port: 587,
            imap_encryption: true,
            smtp_encryption: true
        },
        yahoo: {
            imap_host: 'imap.mail.yahoo.com',
            imap_port: 993,
            smtp_host: 'smtp.mail.yahoo.com',
            smtp_port: 587,
            imap_encryption: true,
            smtp_encryption: true
        },
        icloud: {
            imap_host: 'imap.mail.me.com',
            imap_port: 993,
            smtp_host: 'smtp.mail.me.com',
            smtp_port: 587,
            imap_encryption: true,
            smtp_encryption: true
        }
    };

    function updateServerSettings() {
        const provider = document.getElementById('provider').value;
        
        if (provider && providerSettings[provider]) {
            const settings = providerSettings[provider];
            
            document.getElementById('imap_host').value = settings.imap_host;
            document.getElementById('imap_port').value = settings.imap_port;
            document.getElementById('smtp_host').value = settings.smtp_host;
            document.getElementById('smtp_port').value = settings.smtp_port;
            document.getElementById('imap_encryption').checked = settings.imap_encryption;
            document.getElementById('smtp_encryption').checked = settings.smtp_encryption;
        }
    }

    async function testConnection() {
        const formData = getFormData();
        
        if (!formData.email || !formData.password || !formData.imap_host || !formData.smtp_host) {
            showError('Please fill in all required fields before testing.');
            return;
        }
        
        document.getElementById('test-modal').classList.remove('hidden');
        document.getElementById('test-results').innerHTML = `
            <div class="text-center">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem; color: #3b82f6;"></i>
                <div>Testing connection to ${formData.email}...</div>
            </div>
        `;
        
        try {
            const response = await fetch('/api/smtp/test-connection', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                document.getElementById('test-results').innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem; color: #10b981;"></i>
                        <div style="color: #10b981; font-weight: 600; margin-bottom: 0.5rem;">Connection Successful!</div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Both IMAP and SMTP connections are working properly. You can now save this account.</div>
                    </div>
                `;
            } else {
                document.getElementById('test-results').innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem; color: #ef4444;"></i>
                        <div style="color: #ef4444; font-weight: 600; margin-bottom: 0.5rem;">Connection Failed</div>
                        <div style="color: #6b7280; font-size: 0.875rem;">${result.message || 'Unable to connect to the email server. Please check your settings and try again.'}</div>
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

    function getFormData() {
        return {
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            provider: document.getElementById('provider').value || null,
            imap_host: document.getElementById('imap_host').value,
            imap_port: parseInt(document.getElementById('imap_port').value),
            smtp_host: document.getElementById('smtp_host').value,
            smtp_port: parseInt(document.getElementById('smtp_port').value),
            imap_encryption: document.getElementById('imap_encryption').checked,
            smtp_encryption: document.getElementById('smtp_encryption').checked,
            is_active: document.getElementById('is_active').checked
        };
    }

    // Form submission
    document.getElementById('account-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = getFormData();
        const saveBtn = document.getElementById('save-btn');
        const originalText = saveBtn.innerHTML;
        
        // Validate required fields
        if (!formData.email || !formData.password || !formData.imap_host || !formData.smtp_host || !formData.imap_port || !formData.smtp_port) {
            showError('Please fill in all required fields.');
            return;
        }
        
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveBtn.disabled = true;
        
        try {
            const response = await fetch('/api/accounts', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            if (response.ok) {
                showSuccess('Account saved successfully!');
                setTimeout(() => {
                    window.location.href = '{{ route("accounts.index") }}';
                }, 1500);
            } else {
                const error = await response.json();
                showError('Error saving account: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error saving account: ' + error.message);
        } finally {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    });

    function showSuccess(message) {
        // You can implement a toast notification here
        alert(message);
    }

    function showError(message) {
        // You can implement a toast notification here
        alert(message);
    }

    // Set default values
    document.addEventListener('DOMContentLoaded', function() {
        // Set some sensible defaults
        document.getElementById('imap_port').value = 993;
        document.getElementById('smtp_port').value = 587;
    });
</script>
@endpush