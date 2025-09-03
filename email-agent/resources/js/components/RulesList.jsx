import React, { useState, useEffect } from 'react';

const RulesList = ({ selectedAccount }) => {
    const [rules, setRules] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [showAddForm, setShowAddForm] = useState(false);
    const [editingRule, setEditingRule] = useState(null);
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        conditions: [{
            field: 'sender_email',
            operator: 'contains',
            value: ''
        }],
        actions: [{
            type: 'set_category',
            value: ''
        }],
        priority: 1,
        is_active: true
    });

    useEffect(() => {
        if (selectedAccount) {
            fetchRules();
        }
    }, [selectedAccount]);

    const fetchRules = async () => {
        if (!selectedAccount) return;
        
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`/api/rules?account_id=${selectedAccount.id}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch rules');
            }

            const data = await response.json();
            setRules(data.data || []);
        } catch (error) {
            setError(error.message);
        } finally {
            setLoading(false);
        }
    };

    const resetForm = () => {
        setFormData({
            name: '',
            description: '',
            conditions: [{
                field: 'sender_email',
                operator: 'contains',
                value: ''
            }],
            actions: [{
                type: 'set_category',
                value: ''
            }],
            priority: 1,
            is_active: true
        });
        setEditingRule(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const url = editingRule ? `/api/rules/${editingRule.id}` : '/api/rules';
            const method = editingRule ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method,
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...formData,
                    account_id: selectedAccount.id,
                    conditions: JSON.stringify(formData.conditions),
                    actions: JSON.stringify(formData.actions)
                })
            });

            if (!response.ok) {
                throw new Error(`Failed to ${editingRule ? 'update' : 'create'} rule`);
            }

            setShowAddForm(false);
            resetForm();
            fetchRules();
        } catch (error) {
            alert(`Error ${editingRule ? 'updating' : 'creating'} rule: ` + error.message);
        } finally {
            setLoading(false);
        }
    };

    const deleteRule = async (ruleId) => {
        if (!confirm('Are you sure you want to delete this rule?')) return;
        
        try {
            const response = await fetch(`/api/rules/${ruleId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                fetchRules();
            }
        } catch (error) {
            alert('Error deleting rule: ' + error.message);
        }
    };

    const toggleRule = async (ruleId, isActive) => {
        try {
            const response = await fetch(`/api/rules/${ruleId}`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ is_active: !isActive })
            });

            if (response.ok) {
                fetchRules();
            }
        } catch (error) {
            alert('Error updating rule: ' + error.message);
        }
    };

    const editRule = (rule) => {
        setEditingRule(rule);
        setFormData({
            name: rule.name,
            description: rule.description || '',
            conditions: typeof rule.conditions === 'string' ? JSON.parse(rule.conditions) : rule.conditions,
            actions: typeof rule.actions === 'string' ? JSON.parse(rule.actions) : rule.actions,
            priority: rule.priority,
            is_active: rule.is_active
        });
        setShowAddForm(true);
    };

    const addCondition = () => {
        setFormData({
            ...formData,
            conditions: [...formData.conditions, {
                field: 'sender_email',
                operator: 'contains',
                value: ''
            }]
        });
    };

    const removeCondition = (index) => {
        setFormData({
            ...formData,
            conditions: formData.conditions.filter((_, i) => i !== index)
        });
    };

    const updateCondition = (index, field, value) => {
        const newConditions = [...formData.conditions];
        newConditions[index] = { ...newConditions[index], [field]: value };
        setFormData({ ...formData, conditions: newConditions });
    };

    const addAction = () => {
        setFormData({
            ...formData,
            actions: [...formData.actions, {
                type: 'set_category',
                value: ''
            }]
        });
    };

    const removeAction = (index) => {
        setFormData({
            ...formData,
            actions: formData.actions.filter((_, i) => i !== index)
        });
    };

    const updateAction = (index, field, value) => {
        const newActions = [...formData.actions];
        newActions[index] = { ...newActions[index], [field]: value };
        setFormData({ ...formData, actions: newActions });
    };

    if (!selectedAccount) {
        return (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <div className="text-gray-400 text-4xl mb-4">⚙️</div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">No Account Selected</h3>
                <p className="text-gray-600">Select an email account to manage rules.</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Add/Edit Rule Form */}
            {showAddForm && (
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="text-lg font-medium text-gray-900">
                            {editingRule ? 'Edit Rule' : 'Add New Rule'}
                        </h3>
                        <button
                            onClick={() => {
                                setShowAddForm(false);
                                resetForm();
                            }}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>
                    
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Basic Info */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Rule Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={formData.name}
                                    onChange={(e) => setFormData({...formData, name: e.target.value})}
                                    className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="e.g., Support Emails"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Priority
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="100"
                                    value={formData.priority}
                                    onChange={(e) => setFormData({...formData, priority: parseInt(e.target.value)})}
                                    className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Description
                            </label>
                            <textarea
                                rows={2}
                                value={formData.description}
                                onChange={(e) => setFormData({...formData, description: e.target.value})}
                                className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Describe what this rule does..."
                            />
                        </div>

                        {/* Conditions */}
                        <div>
                            <div className="flex justify-between items-center mb-3">
                                <h4 className="font-medium text-gray-900">Conditions</h4>
                                <button
                                    type="button"
                                    onClick={addCondition}
                                    className="text-blue-600 hover:text-blue-700 text-sm font-medium"
                                >
                                    + Add Condition
                                </button>
                            </div>
                            
                            {formData.conditions.map((condition, index) => (
                                <div key={index} className="flex items-center space-x-2 mb-2">
                                    <select
                                        value={condition.field}
                                        onChange={(e) => updateCondition(index, 'field', e.target.value)}
                                        className="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="sender_email">Sender Email</option>
                                        <option value="sender_name">Sender Name</option>
                                        <option value="subject">Subject</option>
                                        <option value="body_text">Body Text</option>
                                        <option value="recipient_email">Recipient Email</option>
                                    </select>
                                    
                                    <select
                                        value={condition.operator}
                                        onChange={(e) => updateCondition(index, 'operator', e.target.value)}
                                        className="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="contains">Contains</option>
                                        <option value="equals">Equals</option>
                                        <option value="starts_with">Starts With</option>
                                        <option value="ends_with">Ends With</option>
                                        <option value="not_contains">Does Not Contain</option>
                                        <option value="regex">Regex Match</option>
                                    </select>
                                    
                                    <input
                                        type="text"
                                        value={condition.value}
                                        onChange={(e) => updateCondition(index, 'value', e.target.value)}
                                        className="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Value to match"
                                    />
                                    
                                    {formData.conditions.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeCondition(index)}
                                            className="text-red-600 hover:text-red-700"
                                        >
                                            ✕
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Actions */}
                        <div>
                            <div className="flex justify-between items-center mb-3">
                                <h4 className="font-medium text-gray-900">Actions</h4>
                                <button
                                    type="button"
                                    onClick={addAction}
                                    className="text-blue-600 hover:text-blue-700 text-sm font-medium"
                                >
                                    + Add Action
                                </button>
                            </div>
                            
                            {formData.actions.map((action, index) => (
                                <div key={index} className="flex items-center space-x-2 mb-2">
                                    <select
                                        value={action.type}
                                        onChange={(e) => updateAction(index, 'type', e.target.value)}
                                        className="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="set_category">Set Category</option>
                                        <option value="set_priority">Set Priority</option>
                                        <option value="add_tag">Add Tag</option>
                                        <option value="auto_reply">Auto Reply</option>
                                        <option value="forward_to">Forward To</option>
                                        <option value="mark_as_read">Mark as Read</option>
                                        <option value="escalate">Escalate</option>
                                    </select>
                                    
                                    {action.type !== 'mark_as_read' && (
                                        <input
                                            type="text"
                                            value={action.value}
                                            onChange={(e) => updateAction(index, 'value', e.target.value)}
                                            className="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder={
                                                action.type === 'set_category' ? 'Category name' :
                                                action.type === 'set_priority' ? 'high, medium, or low' :
                                                action.type === 'add_tag' ? 'Tag name' :
                                                action.type === 'auto_reply' ? 'Reply message' :
                                                action.type === 'forward_to' ? 'Email address' :
                                                'Action value'
                                            }
                                        />
                                    )}
                                    
                                    {formData.actions.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeAction(index)}
                                            className="text-red-600 hover:text-red-700"
                                        >
                                            ✕
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Active Toggle */}
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                id="is_active"
                                checked={formData.is_active}
                                onChange={(e) => setFormData({...formData, is_active: e.target.checked})}
                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                Rule is active
                            </label>
                        </div>

                        <div className="flex justify-end space-x-3 pt-4">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowAddForm(false);
                                    resetForm();
                                }}
                                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={loading}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                            >
                                {loading ? (editingRule ? 'Updating...' : 'Creating...') : (editingRule ? 'Update Rule' : 'Create Rule')}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Rules List */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                <div className="p-4 border-b border-gray-200">
                    <div className="flex justify-between items-center">
                        <h3 className="text-lg font-medium text-gray-900">
                            Rules - {selectedAccount.display_name || selectedAccount.email_address}
                        </h3>
                        <button
                            onClick={() => setShowAddForm(true)}
                            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            + Add Rule
                        </button>
                    </div>
                </div>
                
                <div className="divide-y divide-gray-200">
                    {error && (
                        <div className="p-4 bg-red-50 border-l-4 border-red-400">
                            <p className="text-red-700">Error: {error}</p>
                        </div>
                    )}

                    {loading && rules.length === 0 ? (
                        <div className="p-8 text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                            <p className="text-gray-600">Loading rules...</p>
                        </div>
                    ) : rules.length === 0 ? (
                        <div className="p-8 text-center">
                            <div className="text-gray-400 text-4xl mb-4">📋</div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">No Rules Found</h3>
                            <p className="text-gray-600 mb-4">Create your first rule to automate email processing.</p>
                            <button
                                onClick={() => setShowAddForm(true)}
                                className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Create Rule
                            </button>
                        </div>
                    ) : (
                        rules.map((rule) => {
                            const conditions = typeof rule.conditions === 'string' ? JSON.parse(rule.conditions) : rule.conditions;
                            const actions = typeof rule.actions === 'string' ? JSON.parse(rule.actions) : rule.actions;
                            
                            return (
                                <div key={rule.id} className="p-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center space-x-3 mb-2">
                                                <div className={`w-3 h-3 rounded-full ${
                                                    rule.is_active ? 'bg-green-400' : 'bg-gray-400'
                                                }`}></div>
                                                <h4 className="font-medium text-gray-900">{rule.name}</h4>
                                                <span className="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">
                                                    Priority: {rule.priority}
                                                </span>
                                            </div>
                                            
                                            {rule.description && (
                                                <p className="text-sm text-gray-600 mb-2">{rule.description}</p>
                                            )}
                                            
                                            <div className="text-sm text-gray-600">
                                                <p><strong>Conditions:</strong> {conditions.length} condition(s)</p>
                                                <p><strong>Actions:</strong> {actions.map(a => a.type).join(', ')}</p>
                                            </div>
                                        </div>
                                        
                                        <div className="flex space-x-2">
                                            <button
                                                onClick={() => toggleRule(rule.id, rule.is_active)}
                                                className={`px-3 py-1 text-sm rounded transition-colors ${
                                                    rule.is_active 
                                                        ? 'bg-red-100 text-red-700 hover:bg-red-200' 
                                                        : 'bg-green-100 text-green-700 hover:bg-green-200'
                                                }`}
                                            >
                                                {rule.is_active ? 'Disable' : 'Enable'}
                                            </button>
                                            <button
                                                onClick={() => editRule(rule)}
                                                className="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => deleteRule(rule.id)}
                                                className="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </div>
    );
};

export default RulesList;