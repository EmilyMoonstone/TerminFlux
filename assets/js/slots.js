document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('#slot-list');
    const addBtn = document.querySelector('#add-slot');
    const rangeBtn = document.querySelector('#apply-range');
    const timesBtn = document.querySelector('#apply-times');
    const rangeStart = document.querySelector('#range-start');
    const rangeEnd = document.querySelector('#range-end');
    const timesInput = document.querySelector('#time-templates');
    const weekdayInputs = Array.from(document.querySelectorAll('[name="weekday_filter[]"]'));

    if (!list || !addBtn) return;

    function rowExists(date, time, note) {
        const rows = Array.from(list.querySelectorAll('.slot-row'));
        return rows.some(row => {
            const d = row.querySelector('input[name="slot_date[]"]').value;
            const t = row.querySelector('input[name="slot_time[]"]').value;
            const n = row.querySelector('input[name="slot_note[]"]').value;
            return d === date && t === time && n === note;
        });
    }

    function addRow({ date = '', time = '', note = '' } = {}) {
        const row = document.createElement('div');
        row.className = 'slot-row';
        row.innerHTML = `
            <label class="slot-field">Datum<br><input type="date" name="slot_date[]" required value="${date}"></label>
            <label class="slot-field">Uhrzeit<br><input type="time" name="slot_time[]" value="${time}" placeholder="z.B. 18:00"></label>
            <label class="slot-field">Notiz (optional)<br><input type="text" name="slot_note[]" value="${note}" placeholder="z.B. nur online"></label>
            <button type="button" class="remove-slot" aria-label="Slot entfernen">&times;</button>
        `;
        row.querySelector('.remove-slot').addEventListener('click', () => {
            if (list.children.length > 1) {
                row.remove();
            }
        });
        list.appendChild(row);
    }

    function getSelectedWeekdays() {
        const selected = weekdayInputs.filter(cb => cb.checked).map(cb => parseInt(cb.value, 10));
        return selected.length ? new Set(selected) : null; // null = alle
    }

    function parseTimes(input) {
        return input.split(/[,\n]/).map(t => t.trim()).filter(Boolean);
    }

    function addRangeSlots() {
        const startVal = rangeStart?.value;
        const endVal = rangeEnd?.value;
        const times = parseTimes(timesInput?.value || '');
        const weekdaySet = getSelectedWeekdays();

        if (!startVal && !endVal && times.length) {
            // Keine Datumsrange: Zeiten auf alle vorhandenen Tage anwenden
            const dates = Array.from(new Set(Array.from(list.querySelectorAll('input[name="slot_date[]"]')).map(i => i.value).filter(Boolean)));
            dates.forEach(date => {
                const weekday = new Date(date + 'T00:00:00').getDay();
                if (weekdaySet && !weekdaySet.has(weekday)) return;
                times.forEach(time => {
                    if (!rowExists(date, time, '')) {
                        addRow({ date, time });
                    }
                });
            });
            return;
        }

        if (!startVal || !endVal) {
            alert('Bitte Start- und Enddatum auswählen.');
            return;
        }

        const startDate = new Date(startVal + 'T00:00:00');
        const endDate = new Date(endVal + 'T00:00:00');
        if (isNaN(startDate) || isNaN(endDate) || startDate > endDate) {
            alert('Datumsbereich prüfen.');
            return;
        }

        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            const isoDate = d.toISOString().slice(0, 10);
            const weekday = d.getDay();
            if (weekdaySet && !weekdaySet.has(weekday)) {
                continue;
            }
            if (times.length === 0) {
                if (!rowExists(isoDate, '', '')) {
                    addRow({ date: isoDate });
                }
            } else {
                times.forEach(time => {
                    if (!rowExists(isoDate, time, '')) {
                        addRow({ date: isoDate, time });
                    }
                });
            }
        }
    }

    function applyTimesToExistingDates() {
        const times = parseTimes(timesInput?.value || '');
        if (!times.length) {
            alert('Bitte mindestens eine Uhrzeit eingeben.');
            return;
        }
        const weekdaySet = getSelectedWeekdays();
        const dates = Array.from(new Set(Array.from(list.querySelectorAll('input[name="slot_date[]"]')).map(i => i.value).filter(Boolean)));
        if (!dates.length) {
            alert('Keine vorhandenen Tage gefunden. Bitte zuerst Tage hinzufügen.');
            return;
        }
        dates.forEach(date => {
            const weekday = new Date(date + 'T00:00:00').getDay();
            if (weekdaySet && !weekdaySet.has(weekday)) return;
            times.forEach(time => {
                if (!rowExists(date, time, '')) {
                    addRow({ date, time });
                }
            });
        });
    }

    addBtn.addEventListener('click', () => addRow());
    rangeBtn?.addEventListener('click', addRangeSlots);
    timesBtn?.addEventListener('click', applyTimesToExistingDates);

    if (list.children.length === 0) {
        addRow();
    } else {
        list.querySelectorAll('.remove-slot').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const row = e.target.closest('.slot-row');
                if (row && list.children.length > 1) {
                    row.remove();
                }
            });
        });
    }
});
