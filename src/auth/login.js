document.addEventListener("DOMContentLoaded", () => {
  const roleInput = document.querySelector("#accountRole");
  const roleTabs = document.querySelectorAll(".role-tab");
  const loginForm = document.querySelector("#loginForm");
  const emailInput = document.querySelector("#email");
  const passwordInput = document.querySelector("#password");
  const togglePassword = document.querySelector("#togglePassword");
  const demoEmails = {
    donor: "donor@food.com",
    receiver: "receiver@food.com",
  };

  roleTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      roleTabs.forEach((item) => {
        item.classList.remove("active");
        item.setAttribute("aria-selected", "false");
      });

      tab.classList.add("active");
      tab.setAttribute("aria-selected", "true");
      roleInput.value = tab.dataset.role;
      emailInput.value = demoEmails[tab.dataset.role];
    });
  });

  togglePassword.addEventListener("click", () => {
    const isPasswordVisible = passwordInput.type === "text";

    passwordInput.type = isPasswordVisible ? "password" : "text";
    togglePassword.classList.toggle("showing", !isPasswordVisible);
    togglePassword.setAttribute("aria-label", isPasswordVisible ? "Show password" : "Hide password");
  });

  loginForm.addEventListener("submit", (event) => {
    event.preventDefault();
    window.location.href = `../roles/${roleInput.value}/dashboard.html`;
  });
});
