import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import Test from './components/Test';

// Initialize React app if dashboard container exists
const dashboardContainer = document.getElementById('dashboard-root');
if (dashboardContainer) {
    const root = createRoot(dashboardContainer);
    root.render(<Test />);
}