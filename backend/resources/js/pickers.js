/*
 * Date and time pickers (DECISIONS D-74): flatpickr, themed in resources/css/app.css.
 * Markup: <input data-picker="date|time" ...>. The submitted value stays machine-readable
 * (date "Y-m-d", time "H:i"); dates are shown to people as "06 Oct 2026".
 */
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

const common = {
    allowInput: true,
    disableMobile: true, // same premium picker on phones instead of the browser default
    monthSelectorType: 'static',
};

export function initPickers(root = document) {
    root.querySelectorAll('input[data-picker="date"]:not(.flatpickr-input)').forEach((el) => {
        flatpickr(el, {
            ...common,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            altInputClass: el.className + ' cursor-pointer',
            minDate: el.dataset.min || null,
            maxDate: el.dataset.max || null,
            locale: { firstDayOfWeek: 1 },
            onReady: (_, __, fp) => {
                fp.altInput.setAttribute('placeholder', el.getAttribute('placeholder') || 'Select date');
                if (el.required) fp.altInput.required = true;
                addClearButton(fp);
            },
        });
    });

    root.querySelectorAll('input[data-picker="time"]:not(.flatpickr-input)').forEach((el) => {
        flatpickr(el, {
            ...common,
            enableTime: true,
            noCalendar: true,
            time_24hr: false,      // people see 3:45 PM …
            dateFormat: 'H:i',     // … the form still submits 15:45
            altInput: true,
            altFormat: 'h:i K',
            altInputClass: el.className + ' cursor-pointer',
            minuteIncrement: 1,
            onReady: (_, __, fp) => {
                fp.altInput.setAttribute('placeholder', 'Select time');
                if (el.required) fp.altInput.required = true;
            },
        });
    });
}

/** "Today" and "Clear" shortcuts under the calendar. */
function addClearButton(fp) {
    const bar = document.createElement('div');
    bar.className = 'ag-picker-actions';
    const today = document.createElement('button');
    today.type = 'button';
    today.textContent = 'Today';
    today.addEventListener('click', () => { fp.setDate(new Date(), true); fp.close(); });
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.textContent = 'Clear';
    clear.addEventListener('click', () => { fp.clear(); fp.close(); });
    bar.append(today, clear);
    fp.calendarContainer.appendChild(bar);
}
