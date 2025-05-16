document.querySelectorAll('.admin-update-form').forEach(form => {
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    fetch('process_admin_update_user.php', {
      method: 'POST',
      body: new FormData(form)
    })
    .then(res => res.json())
    .then(data => alert(data.message));
  });
});

function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;
  
    fetch('process_admin_delete_user.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'id=' + encodeURIComponent(userId)
    })
    .then(res => res.json())
    .then(data => {
      alert(data.message);
      if (data.success) {
        location.reload(); // Refresh table
      }
    });
  }

  function suspendUser(userId) {
    const until = prompt("Suspend user until what date? (YYYY-MM-DD HH:MM:SS)");
    if (!until) return;
  
    const formData = new FormData();
    formData.append('id', userId);
    formData.append('until', until);
  
    fetch('process_suspend_user.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      alert(data.message);
      if (data.success) location.reload();
    });
  }

  function liftSuspension(userId) {
    if (!confirm("Are you sure you want to lift the suspension?")) return;
  
    const formData = new FormData();
    formData.append('id', userId);
  
    fetch('process_lift_suspension.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      alert(data.message);
      if (data.success) location.reload();
    });
  }
  
  
  
  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('userTable');
    const rows = table.querySelectorAll('tr'); // includes all rows
  
    searchInput.addEventListener('input', function () {
      const searchValue = this.value.toLowerCase();
  
      rows.forEach((row, index) => {
        if (index === 0) return; // skip header row
  
        const nameInput = row.querySelector('td:nth-child(2) input');
        const emailInput = row.querySelector('td:nth-child(3) input');
        const name = nameInput ? nameInput.value.toLowerCase() : '';
        const email = emailInput ? emailInput.value.toLowerCase() : '';
  
        if (name.includes(searchValue) || email.includes(searchValue)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
  