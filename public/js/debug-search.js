/**
 * Debug Search Issues - Run this in browser console
 * This script helps debug why search isn't working
 */

// Add a Network Request Interceptor
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const [resource, config] = args;
    
    if (resource && resource.includes('menu')) {
        console.log('🔍 [FETCH] Network Request Intercepted');
        console.log('📍 URL:', resource);
        console.log('⚙️ Method:', config?.method || 'GET');
        console.log('📦 Headers:', config?.headers);
        console.log('💾 Body:', config?.body);
        
        try {
            if (config?.body) {
                console.log('📄 Parsed Body:', JSON.parse(config.body));
            }
        } catch (e) {
            console.log('📄 Body (raw):', config?.body);
        }
    }
    
    return originalFetch.apply(this, args);
};

// Monitor CustomDataTable instance
function monitorSearch() {
    if (window.menuTable) {
        console.log('✅ MenuTable instance found');
        console.log('🔎 Current searchTerm:', window.menuTable.searchTerm);
        console.log('📍 API URL:', window.menuTable.apiUrl);
        console.log('⚙️ Method:', window.menuTable.method);
        console.log('🔧 Request Format:', window.menuTable.format);
        console.log('📄 All properties:', {
            searchTerm: window.menuTable.searchTerm,
            apiUrl: window.menuTable.apiUrl,
            method: window.menuTable.method,
            format: window.menuTable.format,
            filterParams: window.menuTable.filterParams,
            additionalParams: window.menuTable.additionalParams
        });
    } else {
        console.log('❌ MenuTable instance not found');
    }
}

// Test function to trigger search
function testSearch(term) {
    console.log('🧪 Testing search with term:', term);
    if (window.menuTable) {
        window.menuTable.searchTerm = term;
        window.menuTable.currentPage = 1;
        window.menuTable.loadData();
        console.log('✅ Search triggered. Check Network tab for request.');
    } else {
        console.log('❌ MenuTable not initialized');
    }
}

console.log('%c🔍 Search Debug Tool Loaded', 'color: blue; font-size: 14px; font-weight: bold;');
console.log('Available commands:');
console.log('  monitorSearch()  - Check current menuTable state');
console.log('  testSearch("term") - Trigger a search with a test term');
