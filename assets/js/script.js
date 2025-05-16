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
    console.log('Submitting form...');
    console.log('Sending to process_update_profile.php');

    const msg = document.getElementById('update-message');

    try {
      const response = await fetch('process_update_profile.php', {
        method: 'POST',
        body: formData
      });

      const text = await response.text();
      console.log('Raw response:', text);

      if (!text) {
        throw new Error('Empty response from server');
      }

      const result = JSON.parse(text);

      msg.textContent = result.message;
      msg.style.color = result.success ? 'green' : 'red';

      if (result.success) {
        setTimeout(() => location.reload(), 1000);
      }
    } catch (error) {
      console.error('Error processing response:', error);
      msg.textContent = 'Unexpected server error. Please check console.';
      msg.style.color = 'red';
    }
  });
}

});