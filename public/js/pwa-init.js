// PWA Initialization and Service Worker Registration
// Handles service worker registration, updates, and install prompts

(function() {
  'use strict';

  // Check if service workers are supported
  if (!('serviceWorker' in navigator)) {
    console.warn('PWA: Service Workers not supported');
    return;
  }

  // Check for HTTPS (required for production)
  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    console.warn('PWA: Service Workers require HTTPS in production');
  }

  // Register service worker
  window.addEventListener('load', () => {
    registerServiceWorker();
    handleBeforeInstallPrompt();
  });

  // Service Worker registration
  async function registerServiceWorker() {
    try {
      const registration = await navigator.serviceWorker.register(
        '/service-worker.js',
        { scope: '/' }
      );

      console.log('PWA: Service Worker registered successfully', registration);

      // Check for updates periodically
      setInterval(() => {
        registration.update();
      }, 60000); // Check every minute

      // Listen for service worker updates
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'activated' && navigator.serviceWorker.controller) {
            // New service worker available
            showUpdateAvailableNotification();
          }
        });
      });

      // Handle controller change (new worker takes over)
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        console.log('PWA: New Service Worker activated');
      });

    } catch (error) {
      console.error('PWA: Service Worker registration failed', error);
    }
  }

  // Install prompt handling
  let deferredPrompt;

  function handleBeforeInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent the mini-infobar from appearing on mobile
      e.preventDefault();
      // Stash the event for later use
      deferredPrompt = e;

      // Show install button/prompt if available
      const installButton = document.getElementById('pwa-install-button');
      if (installButton) {
        installButton.style.display = 'block';
        installButton.addEventListener('click', () => {
          if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
              if (choiceResult.outcome === 'accepted') {
                console.log('PWA: User accepted the install prompt');
              } else {
                console.log('PWA: User dismissed the install prompt');
              }
              deferredPrompt = null;
            });
          }
        });
      }
    });

    // Handle app installed
    window.addEventListener('appinstalled', () => {
      console.log('PWA: App installed successfully');
      if (deferredPrompt) {
        deferredPrompt = null;
      }
    });
  }

  // Show notification when update is available
  function showUpdateAvailableNotification() {
    // Create a simple notification toast
    const notification = document.createElement('div');
    notification.id = 'pwa-update-notification';
    notification.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #fa8507;
      color: white;
      padding: 16px 24px;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 14px;
      z-index: 10000;
      max-width: 300px;
      animation: slideInUp 0.3s ease-out;
    `;
    notification.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
        <span>📦 New version available!</span>
        <button id="pwa-update-btn" style="
          background: rgba(255, 255, 255, 0.2);
          border: none;
          color: white;
          padding: 6px 12px;
          border-radius: 4px;
          cursor: pointer;
          font-size: 12px;
          font-weight: 600;
          transition: background 0.2s;
        ">Update</button>
      </div>
    `;

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);

    // Handle update button click
    document.getElementById('pwa-update-btn').addEventListener('click', () => {
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
        window.location.reload();
      }
    });

    // Auto-remove notification after 10 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 10000);
  }

  // Check for updates on app visibility change
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && 'serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistration().then((registration) => {
        if (registration) {
          registration.update();
        }
      });
    }
  });

  // Request notification permission
  function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
          console.log('PWA: Notification permission granted');
        }
      });
    }
  }

  // Initialize notifications if user prefers
  window.addEventListener('load', () => {
    // Only request permission on manual user action
    document.addEventListener('click', () => {
      if ('Notification' in window && Notification.permission === 'default') {
        requestNotificationPermission();
      }
    }, { once: true });
  });

  // Expose API for checking PWA state
  window.PWA = {
    isInstalled: () => {
      return window.matchMedia('(display-mode: standalone)').matches ||
             navigator.standalone === true;
    },
    isDeferredPromptAvailable: () => {
      return deferredPrompt !== null;
    },
    showInstallPrompt: () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
      }
    },
    requestNotificationPermission: requestNotificationPermission,
    checkForUpdates: () => {
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then((registration) => {
          if (registration) {
            registration.update();
          }
        });
      }
    }
  };

  console.log('PWA: Initialization complete');
})();
