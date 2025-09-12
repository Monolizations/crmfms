// CSS Loader - Fallback for preload CSS
(function() {
  'use strict';
  
  // Function to load CSS if preload failed
  function loadCSS(href, media) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    if (media) link.media = media;
    document.head.appendChild(link);
  }
  
  // Check if CSS is loaded by testing a specific style
  function isCSSLoaded() {
    if (!document.body) {
      return false; // Body not ready yet
    }
    
    var testEl = document.createElement('div');
    testEl.className = 'btn';
    testEl.style.position = 'absolute';
    testEl.style.left = '-9999px';
    document.body.appendChild(testEl);
    
    var isLoaded = window.getComputedStyle(testEl).borderRadius !== '';
    document.body.removeChild(testEl);
    return isLoaded;
  }
  
  // Fallback loader
  function ensureCSSLoaded() {
    if (!document.body) {
      return; // Body not ready yet
    }
    
    if (!isCSSLoaded()) {
      // Load Bootstrap CSS
      loadCSS('https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
      // Load custom CSS
      loadCSS('/crmfms/public/assests/css/app.css');
      // Load Font Awesome
      loadCSS('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
    }
  }
  
  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureCSSLoaded);
  } else {
    ensureCSSLoaded();
  }
  
  // Also run after a short delay as backup
  setTimeout(ensureCSSLoaded, 100);
})();
