// /public/modules/auth/login.js
const API = "/crmfms/api/auth/auth.php";
const msg = document.getElementById("loginMsg");

// Auto-fill credentials if coming from test page
document.addEventListener('DOMContentLoaded', function() {
  const testEmail = localStorage.getItem('testEmail');
  const testPassword = localStorage.getItem('testPassword');
  
  if (testEmail && testPassword) {
    document.getElementById('email').value = testEmail;
    document.getElementById('password').value = testPassword;
    // Clear the stored credentials
    localStorage.removeItem('testEmail');
    localStorage.removeItem('testPassword');
  }
});

document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  msg.textContent = "Logging in...";

  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value.trim();
  const remember = document.getElementById("rememberMe").checked;

  try {
    const res = await fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ email, password })
    });
    const data = await res.json();

    if (data.success) {
      msg.className = "mt-3 text-success text-center small";
      msg.textContent = data.message || "Login successful";

      const store = remember ? localStorage : sessionStorage;
      store.setItem("user", JSON.stringify(data.user));
      store.setItem("isAuthenticated", "true");

      // Redirect based on user roles
      if (data.user.roles && data.user.roles.includes('admin')) {
        window.location.href = "/crmfms/public/modules/admin/admin.html";
      } else {
        window.location.href = "/crmfms/public/modules/dashboard/index.html";
      }
    } else {
      msg.className = "mt-3 text-danger text-center small";
      msg.textContent = data.message || "Invalid login.";
    }
  } catch (err) {
    console.error(err);
    msg.className = "mt-3 text-danger text-center small";
    msg.textContent = "Server error.";
  }
});
