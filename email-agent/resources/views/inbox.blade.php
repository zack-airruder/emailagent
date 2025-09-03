@extends('layouts.app')

@section('title', 'Inbox - Email Agent')
@section('page-title', 'Inbox')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div class="flex items-center gap-4">
        <button onclick="fetchEmails()" class="btn btn-primary" id="fetch-btn">
            <i class="fas fa-sync-alt"></i>
            Fetch New Emails
        </button>
        <button onclick="markAllAsRead()" class="btn btn-secondary" id="mark-read-btn">
            <i class="fas fa-check"></i>
            Mark All as Read
        </button>
    </div>
    <div class="flex items-center gap-2">
        <select id="account-filter" class="form-select" style="width: auto;" onchange="filterEmails()">
            <option value="">All Accounts</option>
        </select>
        <select id="status-filter" class="form-select" style="width: auto;" onchange="filterEmails()">
            <option value="">All Status</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
            <option value="processed">Processed</option>
        </select>
    </div>
</div>

<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3>Email Messages</h3>
        <div class="flex items-center gap-2">
            <span id="email-count" class="text-sm" style="color: #6b7280;">Loading...</span>
            <button onclick="refreshInbox()" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">
                <i class="fas fa-refresh"></i>
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div id="emails-container">
            <div class="text-center" style="padding: 3rem; color: #6b7280;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <div>Loading emails...</div>
            </div>
        </div>
    </div>
</div>

