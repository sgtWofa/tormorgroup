/**put the javascript for your plugin here**/

document.getElementById('loadRegister').addEventListener('click', function () {
    const date = document.getElementById('date').value;
    if (!date) {
        showAlert('Please select a date!', 'danger');
        return;
    }

    fetch(`fetch_users.php?date=${date}`)
        .then(response => response.json())
        .then(data => {
            populateTable('unmarkedTable', data.unmarked, true);
            populateTable('markedTable', data.marked, false);
            document.getElementById('attendanceSection').style.display = 'block';
        })
        .catch(err => showAlert('Failed to load data!', 'danger'));
});

function populateTable(tableId, users, isUnmarked) {
    const tableBody = document.getElementById(tableId).querySelector('tbody');
    tableBody.innerHTML = '';

    users.forEach(user => {
        const row = document.createElement('tr');

        row.innerHTML = `
            <td>${user.name}</td>
            <td>${isUnmarked ? `<input type="time" value="${user.time || ''}">` : user.time}</td>
            <td>${isUnmarked ? `<input type="text" placeholder="Enter remarks">` : user.remarks}</td>
            <td>${isUnmarked ? `<button class="btn btn-success" onclick="markAttendance(${user.id}, this)">Mark</button>` : ''}</td>
        `;

        tableBody.appendChild(row);
    });
}

function markAttendance(userId, button) {
    const row = button.closest('tr');
    const time = row.querySelector('input[type="time"]').value;
    const remarks = row.querySelector('input[type="text"]').value;

    if (!time) {
        showAlert('Please provide a time!', 'warning');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userId, time, remarks })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Attendance marked!', 'success');
                document.getElementById('loadRegister').click();
            } else {
                showAlert('Failed to mark attendance!', 'danger');
            }
        });
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    document.body.prepend(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}
