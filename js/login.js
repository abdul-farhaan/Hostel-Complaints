// Login page: checks for empty fields before the form is sent to process_login.php.

let loginForm = document.getElementById("login-form");
let loginMessage = document.getElementById("login-message");

function validateLogin(event) {
  let loginId = $("#login-id").val().trim();
  let password = $("#password").val();

  if (loginId === "") {
    event.preventDefault();
    loginMessage.textContent = "Please enter your admission number or staff ID.";
  } else if (password === "") {
    event.preventDefault();
    loginMessage.textContent = "Please enter your password.";
  }
}

loginForm.addEventListener("submit", validateLogin);
