/**
 * Global JavaScript Helpers & Interactions
 */

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Sidebar Toggle with overlay support
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebar = document.querySelector('.app-sidebar');
  
  // Create overlay element if not exists
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
  }

  // Restores sidebar collapsed state on desktop load
  if (window.innerWidth > 768) {
    const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
    if (isCollapsed && sidebar) {
      sidebar.classList.add('collapsed');
    }
  }

  if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', () => {
      if (window.innerWidth > 768) {
        // Desktop collapse toggle
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
      } else {
        // Mobile menu toggle
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
      }
    });

    // Close sidebar on overlay click (mobile only)
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
    });
  }

  // Dark Mode Toggle
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  if (themeToggleBtn) {
    const currentTheme = localStorage.getItem('crm_theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);

    themeToggleBtn.addEventListener('click', () => {
      const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('crm_theme', newTheme);
      updateThemeIcon(newTheme);
      showToast(`${newTheme === 'dark' ? 'Dark Mode' : 'Light Mode'} Enabled`, 'info');
    });
  }
});

function updateThemeIcon(theme) {
  const icon = document.querySelector('#themeToggleBtn i');
  if (icon) {
    if (theme === 'dark') {
      icon.className = 'fas fa-sun text-warning';
    } else {
      icon.className = 'fas fa-moon text-dark';
    }
  }
}

// Toast notification helper
function showToast(message, type = 'info') {
  const toastContainer = document.getElementById('toastContainer') || createToastContainer();
  const toastId = 'toast_' + Date.now();
  
  const icon = type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle';
  const bgClass = type === 'success' ? 'bg-success text-white' : type === 'danger' ? 'bg-danger text-white' : 'bg-dark text-white';

  const html = `
    <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="fas ${icon}"></i> ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  `;

  toastContainer.insertAdjacentHTML('beforeend', html);
  const toastElement = document.getElementById(toastId);
  const bsToast = new bootstrap.Toast(toastElement, { delay: 3000 });
  bsToast.show();

  toastElement.addEventListener('hidden.bs.toast', () => {
    toastElement.remove();
  });
}

function createToastContainer() {
  const container = document.createElement('div');
  container.id = 'toastContainer';
  container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
  container.style.zIndex = '1090';
  document.body.appendChild(container);
  return container;
}

// Global SweetAlert Confirm Handlers
document.addEventListener('click', (e) => {
  const confirmEl = e.target.closest('[data-confirm]');
  if (confirmEl) {
    e.preventDefault();
    const message = confirmEl.getAttribute('data-confirm') || 'Are you sure?';
    const title = confirmEl.getAttribute('data-confirm-title') || 'Confirm Action';
    const confirmText = confirmEl.getAttribute('data-confirm-btn') || 'Yes, proceed';
    const cancelText = confirmEl.getAttribute('data-cancel-btn') || 'Cancel';
    const url = confirmEl.getAttribute('href');

    Swal.fire({
      title: title,
      text: message,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
      color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#f8fafc' : '#1e293b'
    }).then((result) => {
      if (result.isConfirmed) {
        if (url && url !== '#' && url !== 'javascript:void(0)') {
          window.location.href = url;
        } else {
          // Find parent form to submit if button
          const form = confirmEl.closest('form');
          if (form) {
            // Add a hidden element to simulate the submit button clicked
            if (confirmEl.name) {
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = confirmEl.name;
              hiddenInput.value = confirmEl.value || '1';
              form.appendChild(hiddenInput);
            }
            form.submit();
          }
        }
      }
    });
  }
});

// Global SweetAlert2 Constructor Proxy override for premium bounce physics and theme matching
if (typeof Swal !== 'undefined') {
  const originalFire = Swal.fire;
  
  Swal.fire = function(...args) {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    
    const defaultOptions = {
      background: isDark ? '#1e293b' : '#ffffff',
      color: isDark ? '#f8fafc' : '#1e293b',
      confirmButtonColor: '#3b82f6',
      showClass: {
        popup: 'swal2-premium-show'
      },
      hideClass: {
        popup: 'swal2-premium-hide'
      }
    };

    if (args.length === 1 && typeof args[0] === 'object') {
      const mergedOptions = Object.assign({}, defaultOptions, args[0]);
      mergedOptions.showClass = Object.assign({}, defaultOptions.showClass, args[0].showClass);
      mergedOptions.hideClass = Object.assign({}, defaultOptions.hideClass, args[0].hideClass);
      
      if (args[0].icon === 'warning') {
        mergedOptions.confirmButtonColor = args[0].confirmButtonColor || '#ef4444';
      }
      
      return originalFire.call(Swal, mergedOptions);
    } else {
      const options = Object.assign({}, defaultOptions, {
        title: args[0] || '',
        text: args[1] || '',
        icon: args[2] || undefined
      });
      return originalFire.call(Swal, options);
    }
  };
}


