document.getElementById("loginForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    try {
        const response = await fetch("functions/login.php", {
            method: "POST",
            body: formData
        });

        const text = await response.text();  // read raw response
        let result;
        try {
            result = JSON.parse(text);
        } catch (err) {
            console.error("Server returned invalid JSON:", text);
            alert("Login server error. Check logs.");
            return;
        }

        if (result.success) {
              window.location.href = result.redirect || 'dashboard.php';
        } else {
            const errorBox = document.getElementById("errorBox");
            errorBox.textContent = result.message || "Login failed";
            errorBox.style.display = "block";
        }
    } catch (err) {
        console.error("Request failed", err);
        alert("Network error. Try again.");
    }
});