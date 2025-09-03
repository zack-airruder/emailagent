<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Email Agent - Automated Email Processing</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css'])
        
        <style>
            body {
                font-family: 'Inter', sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f9fafb;
            }
            .tab-active {
                background-color: #3b82f6;
                color: white;
            }
            .tab-inactive {
                background-color: #f3f4f6;
                color: #6b7280;
            }
            .hidden {
                display: none;
            }
            .min-h-screen {
                min-height: 100vh;
            }
            .bg-white {
                background-color: white;
            }
            .bg-gray-50 {
                background-color: #f9fafb;
            }
            .bg-gray-100 {
                background-color: #f3f4f6;
            }
            .shadow {
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            }
            .shadow-sm {
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }
            .border-b {
                border-bottom-width: 1px;
            }
            .border-gray-200 {
                border-color: #e5e7eb;
            }
            .max-w-7xl {
                max-width: 80rem;
            }
            .mx-auto {
                margin-left: auto;
                margin-right: auto;
            }
            .px-4 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .py-8 {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }
            .py-4 {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }
            .py-2 {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
            .py-12 {
                padding-top: 3rem;
                padding-bottom: 3rem;
            }
            .px-2 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            .p-1 {
                padding: 0.25rem;
            }
            .p-4 {
                padding: 1rem;
            }
            .p-6 {
                padding: 1.5rem;
            }
            .mb-4 {
                margin-bottom: 1rem;
            }
            .mb-6 {
                margin-bottom: 1.5rem;
            }
            .mt-12 {
                margin-top: 3rem;
            }
            .h-16 {
                height: 4rem;
            }
            .flex {
                display: flex;
            }
            .justify-between {
                justify-content: space-between;
            }
            .justify-center {
                justify-content: center;
            }
            .items-center {
                align-items: center;
            }
            .space-x-1 > * + * {
                margin-left: 0.25rem;
            }
            .space-x-2 > * + * {
                margin-left: 0.5rem;
            }
            .space-x-4 > * + * {
                margin-left: 1rem;
            }
            .rounded-lg {
                border-radius: 0.5rem;
            }
            .rounded-md {
                border-radius: 0.375rem;
            }
            .rounded-full {
                border-radius: 9999px;
            }
            .text-2xl {
                font-size: 1.5rem;
                line-height: 2rem;
            }
            .text-xl {
                font-size: 1.25rem;
                line-height: 1.75rem;
            }
            .text-lg {
                font-size: 1.125rem;
                line-height: 1.75rem;
            }
            .text-sm {
                font-size: 0.875rem;
                line-height: 1.25rem;
            }
            .text-xs {
                font-size: 0.75rem;
                line-height: 1rem;
            }
            .text-6xl {
                font-size: 3.75rem;
                line-height: 1;
            }
            .font-bold {
                font-weight: 700;
            }
            .font-semibold {
                font-weight: 600;
            }
            .font-medium {
                font-weight: 500;
            }
            .text-gray-900 {
                color: #111827;
            }
            .text-gray-600 {
                color: #4b5563;
            }
            .text-gray-500 {
                color: #6b7280;
            }
            .text-white {
                color: white;
            }
            .text-blue-600 {
                color: #2563eb;
            }
            .text-center {
                text-align: center;
            }
            .bg-blue-600 {
                background-color: #2563eb;
            }
            .bg-blue-50 {
                background-color: #eff6ff;
            }
            .hover\:bg-blue-700:hover {
                background-color: #1d4ed8;
            }
            .transition-colors {
                transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
                transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
                transition-duration: 150ms;
            }
            .grid {
                display: grid;
            }
            .grid-cols-1 {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
            .grid-cols-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .gap-6 {
                gap: 1.5rem;
            }
            .border {
                border-width: 1px;
            }
            .border-t {
                border-top-width: 1px;
            }
            .flex-1 {
                flex: 1 1 0%;
            }
            .items-start {
                align-items: flex-start;
            }
            .mt-1 {
                margin-top: 0.25rem;
            }
            .hover\:text-blue-800:hover {
                color: #1e40af;
            }
            .hover\:text-green-800:hover {
                color: #166534;
            }
            .hover\:text-gray-800:hover {
                color: #1f2937;
            }
            .text-green-600 {
                color: #16a34a;
            }
            .text-red-800 {
                color: #991b1b;
            }
            .text-orange-800 {
                color: #9a3412;
            }
            .text-yellow-800 {
                color: #92400e;
            }
            .text-green-800 {
                color: #166534;
            }
            .text-gray-800 {
                color: #1f2937;
            }
            .bg-red-100 {
                background-color: #fee2e2;
            }
            .bg-orange-100 {
                background-color: #ffedd5;
            }
            .bg-yellow-100 {
                background-color: #fef3c7;
            }
            .bg-green-100 {
                background-color: #dcfce7;
            }
            .bg-gray-100 {
                background-color: #f3f4f6;
            }
            @media (min-width: 640px) {
                .sm\:px-6 {
                    padding-left: 1.5rem;
                    padding-right: 1.5rem;
                }
            }
            @media (min-width: 1024px) {
                .lg\:px-8 {
                    padding-left: 2rem;
                    padding-right: 2rem;
                }
            }
            @media (min-width: 768px) {
                .md\:grid-cols-3 {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }
        </style>
    </head>
    <body class="antialiased bg-gray-50">
        <div class="min-h-screen">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <div class="flex items-center">
                            <h1 class="text-2xl font-bold text-gray-900">
                                📧 Email Agent
                            </h1>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-500">{{ date('M j, Y') }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Navigation Tabs -->
                <div class="mb-6">
                    <nav class="flex space-x-1 bg-gray-100 p-1 rounded-lg">
                        <button onclick="showTab('emails')" id="tab-emails" class="tab-active px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            📧 Emails
                        </button>
                        <button onclick="showTab('accounts')" id="tab-accounts" class="tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            ⚙️ Accounts
                        </button>
                        <button onclick="showTab('rules')" id="tab-rules" class="tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            📋 Rules
                        </button>
                        <button onclick="showTab('escalations')" id="tab-escalations" class="tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            🚨 Escalations
                        </button>
                        <button onclick="showTab('stats')" id="tab-stats" class="tab-inactive px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            📊 Statistics
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div id="content-emails" class="tab-content">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Email Management</h2>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📬</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No emails to display</h3>
                            <p class="text-gray-500 mb-4">Connect an email account to start processing emails automatically.</p>
                            <button onclick="showTab('accounts')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Add Email Account
                            </button>
                        </div>
                    </div>
                </div>

                <div id="content-accounts" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Email Accounts</h2>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📮</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No accounts configured</h3>
                            <p class="text-gray-500 mb-4">Add your first email account to start automated email processing.</p>
                            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Add Account
                            </button>
                        </div>
                    </div>
                </div>

                <div id="content-rules" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Processing Rules</h2>
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📋</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No rules defined</h3>
                            <p class="text-gray-500 mb-4">Create rules to automatically process and respond to emails.</p>
                            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Create Rule
                            </button>
                        </div>
                    </div>
                </div>

                <div id="content-escalations" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-gray-900">Human Escalation Queue</h2>
                            <div class="flex space-x-2">
                                <select id="escalation-status-filter" class="border border-gray-300 rounded-md px-3 py-1 text-sm">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                                <select id="escalation-priority-filter" class="border border-gray-300 rounded-md px-3 py-1 text-sm">
                                    <option value="">All Priority</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Escalation Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-red-600" id="pending-count">0</div>
                                <div class="text-sm text-gray-600">Pending</div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600" id="in-progress-count">0</div>
                                <div class="text-sm text-gray-600">In Progress</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600" id="resolved-count">0</div>
                                <div class="text-sm text-gray-600">Resolved</div>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600" id="avg-resolution-time">0h</div>
                                <div class="text-sm text-gray-600">Avg Resolution</div>
                            </div>
                        </div>
                        
                        <!-- Escalations List -->
                        <div id="escalations-list">
                            <div class="text-center py-12">
                                <div class="text-6xl mb-4">🚨</div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No escalations found</h3>
                                <p class="text-gray-500 mb-4">Emails requiring human attention will appear here.</p>
                                <button onclick="loadEscalations()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="content-stats" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Statistics & Analytics</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">0</div>
                                <div class="text-sm text-gray-600">Emails Processed</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">0</div>
                                <div class="text-sm text-gray-600">Auto Responses</div>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-purple-600">0</div>
                                <div class="text-sm text-gray-600">Rules Active</div>
                            </div>
                        </div>
                        <div class="text-center py-8">
                            <div class="text-4xl mb-4">📊</div>
                            <p class="text-gray-500">Statistics will appear here once you start processing emails.</p>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 mt-12">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <div class="text-center text-sm text-gray-500">
                        Email Agent - Automated Email Processing System
                    </div>
                </div>
            </footer>
        </div>

        <script>
            function showTab(tabName) {
                // Hide all tab contents
                const contents = document.querySelectorAll('.tab-content');
                contents.forEach(content => content.classList.add('hidden'));
                
                // Remove active class from all tabs
                const tabs = document.querySelectorAll('[id^="tab-"]');
                tabs.forEach(tab => {
                    tab.classList.remove('tab-active');
                    tab.classList.add('tab-inactive');
                });
                
                // Show selected tab content
                document.getElementById('content-' + tabName).classList.remove('hidden');
                
                // Add active class to selected tab
                document.getElementById('tab-' + tabName).classList.remove('tab-inactive');
                document.getElementById('tab-' + tabName).classList.add('tab-active');
                
                // Load data for specific tabs
                if (tabName === 'escalations') {
                    loadEscalations();
                    loadEscalationStats();
                }
            }
            
            async function loadEscalations() {
                try {
                    const statusFilter = document.getElementById('escalation-status-filter').value;
                    const priorityFilter = document.getElementById('escalation-priority-filter').value;
                    
                    let url = '/api/escalations?';
                    const params = new URLSearchParams();
                    if (statusFilter) params.append('status', statusFilter);
                    if (priorityFilter) params.append('priority', priorityFilter);
                    
                    const response = await fetch(url + params.toString(), {
                        headers: {
                            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error('Failed to load escalations');
                    }
                    
                    const data = await response.json();
                    displayEscalations(data.data);
                } catch (error) {
                    console.error('Error loading escalations:', error);
                    document.getElementById('escalations-list').innerHTML = `
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">⚠️</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading escalations</h3>
                            <p class="text-gray-500 mb-4">${error.message}</p>
                            <button onclick="loadEscalations()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Retry
                            </button>
                        </div>
                    `;
                }
            }
            
            async function loadEscalationStats() {
                try {
                    const response = await fetch('/api/escalations/stats', {
                        headers: {
                            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        const stats = data.data;
                        
                        document.getElementById('pending-count').textContent = stats.pending || 0;
                        document.getElementById('in-progress-count').textContent = stats.in_progress || 0;
                        document.getElementById('resolved-count').textContent = stats.resolved || 0;
                        document.getElementById('avg-resolution-time').textContent = 
                            stats.avg_resolution_time ? `${stats.avg_resolution_time}h` : '0h';
                    }
                } catch (error) {
                    console.error('Error loading escalation stats:', error);
                }
            }
            
            function displayEscalations(escalations) {
                const container = document.getElementById('escalations-list');
                
                if (!escalations || escalations.data.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">🚨</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No escalations found</h3>
                            <p class="text-gray-500 mb-4">Emails requiring human attention will appear here.</p>
                            <button onclick="loadEscalations()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Refresh
                            </button>
                        </div>
                    `;
                    return;
                }
                
                const escalationsList = escalations.data.map(escalation => {
                    const priorityColors = {
                        urgent: 'bg-red-100 text-red-800',
                        high: 'bg-orange-100 text-orange-800',
                        medium: 'bg-yellow-100 text-yellow-800',
                        low: 'bg-green-100 text-green-800'
                    };
                    
                    const statusColors = {
                        pending: 'bg-red-100 text-red-800',
                        in_progress: 'bg-yellow-100 text-yellow-800',
                        resolved: 'bg-green-100 text-green-800',
                        closed: 'bg-gray-100 text-gray-800'
                    };
                    
                    return `
                        <div class="border border-gray-200 rounded-lg p-4 mb-4">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900">${escalation.reason}</h4>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Email: ${escalation.processed_email?.from_address || 'N/A'}
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${priorityColors[escalation.priority] || 'bg-gray-100 text-gray-800'}">
                                        ${escalation.priority.toUpperCase()}
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${statusColors[escalation.status] || 'bg-gray-100 text-gray-800'}">
                                        ${escalation.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span>Created: ${new Date(escalation.escalated_at).toLocaleDateString()}</span>
                                <div class="flex space-x-2">
                                    ${escalation.status === 'pending' ? `
                                        <button onclick="assignEscalation(${escalation.id})" class="text-blue-600 hover:text-blue-800">
                                            Assign to Me
                                        </button>
                                    ` : ''}
                                    ${escalation.status === 'in_progress' ? `
                                        <button onclick="resolveEscalation(${escalation.id})" class="text-green-600 hover:text-green-800">
                                            Resolve
                                        </button>
                                    ` : ''}
                                    <button onclick="viewEscalation(${escalation.id})" class="text-gray-600 hover:text-gray-800">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                container.innerHTML = escalationsList;
            }
            
            async function assignEscalation(escalationId) {
                try {
                    const response = await fetch(`/api/escalations/${escalationId}/assign`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            assigned_to: 1 // Assuming user ID 1 for demo
                        })
                    });
                    
                    if (response.ok) {
                        loadEscalations();
                        loadEscalationStats();
                    } else {
                        alert('Failed to assign escalation');
                    }
                } catch (error) {
                    console.error('Error assigning escalation:', error);
                    alert('Error assigning escalation');
                }
            }
            
            async function resolveEscalation(escalationId) {
                const notes = prompt('Enter resolution notes:');
                if (!notes) return;
                
                try {
                    const response = await fetch(`/api/escalations/${escalationId}/resolve`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            resolution_notes: notes
                        })
                    });
                    
                    if (response.ok) {
                        loadEscalations();
                        loadEscalationStats();
                    } else {
                        alert('Failed to resolve escalation');
                    }
                } catch (error) {
                    console.error('Error resolving escalation:', error);
                    alert('Error resolving escalation');
                }
            }
            
            function viewEscalation(escalationId) {
                alert(`Viewing escalation ${escalationId} - Full details view would be implemented here`);
            }
            
            // Add event listeners for filters
            document.addEventListener('DOMContentLoaded', function() {
                const statusFilter = document.getElementById('escalation-status-filter');
                const priorityFilter = document.getElementById('escalation-priority-filter');
                
                if (statusFilter) {
                    statusFilter.addEventListener('change', loadEscalations);
                }
                if (priorityFilter) {
                    priorityFilter.addEventListener('change', loadEscalations);
                }
            });
        </script>
    </body>
</html>
