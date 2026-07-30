(function () {
  'use strict';

  function initGauges() {
    document.querySelectorAll('.gauge').forEach(function (gauge) {
      var range = gauge.querySelector('input[type=range]');
      var readout = gauge.querySelector('.gauge__readout .value');
      var readoutWrap = gauge.querySelector('.gauge__readout');
      if (!range) return;

      var safeMin = parseFloat(gauge.dataset.safeMin);
      var safeMax = parseFloat(gauge.dataset.safeMax);
      var altSafeMin = gauge.dataset.altSafeMin !== undefined ? parseFloat(gauge.dataset.altSafeMin) : null;
      var altSafeMax = gauge.dataset.altSafeMax !== undefined ? parseFloat(gauge.dataset.altSafeMax) : null;

      function update() {
        var v = parseFloat(range.value);
        if (readout) readout.textContent = range.value;
        var inRange = (v >= safeMin && v <= safeMax) || (altSafeMin !== null && v >= altSafeMin && v <= altSafeMax);
        if (readoutWrap) readoutWrap.classList.toggle('out-of-range', !inRange);
      }

      range.addEventListener('input', update);
      update();
    });
  }

  function initDynamicRows() {
    document.querySelectorAll('[data-dynamic-rows]').forEach(function (block) {
      var list = block.querySelector('.rows');
      var addBtn = block.querySelector('.add-row-btn');
      var template = block.querySelector('template');
      if (!list || !addBtn || !template) return;

      function addRow() {
        var frag = template.content.cloneNode(true);
        var removeBtn = frag.querySelector('.row-item__remove');
        if (removeBtn) {
          removeBtn.addEventListener('click', function () {
            removeBtn.closest('.row-item').remove();
          });
        }
        list.appendChild(frag);
      }

      addBtn.addEventListener('click', addRow);
      if (list.children.length === 0) addRow();
    });
  }

  function initDynamicRowsSerialize() {
    var form = document.querySelector('form[data-serialize-rows]');
    if (!form) return;
    form.addEventListener('submit', function () {
      document.querySelectorAll('[data-dynamic-rows]').forEach(function (block) {
        var fieldKey = block.dataset.fieldKey;
        var hidden = form.querySelector('input[name="' + fieldKey + '"]');
        var rows = [];
        block.querySelectorAll('.row-item').forEach(function (rowEl) {
          var row = {};
          rowEl.querySelectorAll('[data-col]').forEach(function (input) {
            row[input.dataset.col] = input.value;
          });
          if (Object.values(row).some(function (v) { return v !== ''; })) rows.push(row);
        });
        if (hidden) hidden.value = JSON.stringify(rows);
      });
    });
  }

  function setSvgHidden(el, hide) {
    // SVGElement doesn't reflect the `.hidden` IDL property to the attribute
    // the way HTMLElement does, so toggle the attribute directly.
    if (hide) {
      el.setAttribute('hidden', '');
    } else {
      el.removeAttribute('hidden');
    }
  }

  function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
      var input = document.getElementById(btn.dataset.target);
      var eyeIcon = btn.querySelector('.icon-eye');
      var eyeOffIcon = btn.querySelector('.icon-eye-off');
      if (!input) return;

      btn.addEventListener('click', function () {
        var nowVisible = input.type === 'password';
        input.type = nowVisible ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(nowVisible));
        btn.setAttribute('aria-label', nowVisible ? 'הסתר סיסמה' : 'הצג סיסמה');
        if (eyeIcon) setSvgHidden(eyeIcon, nowVisible);
        if (eyeOffIcon) setSvgHidden(eyeOffIcon, !nowVisible);
      });
    });
  }

  function formatElapsed(ms) {
    var totalMinutes = Math.floor(ms / 60000);
    var hours = Math.floor(totalMinutes / 60);
    var minutes = totalMinutes % 60;
    if (hours > 0) return hours + ' שעות ו-' + minutes + ' דקות';
    return minutes + ' דקות';
  }

  function initElapsedTimers() {
    var els = document.querySelectorAll('[data-elapsed-since]');
    if (!els.length) return;

    function tick() {
      var now = Date.now();
      els.forEach(function (el) {
        var since = Date.parse(el.dataset.elapsedSince);
        if (isNaN(since)) return;
        el.textContent = formatElapsed(Math.max(0, now - since));
      });
    }

    tick();
    setInterval(tick, 30000);
  }

  function initVoiceInput() {
    var buttons = document.querySelectorAll('.mic-btn');
    if (!buttons.length) return;

    var SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognitionCtor) {
      // No browser support (e.g. Safari) -- hide rather than show a dead button.
      // iOS/iPadOS users still get voice input via the keyboard's own dictation key.
      buttons.forEach(function (btn) { btn.remove(); });
      return;
    }

    buttons.forEach(function (btn) {
      var textarea = document.getElementById(btn.dataset.target);
      if (!textarea) return;

      var recognition = null;
      var listening = false;

      btn.addEventListener('click', function () {
        if (listening) {
          if (recognition) recognition.stop();
          return;
        }

        recognition = new SpeechRecognitionCtor();
        recognition.lang = 'he-IL';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.addEventListener('start', function () {
          listening = true;
          btn.classList.add('mic-btn--active');
          btn.setAttribute('aria-label', 'עצירת הקלטה');
        });
        recognition.addEventListener('result', function (e) {
          var transcript = e.results[0][0].transcript;
          var sep = textarea.value && !/\s$/.test(textarea.value) ? ' ' : '';
          textarea.value += sep + transcript;
        });
        recognition.addEventListener('end', function () {
          listening = false;
          btn.classList.remove('mic-btn--active');
          btn.setAttribute('aria-label', 'הקלטה קולית');
        });
        recognition.addEventListener('error', function () {
          listening = false;
          btn.classList.remove('mic-btn--active');
          btn.setAttribute('aria-label', 'הקלטה קולית');
        });

        recognition.start();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initGauges();
    initDynamicRows();
    initDynamicRowsSerialize();
    initPasswordToggles();
    initElapsedTimers();
    initVoiceInput();
  });
})();
