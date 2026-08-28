document.addEventListener('DOMContentLoaded', function () {
  const root = document.querySelector('.tranter-events');
  if (!root) return;

  const modal = root.querySelector('.tranter-modal');
  const modalContent = root.querySelector('.tranter-modal-content');
  const closeModal = function () {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  function openTemplate(id) {
    const template = document.getElementById('tranter-event-' + id);
    if (!template) return;
    modalContent.innerHTML = template.innerHTML;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  root.querySelectorAll('.tranter-card-trigger').forEach(function (button) {
    button.addEventListener('click', function () {
      openTemplate(button.closest('[data-event-id]').dataset.eventId);
    });
  });
  root.querySelector('.tranter-modal-close').addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal();
      const lightbox = root.querySelector('.tranter-lightbox');
      if (lightbox) lightbox.classList.remove('is-open');
    }
  });

  root.querySelectorAll('.tranter-countdown').forEach(function (element) {
    const target = parseInt(element.dataset.target, 10) * 1000;
    function tick() {
      let difference = Math.max(0, target - Date.now());
      if (difference === 0) {
        element.innerHTML = '<strong>HAPPENING TODAY</strong>';
        return;
      }
      const values = {
        days: Math.floor(difference / 86400000),
        hours: Math.floor(difference / 3600000) % 24,
        minutes: Math.floor(difference / 60000) % 60,
        seconds: Math.floor(difference / 1000) % 60
      };
      Object.keys(values).forEach(function (unit) {
        const node = element.querySelector('[data-unit="' + unit + '"]');
        if (node) node.textContent = String(values[unit]).padStart(2, '0');
      });
    }
    tick();
    window.setInterval(tick, 1000);
  });

  const filter = root.querySelector('[data-events-filter]');
  const results = root.querySelector('[data-events-results]');
  if (filter && results && window.TranterEvents) {
    filter.addEventListener('change', function () {
      results.classList.add('is-loading');
      const body = new URLSearchParams({
        action: 'tranter_filter_events', nonce: window.TranterEvents.nonce,
        status: filter.status.value, event_type: filter.event_type.value,
        partner: filter.partner.value, year: filter.year.value
      });
      fetch(window.TranterEvents.ajaxUrl, {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body
      }).then(function (response) { return response.json(); }).then(function (json) {
        if (!json.success) return;
        if (!json.data.events.length) {
          results.innerHTML = '<div class="tranter-empty"><h3>No events found matching your filters.</h3></div>';
          return;
        }
        results.innerHTML = json.data.events.map(function (event) {
          const image = event.image ? '<img src="' + event.image + '" alt="">' : '<div class="tranter-image-placeholder">TRANTER EVENTS</div>';
          return '<article class="tranter-event-card" data-event-id="' + event.id + '"><button class="tranter-card-trigger" type="button"><div class="tranter-card-image">' + image + '</div><div class="tranter-card-body"><span class="tranter-card-meta">' + event.type + ' · ' + event.date + '</span><h3>' + event.title + '</h3><p>' + event.short + '</p><span class="tranter-card-location">' + event.location + '</span><span class="tranter-card-link">View details →</span></div></button></article>';
        }).join('');
        results.querySelectorAll('.tranter-card-trigger').forEach(function (button) {
          button.addEventListener('click', function () { openTemplate(button.closest('[data-event-id]').dataset.eventId); });
        });
      }).finally(function () { results.classList.remove('is-loading'); });
    });
  }

  root.querySelectorAll('[data-lightbox]').forEach(function (button) {
    button.addEventListener('click', function () {
      const lightbox = root.querySelector('.tranter-lightbox');
      lightbox.querySelector('img').src = button.dataset.lightbox;
      lightbox.classList.add('is-open');
    });
  });
  root.querySelector('.tranter-lightbox > button').addEventListener('click', function () {
    root.querySelector('.tranter-lightbox').classList.remove('is-open');
  });

  root.querySelectorAll('[data-video]').forEach(function (button) {
    button.addEventListener('click', function () {
      let url = button.dataset.video;
      if (url.indexOf('youtube.com/watch?v=') !== -1) url = url.replace('watch?v=', 'embed/');
      else if (url.indexOf('youtu.be/') !== -1) url = url.replace('youtu.be/', 'www.youtube.com/embed/');
      else if (url.indexOf('vimeo.com/') !== -1) url = url.replace('vimeo.com/', 'player.vimeo.com/video/');
      modalContent.innerHTML = '<div class="tranter-video-frame"><iframe src="' + url + '" title="Event video" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0"></iframe></div>';
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    });
  });
});
