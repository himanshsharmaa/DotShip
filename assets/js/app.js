document.addEventListener('DOMContentLoaded', () => {
  if (window.AOS) {
    AOS.init({ duration: 700, once: true, offset: 60 });
  }

  const flashToast = document.querySelector('[data-flash-toast]');
  if (flashToast && window.Swal) {
    const payload = JSON.parse(flashToast.getAttribute('data-flash-toast'));
    Swal.fire({
      icon: payload.type || 'success',
      title: payload.title || 'DOT SHIP',
      text: payload.message || '',
      confirmButtonColor: '#ff7a00',
      backdrop: 'rgba(11, 31, 58, 0.4)',
    });
  }

  document.querySelectorAll('[data-toggle-password]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const target = document.querySelector(toggle.getAttribute('data-toggle-password'));
      if (!target) {
        return;
      }

      const icon = toggle.querySelector('i');
      const isPassword = target.getAttribute('type') === 'password';
      target.setAttribute('type', isPassword ? 'text' : 'password');
      if (icon) {
        icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
      }
    });
  });

  document.querySelectorAll('.btn-ripple').forEach((button) => {
    button.addEventListener('click', (event) => {
      const circle = document.createElement('span');
      const diameter = Math.max(button.clientWidth, button.clientHeight);
      const radius = diameter / 2;
      const rect = button.getBoundingClientRect();

      circle.style.width = circle.style.height = `${diameter}px`;
      circle.style.left = `${event.clientX - rect.left - radius}px`;
      circle.style.top = `${event.clientY - rect.top - radius}px`;
      circle.classList.add('ripple');

      const existingRipple = button.querySelector('.ripple');
      if (existingRipple) {
        existingRipple.remove();
      }

      button.appendChild(circle);
    });
  });

  document.querySelectorAll('[data-confirm-delete]').forEach((button) => {
    button.addEventListener('click', (event) => {
      if (!window.Swal) {
        return;
      }

      event.preventDefault();
      const form = button.closest('form');
      const message = button.getAttribute('data-confirm-delete') || 'This action cannot be undone.';

      Swal.fire({
        title: 'Delete this shipment?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it',
      }).then((result) => {
        if (result.isConfirmed && form) {
          form.submit();
        }
      });
    });
  });

  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-open');
    });
  }

  document.querySelectorAll('[data-live-search]').forEach((input) => {
    input.addEventListener('input', () => {
      const targetSelector = input.getAttribute('data-live-search');
      const rows = document.querySelectorAll(targetSelector + ' tbody tr');
      const query = input.value.toLowerCase();

      rows.forEach((row) => {
        row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
      });
    });
  });

  // Framer-like intersection entrance animations
  const inView = (el) => {
    el.classList.add('is-in');
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        inView(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.motion-fade-up, .motion-pop').forEach((el) => {
    observer.observe(el);
  });

  // Sticky navbar contrast toggle on scroll
  const navbar = document.querySelector('.navbar-glass');
  function checkNavbarSticky() {
    if (!navbar) return;
    if (window.scrollY > 24) {
      navbar.classList.add('stuck');
    } else {
      navbar.classList.remove('stuck');
    }
  }

  window.addEventListener('scroll', checkNavbarSticky, { passive: true });
  // initial check
  checkNavbarSticky();

  // Lightweight tilt on hover for .card-tilt elements
  document.querySelectorAll('.card-tilt').forEach((card) => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = (e.clientX - rect.left - rect.width / 2) / rect.width;
      const y = (e.clientY - rect.top - rect.height / 2) / rect.height;
      const rotX = (y * 6).toFixed(2);
      const rotY = (x * -6).toFixed(2);
      card.style.transform = `perspective(800px) rotateX(${rotX}deg) rotateY(${rotY}deg) translateZ(6px)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });
});