<!-- Email Detail Modal -->
<div id="email-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 800px; max-height: 90%; overflow-y: auto;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modal-subject">Email Details</h3>
            <button onclick="closeEmailModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.5rem;">
            <div id="modal-content"></div>
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; display: flex; gap: 1rem;">
                <button onclick="replyToEmail()" class="btn btn-primary">
                    <i class="fas fa-reply"></i>
                    Reply
                </button>
                <button onclick="escalateEmail()" class="btn btn-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Escalate
                </button>
                <button onclick="markAsProcessed()" class="btn btn-secondary">
                    <i class="fas fa-check"></i>
                    Mark as Processed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div id="reply-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 0.5rem; width: 90%; max-width: 600px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3>Reply to Email</h3>
            <button onclick="closeReplyModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="reply-form" style="padding: 1.5rem;">
            <div class="form-group">
                <label class="form-label">To:</label>
                <input type="email" id="reply-to" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Subject:</label>
                <input type="text" id="reply-subject" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Message:</label>
                <textarea id="reply-message" class="form-textarea" rows="8" placeholder="Type your reply here..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeReplyModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Send Reply
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentEmails = [];
    let selectedEmail = null;
    let accounts = [];

    // Load inbox data
    async function loadInbox() {
        try {
            // Load accounts for filter
            const accountsResponse = await fetch('/api/accounts', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (accountsResponse.ok) {
                accounts = await accountsResponse.json();
                populateAccountFilter();
            }

            // Load emails
            await loadEmails();
        } catch (error) {
            console.error('Error loading inbox:', error);
            showError('Failed to load inbox data');
        }
    }

    async function loadEmails() {
        try {
            const response = await fetch('/api/emails', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                currentEmails = data.data || data;
                displayEmails(currentEmails);
                updateEmailCount(currentEmails.length);
            } else {
                const error = await response.json();
                showError('Failed to load emails: ' + (error.message || 'Unknown error'));
                displayNoEmails('Failed to load emails. Please try again.');
            }
        } catch (error) {
            console.error('Error loading emails:', error);
            showError('Failed to load emails');
            displayNoEmails('Failed to load emails. Please check your connection.');
        }
    }

    function populateAccountFilter() {
        const select = document.getElementById('account-filter');
        select.innerHTML = '<option value="">All Accounts</option>';
        
        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = account.email;
            select.appendChild(option);
        });
    }

    function displayEmails(emails) {
        const container = document.getElementById('emails-container');
        
        if (!emails || emails.length === 0) {
            displayNoEmails('No emails found. Click "Fetch New Emails" to check for new messages.');
            return;
        }

        const emailsHtml = emails.map(email => `
            <div class="email-item" onclick="openEmailModal(${email.id})" style="padding: 1rem; border-bottom: 1px solid #e5e7eb; cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f9fafb'" onmouseout="this.style.backgroundColor='white'">
                <div class="flex justify-between items-start">
                    <div style="flex: 1;">
                        <div class="flex items-center gap-2 mb-2">
                            <div style="font-weight: 600; color: #1f2937;">${email.subject || 'No Subject'}</div>
                            ${email.is_read ? '' : '<span class="badge badge-info">New</span>'}
                            ${email.is_processed ? '<span class="badge badge-success">Processed</span>' : ''}
                        </div>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                            <strong>From:</strong> ${email.sender || 'Unknown'}
                        </div>
                        <div style="font-size: 0.875rem; color: #9ca3af; line-height: 1.4;">
                            ${email.body ? email.body.substring(0, 150) + (email.body.length > 150 ? '...' : '') : 'No content'}
                        </div>
                    </div>
                    <div style="text-align: right; margin-left: 1rem;">
                        <div style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 0.5rem;">
                            ${new Date(email.received_at || email.created_at).toLocaleString()}
                        </div>
                        <div class="flex gap-1">
                            <button onclick="event.stopPropagation(); replyToEmailDirect(${email.id})" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Reply">
                                <i class="fas fa-reply"></i>
                            </button>
                            <button onclick="event.stopPropagation(); escalateEmailDirect(${email.id})" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Escalate">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = emailsHtml;
    }

    function displayNoEmails(message) {
        const container = document.getElementById('emails-container');
        container.innerHTML = `
            <div class="text-center" style="padding: 3rem; color: #6b7280;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <div style="font-size: 1.125rem; margin-bottom: 0.5rem;">${message}</div>
                <a href="{{ route('accounts.create') }}" class="text-blue-600">Add an email account</a> to get started.
            </div>
        `;
    }

    function updateEmailCount(count) {
        document.getElementById('email-count').textContent = `${count} email${count !== 1 ? 's' : ''}`;
    }

    function filterEmails() {
        const accountFilter = document.getElementById('account-filter').value;
        const statusFilter = document.getElementById('status-filter').value;
        
        let filteredEmails = currentEmails;
        
        if (accountFilter) {
            filteredEmails = filteredEmails.filter(email => email.account_id == accountFilter);
        }
        
        if (statusFilter) {
            switch (statusFilter) {
                case 'unread':
                    filteredEmails = filteredEmails.filter(email => !email.is_read);
                    break;
                case 'read':
                    filteredEmails = filteredEmails.filter(email => email.is_read);
                    break;
                case 'processed':
                    filteredEmails = filteredEmails.filter(email => email.is_processed);
                    break;
            }
        }
        
        displayEmails(filteredEmails);
        updateEmailCount(filteredEmails.length);
    }

    async function openEmailModal(emailId) {
        const email = currentEmails.find(e => e.id === emailId);
        if (!email) return;
        
        selectedEmail = email;
        
        document.getElementById('modal-subject').textContent = email.subject || 'No Subject';
        document.getElementById('modal-content').innerHTML = `
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <strong>From:</strong> ${email.sender || 'Unknown'}
                </div>
                <div>
                    <strong>To:</strong> ${email.recipient || 'Unknown'}
                </div>
                <div>
                    <strong>Date:</strong> ${new Date(email.received_at || email.created_at).toLocaleString()}
                </div>
                <div>
                    <strong>Subject:</strong> ${email.subject || 'No Subject'}
                </div>
                <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                    <strong>Message:</strong>
                    <div style="margin-top: 0.5rem; white-space: pre-wrap; line-height: 1.6;">${email.body || 'No content'}</div>
                </div>
            </div>
        `;
        
        document.getElementById('email-modal').classList.remove('hidden');
        
        // Mark as read
        if (!email.is_read) {
            await markEmailAsRead(emailId);
        }
    }

    function closeEmailModal() {
        document.getElementById('email-modal').classList.add('hidden');
        selectedEmail = null;
    }

    function openReplyModal(email) {
        document.getElementById('reply-to').value = email.sender || '';
        document.getElementById('reply-subject').value = 'Re: ' + (email.subject || 'No Subject');
        document.getElementById('reply-message').value = '';
        document.getElementById('reply-modal').classList.remove('hidden');
    }

    function closeReplyModal() {
        document.getElementById('reply-modal').classList.add('hidden');
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
                showSuccess('Emails fetched successfully!');
                await loadEmails();
            } else {
                const error = await response.json();
                showError('Error fetching emails: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error fetching emails: ' + error.message);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async function markEmailAsRead(emailId) {
        try {
            const response = await fetch(`/api/emails/${emailId}/read`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                // Update local email data
                const email = currentEmails.find(e => e.id === emailId);
                if (email) {
                    email.is_read = true;
                }
                filterEmails(); // Refresh display
            }
        } catch (error) {
            console.error('Error marking email as read:', error);
        }
    }

    function refreshInbox() {
        loadEmails();
    }

    function replyToEmail() {
        if (selectedEmail) {
            openReplyModal(selectedEmail);
        }
    }

    function replyToEmailDirect(emailId) {
        const email = currentEmails.find(e => e.id === emailId);
        if (email) {
            openReplyModal(email);
        }
    }

    async function escalateEmail() {
        if (!selectedEmail) return;
        await escalateEmailDirect(selectedEmail.id);
    }

    async function escalateEmailDirect(emailId) {
        try {
            const response = await fetch('/api/escalations', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email_id: emailId,
                    reason: 'Manual escalation from inbox'
                })
            });

            if (response.ok) {
                showSuccess('Email escalated successfully!');
                closeEmailModal();
            } else {
                const error = await response.json();
                showError('Error escalating email: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error escalating email: ' + error.message);
        }
    }

    async function markAsProcessed() {
        if (!selectedEmail) return;
        
        try {
            const response = await fetch(`/api/emails/${selectedEmail.id}/processed`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                showSuccess('Email marked as processed!');
                selectedEmail.is_processed = true;
                closeEmailModal();
                filterEmails();
            } else {
                const error = await response.json();
                showError('Error marking email as processed: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error marking email as processed: ' + error.message);
        }
    }

    // Reply form submission
    document.getElementById('reply-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const to = document.getElementById('reply-to').value;
        const subject = document.getElementById('reply-subject').value;
        const message = document.getElementById('reply-message').value;
        
        if (!message.trim()) {
            showError('Please enter a message');
            return;
        }
        
        try {
            const response = await fetch('/api/smtp/send', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    to: to,
                    subject: subject,
                    body: message
                })
            });

            if (response.ok) {
                showSuccess('Reply sent successfully!');
                closeReplyModal();
            } else {
                const error = await response.json();
                showError('Error sending reply: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error sending reply: ' + error.message);
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

    // Load inbox when page loads
    document.addEventListener('DOMContentLoaded', loadInbox);

    // Auto-refresh every 60 seconds
    setInterval(loadEmails, 60000);
</script>
@endpush