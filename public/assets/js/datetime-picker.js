(() => {
  const isEnglish = (document.documentElement.lang || '').toLowerCase() === 'en';
  const monthNames = isEnglish
    ? ['January','February','March','April','May','June','July','August','September','October','November','December']
    : ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
  const week = isEnglish ? ['Mo','Tu','We','Th','Fr','Sa','Su'] : ['lu','ma','me','je','ve','sa','di'];
  const pad = n => String(n).padStart(2, '0');

  const parseNative = (value, type) => {
    if (!value) return null;
    if (type === 'date') {
      const m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      return m ? new Date(+m[1], +m[2]-1, +m[3], 0, 0) : null;
    }
    const m = value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
    return m ? new Date(+m[1], +m[2]-1, +m[3], +m[4], +m[5]) : null;
  };

  const parseDisplay = (value, type) => {
    const v = value.trim();
    if (!v) return null;
    let m;
    if (type === 'date') {
      m = v.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
      if (!m) return null;
      const d = new Date(+m[3], +m[2]-1, +m[1], 0, 0);
      return d.getFullYear() === +m[3] && d.getMonth() === +m[2]-1 && d.getDate() === +m[1] ? d : null;
    }
    m = v.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/);
    if (!m) return null;
    const h = m[4] === undefined ? 0 : +m[4];
    const min = m[5] === undefined ? 0 : +m[5];
    if (h > 23 || min > 59) return null;
    const d = new Date(+m[3], +m[2]-1, +m[1], h, min);
    return d.getFullYear() === +m[3] && d.getMonth() === +m[2]-1 && d.getDate() === +m[1] ? d : null;
  };

  const serialize = (d, type) => type === 'date'
    ? `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`
    : `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

  const display = (d, type) => {
    if (!d) return '';
    return type === 'date'
      ? `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()}`
      : `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  const placeholder = type => type === 'date' ? 'jj/mm/aaaa' : 'jj/mm/aaaa hh:mm';

  document.querySelectorAll('input[type="datetime-local"], input[type="date"]').forEach(input => {
    if (input.dataset.tfPickerReady === '1') return;
    input.dataset.tfPickerReady = '1';
    const required = input.required;
    const inputType = input.type;
    input.required = false;
    input.classList.add('tf-datetime-native');

    let selected = parseNative(input.value, inputType);
    let cursor = selected ? new Date(selected) : new Date();
    const initialNativeValue = () => input.value;

    const wrap = document.createElement('div');
    wrap.className = 'tf-datetime';
    const field = document.createElement('div');
    field.className = 'tf-datetime-field';
    const text = document.createElement('input');
    text.type = 'text';
    text.className = 'tf-datetime-text';
    text.autocomplete = 'off';
    text.inputMode = 'numeric';
    text.placeholder = placeholder(inputType);
    text.value = display(selected, inputType);
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'tf-datetime-trigger';
    trigger.setAttribute('aria-label', isEnglish ? 'Open calendar' : 'Ouvrir le calendrier');
    trigger.innerHTML = '<i class="fa-regular fa-calendar"></i>';
    field.append(text, trigger);

    const pop = document.createElement('div');
    pop.className = 'tf-datetime-popover';
    pop.innerHTML = `
      <div class="tf-calendar-head">
        <button type="button" data-prev aria-label="${isEnglish ? 'Previous month' : 'Mois précédent'}"><i class="fa-solid fa-chevron-left"></i></button>
        <div class="tf-calendar-title"></div>
        <button type="button" data-next aria-label="${isEnglish ? 'Next month' : 'Mois suivant'}"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
      <div class="tf-calendar-weekdays">${week.map(d => `<span>${d}</span>`).join('')}</div>
      <div class="tf-calendar-grid"></div>
      <div class="tf-calendar-time">
        <input type="number" min="0" max="23" step="1" data-hour aria-label="${isEnglish ? 'Hour' : 'Heure'}">
        <span>:</span>
        <input type="number" min="0" max="59" step="1" data-minute aria-label="${isEnglish ? 'Minute' : 'Minute'}">
      </div>
      <div class="tf-calendar-actions">
        <button type="button" data-clear>${isEnglish ? 'Clear' : 'Effacer'}</button>
        <button type="button" data-today>${isEnglish ? 'Today' : 'Aujourd’hui'}</button>
        <button type="button" class="primary" data-apply>${isEnglish ? 'Apply' : 'Appliquer'}</button>
      </div>`;

    input.parentNode.insertBefore(wrap, input);
    wrap.append(input, field, pop);

    const title = pop.querySelector('.tf-calendar-title');
    const grid = pop.querySelector('.tf-calendar-grid');
    const timeSection = pop.querySelector('.tf-calendar-time');
    const hour = pop.querySelector('[data-hour]');
    const minute = pop.querySelector('[data-minute]');
    if (inputType === 'date') timeSection.hidden = true;

    const refreshTime = () => {
      const d = selected || new Date();
      hour.value = pad(d.getHours());
      minute.value = pad(d.getMinutes());
    };

    const updateText = () => {
      text.value = display(selected, inputType);
      text.classList.toggle('is-empty', !selected);
    };

    const render = () => {
      title.textContent = `${monthNames[cursor.getMonth()]} ${cursor.getFullYear()}`;
      grid.innerHTML = '';
      const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
      const mondayIndex = (first.getDay() + 6) % 7;
      const start = new Date(cursor.getFullYear(), cursor.getMonth(), 1 - mondayIndex);
      const today = new Date();
      for (let i = 0; i < 42; i++) {
        const d = new Date(start); d.setDate(start.getDate() + i);
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'tf-calendar-day'; b.textContent = d.getDate();
        if (d.getMonth() !== cursor.getMonth()) b.classList.add('is-outside');
        if (d.toDateString() === today.toDateString()) b.classList.add('is-today');
        if (selected && d.toDateString() === selected.toDateString()) b.classList.add('is-selected');
        b.addEventListener('click', () => {
          const base = selected || new Date();
          selected = new Date(d.getFullYear(), d.getMonth(), d.getDate(), base.getHours(), base.getMinutes());
          cursor = new Date(d);
          refreshTime();
          updateText();
          render();
        });
        grid.appendChild(b);
      }
    };

    const commit = ({ close = true } = {}) => {
      const typed = text.value.trim();
      if (typed !== '') {
        const parsed = parseDisplay(typed, inputType);
        if (!parsed) {
          wrap.classList.add('tf-calendar-error');
          return false;
        }
        selected = parsed;
      } else if (typed === '') {
        selected = null;
      }
      if (selected && inputType !== 'date') {
        selected.setHours(Math.min(23, Math.max(0, +hour.value || selected.getHours())), Math.min(59, Math.max(0, +minute.value || selected.getMinutes())), 0, 0);
      }
      input.value = selected ? serialize(selected, inputType) : '';
      updateText();
      wrap.classList.remove('tf-calendar-error');
      if (close) wrap.classList.remove('is-open');
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    };

    const open = () => {
      document.querySelectorAll('.tf-datetime.is-open').forEach(x => {
        if (x !== wrap) x.dispatchEvent(new CustomEvent('tf-close-picker'));
      });
      const typed = parseDisplay(text.value, inputType);
      if (typed) { selected = typed; cursor = new Date(typed); }
      wrap.classList.add('is-open');
      refreshTime(); render();
    };

    text.addEventListener('focus', open);
    trigger.addEventListener('click', () => wrap.classList.contains('is-open') ? commit() : open());
    text.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); if (commit()) text.blur(); }
      if (e.key === 'Escape') {
        e.preventDefault();
        selected = parseNative(input.value, inputType);
        cursor = selected ? new Date(selected) : new Date();
        updateText();
        wrap.classList.remove('is-open','tf-calendar-error');
        text.blur();
      }
    });

    hour.addEventListener('input', () => {
      if (!selected) selected = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
      selected.setHours(Math.min(23, Math.max(0, +hour.value || 0)));
      updateText();
    });
    minute.addEventListener('input', () => {
      if (!selected) selected = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
      selected.setMinutes(Math.min(59, Math.max(0, +minute.value || 0)));
      updateText();
    });

    pop.querySelector('[data-prev]').addEventListener('click', () => { cursor.setMonth(cursor.getMonth()-1); render(); });
    pop.querySelector('[data-next]').addEventListener('click', () => { cursor.setMonth(cursor.getMonth()+1); render(); });
    pop.querySelector('[data-today]').addEventListener('click', () => { selected = new Date(); if (inputType === 'date') selected.setHours(0,0,0,0); cursor = new Date(selected); refreshTime(); updateText(); render(); });
    pop.querySelector('[data-clear]').addEventListener('click', () => { selected = null; text.value = ''; commit(); });
    pop.querySelector('[data-apply]').addEventListener('click', () => {
      if (!selected) { const now = new Date(); selected = new Date(cursor.getFullYear(), cursor.getMonth(), 1, inputType === 'date' ? 0 : now.getHours(), inputType === 'date' ? 0 : now.getMinutes()); updateText(); }
      commit();
    });

    wrap.addEventListener('tf-close-picker', () => { if (wrap.classList.contains('is-open')) commit(); });
    input.form?.addEventListener('submit', e => {
      if (!commit({ close: false }) || (required && !input.value)) {
        e.preventDefault(); wrap.classList.add('tf-calendar-error'); text.focus();
      }
    });

    // Keep browser/back-forward/autofill changes in sync.
    input.addEventListener('change', () => {
      const current = parseNative(input.value, inputType);
      if ((current && serialize(current, inputType) !== serialize(selected || current, inputType)) || (!current && initialNativeValue() === '')) {
        selected = current; updateText();
      }
    });
  });

  document.addEventListener('mousedown', e => {
    document.querySelectorAll('.tf-datetime.is-open').forEach(w => {
      if (!w.contains(e.target)) w.dispatchEvent(new CustomEvent('tf-close-picker'));
    });
  });
})();
