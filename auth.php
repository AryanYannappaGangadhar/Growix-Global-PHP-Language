<?php header("Content-type: application/javascript"); ?>
document.addEventListener("DOMContentLoaded", () => {
    
    // Login Logic
    let loginTempToken = null;
    const loginForm = document.getElementById("login-form");
    const loginOtpForm = document.getElementById("login-otp-form");

    if (loginForm) {
        loginForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const username = document.getElementById("login-username").value;
            const password = document.getElementById("login-password").value;
            
            try {
                const res = await apiRequest("/auth/login", "POST", { username, password });
                
                if (!res) throw new Error("Empty response from server");

                // Normal Login Success
                if (res.token) {
                    localStorage.setItem("auth_token", res.token);
                }

                showMessage("login-message", "Login successful! Redirecting...", "success");
                
                setTimeout(() => {
                    window.location.href = "dashboard.php";
                }, 1000);
            } catch (err) {
                showMessage("login-message", err.message, "error");
            }
        });
    }

    // Removed loginOtpForm listener since 2FA is removed

    // Signup Logic
    let signupUsername = null;
    const signupForm = document.getElementById("signup-form");
    
    if (signupForm) {
        signupForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const full_name = document.getElementById("signup-fullname").value;
            const email = document.getElementById("signup-email").value;
            const username = document.getElementById("signup-username").value;
            // const phoneNumber = document.getElementById("signup-phone").value; // Removed
            const password = document.getElementById("signup-password").value;
            const confirm = document.getElementById("signup-confirm").value;

            if (password !== confirm) {
                showMessage("signup-message", "Passwords do not match", "error");
                return;
            }

            try {
                // Removed phoneNumber from payload
                const res = await apiRequest("/auth/signup", "POST", { fullName: full_name, email, username, password, confirmPassword: confirm });
                
                if (!res) throw new Error("Empty response from server");

                showMessage("signup-message", "Account created! Redirecting to login...", "success");
                setTimeout(() => {
                    window.location.href = "login.php";
                }, 1500);
            } catch (err) {
                showMessage("signup-message", err.message, "error");
            }
        });
    }

    // Removed signupOtpForm listener since OTP is removed

    // Forgot Password Form
    const forgotForm = document.getElementById("forgot-form");
    if (forgotForm) {
        forgotForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const email = document.getElementById("forgot-email").value;

            try {
                await apiRequest("/auth/forgot-password", "POST", { email });
                showMessage("forgot-message", "If an account exists, a reset link has been sent.", "success");
            } catch (err) {
                showMessage("forgot-message", err.message, "error");
            }
        });
    }

    // Reset Password Form
    const resetForm = document.getElementById("reset-form");
    if (resetForm) {
        resetForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const username = document.getElementById("reset-username").value;
            const password = document.getElementById("reset-password").value;
            const confirm = document.getElementById("reset-confirm").value;
            
            if (password !== confirm) {
                showMessage("reset-message", "Passwords do not match", "error");
                return;
            }

            try {
                await apiRequest("/auth/reset-password", "POST", { username, password, confirmPassword: confirm });
                showMessage("reset-message", "Password updated! Redirecting to login...", "success");
                setTimeout(() => {
                    window.location.href = "login.php";
                }, 2000);
            } catch (err) {
                showMessage("reset-message", err.message, "error");
            }
        });
    }
});
