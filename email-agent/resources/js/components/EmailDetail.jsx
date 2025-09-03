import React, { useState, useEffect } from 'react';

const EmailDetail = ({ email, onEmailUpdate, onClose }) => {
    const [loading, setLoading] = useState(false);
    const [showReplyForm, setShowReplyForm] = useState(false);
    const [showForwardForm, setShowForwardForm] = useState(false);
    const [replyData, setReplyData] = useState({
        to: '',
        subject: '',
        body: ''
    });
    const [forwardData, setForwardData] = useState({
        to: '',
        subject: '',
        body: ''
    });
    const [classification, setClassification] = useState(null);
    const [sentiment, setSentiment] = useState(null);

    useEffect(() => {
        if (email) {
            // Initialize reply data
            setReplyData({
                to: email.sender_email,
                subject: email.subject.startsWith('Re:') ? email.subject : `Re: ${email.subject}`,
                body: `\n\n--- Original Message ---\nFrom: ${email.sender_email}\nDate: ${new Date(email.received_at).toLocaleString()}\nSubject: ${email.subject}\n\n${email.body_text || email.body_html || ''}`
            });

            // Initialize forward data
            setForwardData({
                to: '',
                subject: email.subject.startsWith('Fwd:') ? email.subject : `Fwd: ${email.subject}`,
                body: `\n\n--- Forwarded Message ---\nFrom: ${email.sender_email}\nDate: ${new Date(email.received_at).toLocaleString()}\nTo: ${email.recipient_email}\nSubject: ${email.subject}\n\n${email.body_text || email.body_html || ''}`
            });
        }
    }, [email]);

    const classifyEmail = async () => {
        if (!email) return;
        
        setLoading(true);
        try {
            const response = await fetch('/api/llm/classify', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email_id: email.id,
                    subject: email.subject,
                    body: email.body_text || email.body_html,
                    sender: email.sender_email
                })
            });

            if (response.ok) {
                const data = await response.json();
                setClassification(data.classification);
                if (onEmailUpdate) onEmailUpdate();
            }
        } catch (error) {
            console.error('Failed to classify email:', error);
        } finally {
            setLoading(false);
        }
    };

    const analyzeSentiment = async () => {
        if (!email) return;
        
        setLoading(true);
        try {
            const response = await fetch('/api/llm/sentiment', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    text: email.body_text || email.body_html
                })
            });

            if (response.ok) {
                const data = await response.json();
                setSentiment(data.sentiment);
            }
        } catch (error) {
            console.error('Failed to analyze sentiment:', error);
        } finally {
            setLoading(false);
        }
    };

    const generateResponse = async () => {
        if (!email) return;
        
        setLoading(true);
        try {
            const response = await fetch('/api/llm/generate-response', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email_id: email.id,
                    subject: email.subject,
                    body: email.body_text || email.body_html,
                    sender: email.sender_email,
                    context: 'reply'
                })
            });

            if (response.ok) {
                const data = await response.json();
                setReplyData({
                    ...replyData,
                    body: data.response
                });
                setShowReplyForm(true);
            }
        } catch (error) {
            console.error('Failed to generate response:', error);
        } finally {
            setLoading(false);
        }
    };

    const sendReply = async (e) => {
        e.preventDefault();
        setLoading(true);
        
        try {
            const response = await fetch('/api/smtp/reply', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    original_email_id: email.id,
                    to: replyData.to,
                    subject: replyData.subject,
                    body: replyData.body
                })
            });

            if (response.ok) {
                setShowReplyForm(false);
                alert('Reply sent successfully!');
                if (onEmailUpdate) onEmailUpdate();
            } else {
                const errorData = await response.json();
                alert('Failed to send reply: ' + (errorData.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error sending reply: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    const sendForward = async (e) => {
        e.preventDefault();
        setLoading(true);
        
        try {
            const response = await fetch('/api/smtp/forward', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    original_email_id: email.id,
                    to: forwardData.to,
                    subject: forwardData.subject,
                    body: forwardData.body
                })
            });

            if (response.ok) {
                setShowForwardForm(false);
                alert('Email forwarded successfully!');
                if (onEmailUpdate) onEmailUpdate();
            } else {
                const errorData = await response.json();
                alert('Failed to forward email: ' + (errorData.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error forwarding email: ' + error.message);
        } finally {
            setLoading(false);
        }
    };

    const updateStatus = async (newStatus) => {
        if (!email) return;
        
        try {
            const response = await fetch(`/api/emails/${email.id}/status`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (response.ok && onEmailUpdate) {
                onEmailUpdate();
            }
        } catch (error) {
            console.error('Failed to update status:', error);
        }
    };

    if (!email) {
        return (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <div className="text-gray-400 text-4xl mb-4">📧</div>
                <h3 className="text-lg font-medium text-gray-900 mb-2">No Email Selected</h3>
                <p className="text-gray-600">Select an email from the list to view details.</p>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            {/* Header */}
            <div className="p-6 border-b border-gray-200">
                <div className="flex justify-between items-start mb-4">
                    <div className="flex-1">
                        <h2 className="text-xl font-semibold text-gray-900 mb-2">{email.subject}</h2>
                        <div className="flex items-center space-x-4 text-sm text-gray-600">
                            <span><strong>From:</strong> {email.sender_name || email.sender_email}</span>
                            <span><strong>To:</strong> {email.recipient_email}</span>
                            <span><strong>Date:</strong> {new Date(email.received_at).toLocaleString()}</span>
                        </div>
                    </div>
                    {onClose && (
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    )}
                </div>

                {/* Status and Tags */}
                <div className="flex items-center space-x-2 mb-4">
                    {email.category && (
                        <span className="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-600">
                            {email.category}
                        </span>
                    )}
                    {email.priority && (
                        <span className={`px-2 py-1 text-xs rounded-full ${
                            email.priority === 'high' ? 'bg-red-100 text-red-600' :
                            email.priority === 'medium' ? 'bg-yellow-100 text-yellow-600' :
                            'bg-green-100 text-green-600'
                        }`}>
                            {email.priority} priority
                        </span>
                    )}
                    <span className={`px-2 py-1 text-xs rounded-full ${
                        email.status === 'processed' ? 'bg-green-100 text-green-600' :
                        email.status === 'escalated' ? 'bg-red-100 text-red-600' :
                        'bg-gray-100 text-gray-600'
                    }`}>
                        {email.status || 'unprocessed'}
                    </span>
                </div>

                {/* Action Buttons */}
                <div className="flex flex-wrap gap-2">
                    <button
                        onClick={() => setShowReplyForm(true)}
                        className="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Reply
                    </button>
                    <button
                        onClick={() => setShowForwardForm(true)}
                        className="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                    >
                        Forward
                    </button>
                    <button
                        onClick={generateResponse}
                        disabled={loading}
                        className="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
                    >
                        {loading ? 'Generating...' : '🤖 AI Reply'}
                    </button>
                    <button
                        onClick={classifyEmail}
                        disabled={loading}
                        className="px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50"
                    >
                        {loading ? 'Classifying...' : '🏷️ Classify'}
                    </button>
                    <button
                        onClick={analyzeSentiment}
                        disabled={loading}
                        className="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
                    >
                        {loading ? 'Analyzing...' : '😊 Sentiment'}
                    </button>
                    
                    {/* Status Update Dropdown */}
                    <select
                        value={email.status || 'unprocessed'}
                        onChange={(e) => updateStatus(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="unprocessed">Unprocessed</option>
                        <option value="processed">Processed</option>
                        <option value="escalated">Escalated</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>

            {/* Classification Results */}
            {classification && (
                <div className="p-4 bg-purple-50 border-b border-gray-200">
                    <h4 className="font-medium text-purple-900 mb-2">AI Classification</h4>
                    <div className="text-sm text-purple-700">
                        <p><strong>Category:</strong> {classification.category}</p>
                        <p><strong>Priority:</strong> {classification.priority}</p>
                        <p><strong>Confidence:</strong> {Math.round(classification.confidence * 100)}%</p>
                        {classification.tags && classification.tags.length > 0 && (
                            <p><strong>Tags:</strong> {classification.tags.join(', ')}</p>
                        )}
                    </div>
                </div>
            )}

            {/* Sentiment Analysis */}
            {sentiment && (
                <div className="p-4 bg-orange-50 border-b border-gray-200">
                    <h4 className="font-medium text-orange-900 mb-2">Sentiment Analysis</h4>
                    <div className="text-sm text-orange-700">
                        <p><strong>Sentiment:</strong> {sentiment.label}</p>
                        <p><strong>Score:</strong> {Math.round(sentiment.score * 100)}%</p>
                    </div>
                </div>
            )}

            {/* Email Content */}
            <div className="p-6">
                {email.body_html ? (
                    <div 
                        className="prose max-w-none"
                        dangerouslySetInnerHTML={{ __html: email.body_html }}
                    />
                ) : (
                    <div className="whitespace-pre-wrap text-gray-900">
                        {email.body_text || 'No content available'}
                    </div>
                )}

                {/* Attachments */}
                {email.has_attachments && (
                    <div className="mt-6 pt-4 border-t border-gray-200">
                        <h4 className="font-medium text-gray-900 mb-2">Attachments</h4>
                        <div className="text-sm text-gray-600">
                            <p>📎 This email contains attachments</p>
                        </div>
                    </div>
                )}
            </div>

            {/* Reply Form Modal */}
            {showReplyForm && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-medium text-gray-900">Reply to Email</h3>
                                <button
                                    onClick={() => setShowReplyForm(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    ✕
                                </button>
                            </div>
                            
                            <form onSubmit={sendReply} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">To</label>
                                    <input
                                        type="email"
                                        required
                                        value={replyData.to}
                                        onChange={(e) => setReplyData({...replyData, to: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                    <input
                                        type="text"
                                        required
                                        value={replyData.subject}
                                        onChange={(e) => setReplyData({...replyData, subject: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                    <textarea
                                        required
                                        rows={10}
                                        value={replyData.body}
                                        onChange={(e) => setReplyData({...replyData, body: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div className="flex justify-end space-x-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowReplyForm(false)}
                                        className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                    >
                                        {loading ? 'Sending...' : 'Send Reply'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Forward Form Modal */}
            {showForwardForm && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-medium text-gray-900">Forward Email</h3>
                                <button
                                    onClick={() => setShowForwardForm(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    ✕
                                </button>
                            </div>
                            
                            <form onSubmit={sendForward} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">To</label>
                                    <input
                                        type="email"
                                        required
                                        value={forwardData.to}
                                        onChange={(e) => setForwardData({...forwardData, to: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="recipient@example.com"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                    <input
                                        type="text"
                                        required
                                        value={forwardData.subject}
                                        onChange={(e) => setForwardData({...forwardData, subject: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                    <textarea
                                        required
                                        rows={10}
                                        value={forwardData.body}
                                        onChange={(e) => setForwardData({...forwardData, body: e.target.value})}
                                        className="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div className="flex justify-end space-x-3">
                                    <button
                                        type="button"
                                        onClick={() => setShowForwardForm(false)}
                                        className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                                    >
                                        {loading ? 'Forwarding...' : 'Forward Email'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmailDetail;