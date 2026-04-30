// js/booking.js

function updateDoctorInfo() {
    const select = document.getElementById('doctorSelect');
    const opt    = select.options[select.selectedIndex];
    const body   = document.getElementById('doctorInfoBody');

    if (!select.value) {
        body.innerHTML = '<div class="doc-placeholder"><i class="fas fa-user-md"></i>Select a doctor to see their details</div>';
        return;
    }

    const fmtTime = t => {
        if (!t) return '';
        const [h, m] = t.split(':');
        const hr = parseInt(h);
        return `${hr > 12 ? hr-12 : (hr || 12)}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
    };

    body.innerHTML = `
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-stethoscope"></i> Specialization:</div>
            <div class="doc-info-value">${opt.dataset.spec}</div>
        </div>
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-money-bill-wave"></i> Consultation Fee:</div>
            <div class="doc-info-value fee">LKR ${opt.dataset.fee}</div>
        </div>
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-calendar-alt"></i> Available Days:</div>
            <div class="doc-info-value">${opt.dataset.days}</div>
        </div>
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-clock"></i> Consultation Hours:</div>
            <div class="doc-info-value">${fmtTime(opt.dataset.start)} – ${fmtTime(opt.dataset.end)}</div>
        </div>
    `;
}

function getAvailableSlots() {
    const doctorId  = document.getElementById('doctorSelect').value;
    const date      = document.getElementById('appointmentDate').value;
    const container = document.getElementById('timeSlots');

    if (!doctorId || !date) {
        container.innerHTML = '<p style="color:#aaa; font-size:.85rem;">Please select a doctor and date first.</p>';
        return;
    }

    container.innerHTML = '<p style="color:#888; font-size:.85rem;"><i class="fas fa-spinner fa-spin"></i> Loading slots...</p>';

    fetch(`get_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`)
        .then(r => r.json())
        .then(slots => {
            if (!slots.length) {
                container.innerHTML = '<p style="color:#aaa; font-size:.85rem;">No slots available for this date.</p>';
                return;
            }
            const grid = document.createElement('div');
            grid.className = 'time-slots-grid';
            slots.forEach(slot => {
                const div = document.createElement('div');
                div.className = 'time-slot' + (slot.available ? '' : ' booked');
                div.textContent = slot.time;
                if (slot.available) {
                    div.onclick = function () {
                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('selected_time').value = slot.time;
                    };
                }
                grid.appendChild(div);
            });
            container.innerHTML = '';
            container.appendChild(grid);
        })
        .catch(() => {
            container.innerHTML = '<p style="color:#e74c3c; font-size:.85rem;">Failed to load slots. Please try again.</p>';
        });
}

window.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('doctorSelect').value) {
        updateDoctorInfo();
        if (document.getElementById('appointmentDate').value) {
            getAvailableSlots();
        }
    }
});