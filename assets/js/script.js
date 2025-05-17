document.addEventListener('DOMContentLoaded', () => {
  // Login Form
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(loginForm);
      const response = await fetch('process_login.php', {
        method: 'POST',
        body: formData
      });
      const result = await response.json();

      const msg = document.getElementById('login-message');
      if (result.success) {
        msg.style.color = 'green';
        msg.textContent = result.message;
        setTimeout(() => window.location.href = 'dashboard.php', 1000);
      } else {
        msg.style.color = 'red';
        msg.textContent = result.message;
      }
    });
  }

  // Register Form
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(registerForm);
      const response = await fetch('process_register.php', {
        method: 'POST',
        body: formData
      });
      const result = await response.json();

      const msg = document.getElementById('register-message');
      if (result.success) {
        msg.style.color = 'green';
        msg.textContent = result.message;
        registerForm.reset();
      } else {
        msg.style.color = 'red';
        msg.textContent = result.message;
      }
    });
  }

  // Update Profile Form
const updateForm = document.getElementById('updateForm');
if (updateForm) {
  updateForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(updateForm);
    const msg = document.getElementById('update-message');

    // show spinner
    Swal.fire({title:'Saving…',allowOutsideClick:false,didOpen:Swal.showLoading});

    try {
      const res  = await fetch('process_update_profile.php', { method:'POST', body:formData });
      const raw  = await res.text();                   // might be JSON *or* PHP warning HTML
      console.log('Raw response:', raw);

      let data;
      try {                                            // attempt JSON parse
        data = JSON.parse(raw);
      } catch (parseErr) {
        throw new Error('Server returned non-JSON. Check PHP error log.');
      }

      Swal.close();

      if (data.success) {
        Swal.fire({
          icon:'success',
          title:'Profile saved!',
          showDenyButton:true,
          confirmButtonText:'Return Home',
          denyButtonText:'Continue Editing'
        }).then(r=>{
          if (r.isConfirmed) window.location.href='dashboard.php';
          else               location.reload();
        });
      } else {
        Swal.fire('Oops…', data.message || 'Upload failed' ,'error');
      }

    } catch (err) {
      console.error(err);
      Swal.close();
      Swal.fire('Error','Unexpected server error. See console.','error');
    }
  });

}

});

