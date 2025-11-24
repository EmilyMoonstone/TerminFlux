document.addEventListener('DOMContentLoaded', () => {
    const list = document.querySelector('#slot-list');
    const addBtn = document.querySelector('#add-slot');
    if (!list || !addBtn) return;

    function addRow(value = '') {
        const row = document.createElement('div');
        row.className = 'slot-row';
        row.innerHTML = `
            <input type="text" name="slot_label[]" placeholder="z.B. 12.06. 18:00" required value="${value}">
            <button type="button" class="remove-slot" aria-label="Slot entfernen">&times;</button>
        `;
        row.querySelector('.remove-slot').addEventListener('click', () => {
            if (list.children.length > 1) {
                row.remove();
            }
        });
        list.appendChild(row);
    }

    addBtn.addEventListener('click', () => addRow());

    // ensure at least one slot row
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
