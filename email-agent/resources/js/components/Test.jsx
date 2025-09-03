import * as React from 'react';

function Test() {
    return React.createElement('div', { className: 'p-4' },
        React.createElement('h1', { className: 'text-2xl font-bold text-blue-600' }, 'React is Working!'),
        React.createElement('p', { className: 'text-gray-600 mt-2' }, 'This is a test component to verify React setup.')
    );
}

export default Test;