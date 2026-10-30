const passwordInput = document.querySelector("#login-password");

document.querySelector("#toggle-password").addEventListener("click", (event) => {
  const isHidden = passwordInput.type === "password";
  passwordInput.type = isHidden ? "text" : "password";
  event.currentTarget.textContent = isHidden ? "Ocultar" : "Mostrar";
  event.currentTarget.setAttribute("aria-label", isHidden ? "Ocultar contraseña" : "Mostrar contraseña");
});
