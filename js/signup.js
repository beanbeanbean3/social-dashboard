document.getElementById("signupForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch("functions/signup.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        const errorBox = document.getElementById("errorBox");
        const successBox = document.getElementById("successBox");

        errorBox.style.display = "none";
        successBox.style.display = "none";

        if (result.success) {
            successBox.textContent = "Account created successfully! Redirecting...";
            successBox.style.display = "block";

            setTimeout(() => {
                window.location.href = "index.html";
            }, 1500);

        } else {
            errorBox.textContent = result.message;
            errorBox.style.display = "block";
        }

    } catch (err) {
        alert("Signup failed. Try again.");
        console.error(err);
    }
});