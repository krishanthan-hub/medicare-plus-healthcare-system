function loadAppointments(patientId) {
    const select = document.getElementById('appointmentSelect');
    select.innerHTML = '<option value="">Loading...</option>';

    if (!patientId) {
        select.innerHTML = '<option value="">-- Select patient first --</option>';
        return;
    }

    fetch(`get_patient_appointments.php?patient_id=${patientId}`)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">-- Select appointment --</option>';
            if (data.length === 0) {
                select.innerHTML = '<option value="">No appointments found</option>';
                return;
            }
            data.forEach(apt => {
                const opt = document.createElement('option');
                opt.value = apt.appointment_id;
                opt.textContent = `${apt.appointment_date} at ${apt.appointment_time} (${apt.status})`;
                select.appendChild(opt);
            });
        })
        .catch(() => {
            select.innerHTML = '<option value="">Failed to load</option>';
        });
}