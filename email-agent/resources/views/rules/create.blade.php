@extends('layouts.app')

@section('title', 'Create Rule - Email Agent')
@section('page-title', 'Create Email Rule')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <p style="color: #6b7280;">Create automated rules to process incoming emails based on conditions and actions.</p>
    </div>
    <a href="{{ route('rules.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Back to Rules
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Rule Form -->
    <div class="lg:col-span-2">
        <form id="rule-form">
            <!-- Basic Information -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Basic Information</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Rule Name *</label>
                        <input type="text" id="name" class="form-input" required placeholder="e.g., Auto-reply for support emails">
                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Give your rule a descriptive name</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="description" class="form-input" rows="3" placeholder="Optional description of what this rule does"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Email Account *</label>
                            <select id="account_id" class="form-select" required>
                                <option value="">Select an email account</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select id="priority" class="form-select">
                                <option value="0">Normal (0)</option>
                                <option value="1">Low (1)</option>
                                <option value="5">Medium (5)</option>
                                <option value="10">High (10)</option>
                            </select>
                            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Higher numbers = higher priority</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="flex items-center">
                            <input type="checkbox" id="is_active" style="margin-right: 0.5rem;" checked>
                            <span class="form-label" style="margin-bottom: 0;">Active</span>
                        </label>
                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Enable this rule to process emails</div>
                    </div>
                </div>
            </div>

            <!-- Conditions -->
            <div class="card mb-6">
                <div class="card-header">
                    <div class="flex justify-between items-center">
                        <h3>Conditions</h3>
                        <button type="button" onclick="addCondition()" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i>
                            Add Condition
                        </button>
                    </div>
                    <p style="color: #6b7280; font-size: 0.875rem; margin: 0.5rem 0 0 0;">Define when this rule should be triggered</p>
                </div>
                <div class="card-body">
                    <div id="conditions-container">
                        <div class="text-center" style="padding: 2rem; color: #6b7280;">
                            <i class="fas fa-filter" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <div>No conditions added yet</div>
                            <div style="font-size: 0.875rem;">Click "Add Condition" to get started</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mb-6">
                <div class="card-header">
                    <div class="flex justify-between items-center">
                        <h3>Actions</h3>
                        <button type="button" onclick="addAction()" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i>
                            Add Action
                        </button>
                    </div>
                    <p style="color: #6b7280; font-size: 0.875rem; margin: 0.5rem 0 0 0;">Define what should happen when conditions are met</p>
                </div>
                <div class="card-body">
                    <div id="actions-container">
                        <div class="text-center" style="padding: 2rem; color: #6b7280;">
                            <i class="fas fa-cogs" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <div>No actions added yet</div>
                            <div style="font-size: 0.875rem;">Click "Add Action" to get started</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary" id="save-btn">
                    <i class="fas fa-save"></i>
                    Create Rule
                </button>
                <button type="button" onclick="testRule()" class="btn btn-secondary" id="test-btn">
                    <i class="fas fa-flask"></i>
                    Test Rule
                </button>
            </div>
        </form>
    </div>

    <!-- Help Panel -->
    <div class="lg:col-span-1">
        <div class="card">
            <div class="card-header">
                <h3>Rule Help</h3>
            </div>
            <div class="card-body">
                <div class="space-y-4">
                    <div>
                        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Condition Fields</h4>
                        <ul style="font-size: 0.75rem; color: #6b7280; margin: 0; padding-left: 1rem;">
                            <li><strong>from:</strong> Sender email address</li>
                            <li><strong>to:</strong> Recipient email address</li>
                            <li><strong>subject:</strong> Email subject line</li>
                            <li><strong>body:</strong> Email content/body</li>
                            <li><strong>has_attachment:</strong> true/false</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Operators</h4>
                        <ul style="font-size: 0.75rem; color: #6b7280; margin: 0; padding-left: 1rem;">
                            <li><strong>contains:</strong> Text contains value</li>
                            <li><strong>equals:</strong> Exact match</li>
                            <li><strong>starts_with:</strong> Begins with value</li>
                            <li><strong>ends_with:</strong> Ends with value</li>
                            <li><strong>not_contains:</strong> Does not contain</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Action Types</h4>
                        <ul style="font-size: 0.75rem; color: #6b7280; margin: 0; padding-left: 1rem;">
                            <li><strong>mark_as_read:</strong> Mark email as read</li>
                            <li><strong>escalate:</strong> Create escalation</li>
                            <li><strong>auto_reply:</strong> Send automatic reply</li>
                            <li><strong>forward:</strong> Forward to email</li>
                            <li><strong>tag:</strong> Add tag/label</li>
                        </ul>
                    </div>
                    
                    <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.375rem;">
                        <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">💡 Tips</h4>
                        <ul style="font-size: 0.75rem; color: #6b7280; margin: 0; padding-left: 1rem;">
                            <li>Rules are processed in priority order</li>
                            <li>All conditions must match (AND logic)</li>
                            <li>Test your rules before activating</li>
                            <li>Use specific conditions to avoid false matches</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let conditionCount = 0;
    let actionCount = 0;
    let accounts = [];
    
    const fieldOptions = [
        { value: 'from', label: 'From (Sender)' },
        { value: 'to', label: 'To (Recipient)' },
        { value: 'subject', label: 'Subject' },
        { value: 'body', label: 'Body/Content' },
        { value: 'has_attachment', label: 'Has Attachment' }
    ];
    
    const operatorOptions = [
        { value: 'contains', label: 'Contains' },
        { value: 'equals', label: 'Equals' },
        { value: 'starts_with', label: 'Starts with' },
        { value: 'ends_with', label: 'Ends with' },
        { value: 'not_contains', label: 'Does not contain' }
    ];
    
    const actionTypes = [
        { value: 'mark_as_read', label: 'Mark as Read', hasValue: false },
        { value: 'escalate', label: 'Create Escalation', hasValue: true, placeholder: 'Escalation reason' },
        { value: 'auto_reply', label: 'Auto Reply', hasValue: true, placeholder: 'Reply message' },
        { value: 'forward', label: 'Forward Email', hasValue: true, placeholder: 'Forward to email address' },
        { value: 'tag', label: 'Add Tag', hasValue: true, placeholder: 'Tag name' }
    ];
    
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
                populateAccountSelect();
            }
        } catch (error) {
            console.error('Error loading accounts:', error);
        }
    }
    
    function populateAccountSelect() {
        const select = document.getElementById('account_id');
        select.innerHTML = '<option value="">Select an email account</option>';
        
        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = account.email;
            select.appendChild(option);
        });
    }
    
    function addCondition() {
        conditionCount++;
        const container = document.getElementById('conditions-container');
        
        // Remove empty state if it exists
        if (container.querySelector('.text-center')) {
            container.innerHTML = '';
        }
        
        const conditionHtml = `
            <div class="condition-item border border-gray-200 rounded-lg p-4 mb-3" data-condition="${conditionCount}">
                <div class="flex justify-between items-start mb-3">
                    <h4 style="font-size: 0.875rem; font-weight: 600; margin: 0;">Condition ${conditionCount}</h4>
                    <button type="button" onclick="removeCondition(${conditionCount})" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="form-group">
                        <label class="form-label">Field</label>
                        <select class="form-select condition-field" data-condition="${conditionCount}">
                            <option value="">Select field</option>
                            ${fieldOptions.map(option => `<option value="${option.value}">${option.label}</option>`).join('')}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Operator</label>
                        <select class="form-select condition-operator" data-condition="${conditionCount}">
                            <option value="">Select operator</option>
                            ${operatorOptions.map(option => `<option value="${option.value}">${option.label}</option>`).join('')}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Value</label>
                        <input type="text" class="form-input condition-value" data-condition="${conditionCount}" placeholder="Enter value to match">
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', conditionHtml);
    }
    
    function removeCondition(conditionId) {
        const element = document.querySelector(`[data-condition="${conditionId}"]`);
        if (element) {
            element.remove();
        }
        
        // Show empty state if no conditions left
        const container = document.getElementById('conditions-container');
        if (container.children.length === 0) {
            container.innerHTML = `
                <div class="text-center" style="padding: 2rem; color: #6b7280;">
                    <i class="fas fa-filter" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <div>No conditions added yet</div>
                    <div style="font-size: 0.875rem;">Click "Add Condition" to get started</div>
                </div>
            `;
        }
    }
    
    function addAction() {
        actionCount++;
        const container = document.getElementById('actions-container');
        
        // Remove empty state if it exists
        if (container.querySelector('.text-center')) {
            container.innerHTML = '';
        }
        
        const actionHtml = `
            <div class="action-item border border-gray-200 rounded-lg p-4 mb-3" data-action="${actionCount}">
                <div class="flex justify-between items-start mb-3">
                    <h4 style="font-size: 0.875rem; font-weight: 600; margin: 0;">Action ${actionCount}</h4>
                    <button type="button" onclick="removeAction(${actionCount})" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="form-group">
                        <label class="form-label">Action Type</label>
                        <select class="form-select action-type" data-action="${actionCount}" onchange="toggleActionValue(${actionCount})">
                            <option value="">Select action</option>
                            ${actionTypes.map(option => `<option value="${option.value}" data-has-value="${option.hasValue}" data-placeholder="${option.placeholder || ''}">${option.label}</option>`).join('')}
                        </select>
                    </div>
                    
                    <div class="form-group action-value-container" data-action="${actionCount}" style="display: none;">
                        <label class="form-label">Value</label>
                        <input type="text" class="form-input action-value" data-action="${actionCount}" placeholder="">
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', actionHtml);
    }
    
    function removeAction(actionId) {
        const element = document.querySelector(`[data-action="${actionId}"]`);
        if (element) {
            element.remove();
        }
        
        // Show empty state if no actions left
        const container = document.getElementById('actions-container');
        if (container.children.length === 0) {
            container.innerHTML = `
                <div class="text-center" style="padding: 2rem; color: #6b7280;">
                    <i class="fas fa-cogs" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <div>No actions added yet</div>
                    <div style="font-size: 0.875rem;">Click "Add Action" to get started</div>
                </div>
            `;
        }
    }
    
    function toggleActionValue(actionId) {
        const select = document.querySelector(`select.action-type[data-action="${actionId}"]`);
        const container = document.querySelector(`.action-value-container[data-action="${actionId}"]`);
        const input = document.querySelector(`input.action-value[data-action="${actionId}"]`);
        
        const selectedOption = select.options[select.selectedIndex];
        const hasValue = selectedOption.getAttribute('data-has-value') === 'true';
        const placeholder = selectedOption.getAttribute('data-placeholder') || '';
        
        if (hasValue) {
            container.style.display = 'block';
            input.placeholder = placeholder;
            input.required = true;
        } else {
            container.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }
    
    function getFormData() {
        const conditions = [];
        const actions = [];
        
        // Collect conditions
        document.querySelectorAll('.condition-item').forEach(item => {
            const conditionId = item.getAttribute('data-condition');
            const field = document.querySelector(`.condition-field[data-condition="${conditionId}"]`).value;
            const operator = document.querySelector(`.condition-operator[data-condition="${conditionId}"]`).value;
            const value = document.querySelector(`.condition-value[data-condition="${conditionId}"]`).value;
            
            if (field && operator && value) {
                conditions.push({ field, operator, value });
            }
        });
        
        // Collect actions
        document.querySelectorAll('.action-item').forEach(item => {
            const actionId = item.getAttribute('data-action');
            const type = document.querySelector(`.action-type[data-action="${actionId}"]`).value;
            const value = document.querySelector(`.action-value[data-action="${actionId}"]`).value;
            
            if (type) {
                const action = { type };
                if (value) action.value = value;
                actions.push(action);
            }
        });
        
        return {
            name: document.getElementById('name').value,
            description: document.getElementById('description').value,
            account_id: parseInt(document.getElementById('account_id').value),
            priority: parseInt(document.getElementById('priority').value),
            is_active: document.getElementById('is_active').checked,
            conditions: JSON.stringify(conditions),
            actions: JSON.stringify(actions)
        };
    }
    
    async function testRule() {
        const formData = getFormData();
        
        if (!formData.name || !formData.account_id) {
            showError('Please fill in the rule name and select an account before testing.');
            return;
        }
        
        if (formData.conditions === '[]') {
            showError('Please add at least one condition to test the rule.');
            return;
        }
        
        const testBtn = document.getElementById('test-btn');
        const originalText = testBtn.innerHTML;
        
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
        testBtn.disabled = true;
        
        try {
            const response = await fetch('/api/rules/test', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showSuccess(`Rule test completed! Found ${result.matches || 0} matching emails.`);
            } else {
                showError('Test failed: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Test error: ' + error.message);
        } finally {
            testBtn.innerHTML = originalText;
            testBtn.disabled = false;
        }
    }
    
    // Form submission
    document.getElementById('rule-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = getFormData();
        const saveBtn = document.getElementById('save-btn');
        const originalText = saveBtn.innerHTML;
        
        // Validate required fields
        if (!formData.name || !formData.account_id) {
            showError('Please fill in all required fields.');
            return;
        }
        
        if (formData.conditions === '[]') {
            showError('Please add at least one condition.');
            return;
        }
        
        if (formData.actions === '[]') {
            showError('Please add at least one action.');
            return;
        }
        
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        saveBtn.disabled = true;
        
        try {
            const response = await fetch('/api/rules', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            if (response.ok) {
                showSuccess('Rule created successfully!');
                setTimeout(() => {
                    window.location.href = '{{ route("rules.index") }}';
                }, 1500);
            } else {
                const error = await response.json();
                showError('Error creating rule: ' + (error.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error creating rule: ' + error.message);
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
    
    // Load accounts when page loads
    document.addEventListener('DOMContentLoaded', loadAccounts);
</script>
@endpush