document.addEventListener("DOMContentLoaded", function () {

    const autogenerateBtn = document.getElementById("autogenerateBtn");
    const autogenerateModal = document.getElementById("autogenerateModal");

    const closeAutoreportModal =
        document.getElementById("closeAutoreportModal");

    const autoreportCancelBtn =
        document.getElementById("autoreportCancelBtn");

    const autoreportConfirmBtn =
        document.getElementById("autoreportConfirmBtn");

    const startDateInput =
        document.getElementById("autoreportStartDate");

    const timeInput =
        document.getElementById("autoreportTime");

    const timezoneInput =
        document.getElementById("autoreportTimezone");


    /*
     * Detect browser timezone
     */
    const browserTimezone =
        Intl.DateTimeFormat().resolvedOptions().timeZone;

    timezoneInput.value = browserTimezone || "UTC";


    /*
     * Open AutoReport modal
     */
    autogenerateBtn.addEventListener("click", function () {

        // Default start date to today
        if (!startDateInput.value) {
            const today = new Date();

            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, "0");
            const day = String(today.getDate()).padStart(2, "0");

            startDateInput.value = `${year}-${month}-${day}`;
        }

        // Default time to current time
        if (!timeInput.value) {
            const now = new Date();

            const hours = String(now.getHours()).padStart(2, "0");
            const minutes = String(now.getMinutes()).padStart(2, "0");

            timeInput.value = `${hours}:${minutes}`;
        }

        autogenerateModal.style.display = "block";
    });


    /*
     * Close modal using X
     */
    closeAutoreportModal.addEventListener("click", function () {
        autogenerateModal.style.display = "none";
    });


    /*
     * Cancel AutoReport
     */
    autoreportCancelBtn.addEventListener("click", function () {

        const confirmed = confirm(
            "Do you want to cancel auto gen?"
        );

        if (confirmed) {
            autogenerateModal.style.display = "none";
        }
    });


    /*
     * Confirm AutoReport
     *
     * Phase 1:
     * We only display the selected settings.
     *
     * No schedule is saved yet.
     */
autoreportConfirmBtn.addEventListener("click", async function () {

    const startDate = startDateInput.value;
    const time = timeInput.value;
    const timezone = timezoneInput.value;

    if (!startDate) {
        alert("Please select a start date.");
        return;
    }

    if (!time) {
        alert("Please select a time.");
        return;
    }

    if (!timezone) {
        alert("Timezone could not be detected.");
        return;
    }

    /*
     * CURRENT_PROJECT should already be available
     * from dashboard.php.
     */
    if (
        typeof window.CURRENT_PROJECT === "undefined" ||
        !window.CURRENT_PROJECT
    ) {
        alert("Current project could not be determined.");
        return;
    }

    /*
     * Prevent double-clicks while saving.
     */
    autoreportConfirmBtn.disabled = true;
    autoreportConfirmBtn.textContent = "Saving...";

    try {

        const formData = new FormData();

        formData.append(
            "action",
            "save"
        );

        formData.append(
            "project",
            window.CURRENT_PROJECT
        );

        formData.append(
            "start_date",
            startDate
        );

        formData.append(
            "time",
            time
        );

        formData.append(
            "timezone",
            timezone
        );


        const response =
            await fetch(
                "functions/autoreport.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const result =
            await response.json();


        if (!result.success) {

            throw new Error(
                result.message ||
                "Unable to save autoreport schedule."
            );
        }


        alert(
            "AutoReport scheduled successfully!\n\n" +
            "Start Date: " +
            result.schedule.start_date +
            "\n" +
            "Time: " +
            result.schedule.time +
            "\n" +
            "Timezone: " +
            result.schedule.timezone +
            "\n" +
            "Frequency: Weekly"
        );


        autogenerateModal.style.display = "none";


    } catch (error) {

        console.error(
            "AutoReport save error:",
            error
        );

        alert(
            "Unable to save AutoReport schedule.\n\n" +
            error.message
        );


    } finally {

        autoreportConfirmBtn.disabled = false;
        autoreportConfirmBtn.textContent = "Confirm";
    }

});

    /*
     * Close modal when clicking outside the modal content
     */
    window.addEventListener("click", function (event) {

        if (event.target === autogenerateModal) {
            autogenerateModal.style.display = "none";
        }

    });

});