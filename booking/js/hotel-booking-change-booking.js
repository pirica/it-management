(function () {
  var openBtn = document.getElementById('hb-change-booking-open');
  var modal = document.getElementById('hb-change-booking-modal');
  if (!openBtn || !modal) {
    return;
  }

  function openModal() {
    modal.hidden = false;
    document.body.classList.add('hb-modal-open');
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('hb-modal-open');
  }

  openBtn.addEventListener('click', function () {
    openModal();
  });

  document.querySelectorAll('[data-hb-modal-close="hb-change-booking-modal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeModal();
    });
  });

  modal.addEventListener('click', function (e) {
    if (e.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });
})();
