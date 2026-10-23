(() => {
  const app = document.querySelector('.admin-app');
  const sidebarToggle = document.querySelector('.admin-sidebar-toggle');

  if (app && sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      const collapsed = app.classList.toggle('is-sidebar-collapsed');
      sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
      sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    });
  }

  document.querySelectorAll('[data-open-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.openDialog);
      if (dialog instanceof HTMLDialogElement) {
        dialog.showModal();
        const firstInput = dialog.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) firstInput.focus();
      }
    });
  });

  document.querySelectorAll('.catalog-dialog[data-open-on-load]').forEach((dialog) => {
    if (dialog instanceof HTMLDialogElement) {
      dialog.showModal();
      dialog.removeAttribute('data-open-on-load');
    }
  });

  document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = button.closest('dialog');
      if (dialog instanceof HTMLDialogElement) dialog.close();
    });
  });

  document.querySelectorAll('.catalog-dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close();
      }
    });
  });
})();
