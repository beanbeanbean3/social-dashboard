const modal = document.getElementById("userModal");
const modalTitle = document.getElementById("modalTitle");
const projectModal = document.getElementById("projectModal");
const projectForm = document.getElementById("projectForm");
const projectModalTitle = document.getElementById("projectModalTitle");
const form = document.getElementById("userForm");
const saveBtn = document.getElementById("saveBtn");

const modeInput = form.querySelector("[name=mode]");
const idInput = form.querySelector("[name=id]");


window.addEventListener("DOMContentLoaded", function() {

    const activeTab = localStorage.getItem("activeTab");

    if (activeTab && document.getElementById(activeTab)) {

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.remove('active');
        });

        document.querySelector(`[data-tab="${activeTab}"]`).classList.add('active');
        document.getElementById(activeTab).classList.add('active');
    }

});


if (document.getElementById("userContainer")) {

/* =========================
   OPEN ADD USER
========================= */
    const addUserBtn = document.getElementById("addUserBtn");

    if (addUserBtn) {

        addUserBtn.onclick = () => {

            form.reset();
            modeInput.value = "add";
            idInput.value = "";

            modalTitle.textContent = "Add User";
            saveBtn.textContent = "Add";

            form.querySelectorAll("input, select").forEach(el => el.disabled = false);

            document.querySelectorAll(".password-group")
            .forEach(g => g.style.display = "none");

            document.getElementById("resetPasswordBtn").style.display = "none";

            modal.style.display = "block";
        };

    }


/* =========================
   EDIT USER
========================= */
    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.onclick = async function() {
            const id = this.dataset.id;

        // Fetch the latest data from JSON file
            async function fetchUser(id) {
                const res = await fetch(
            `json/${id}.json?nocache=${Date.now()}`
            );
                return await res.json();
            }
            const data = await fetchUser(id);

            modalTitle.textContent = "Edit User";
            saveBtn.style.display = "inline-block";

            form.querySelector("[name=first_name]").value = data.first_name;
            form.querySelector("[name=last_name]").value = data.last_name;
            form.querySelector("[name=email]").value = data.email;
            form.querySelector("[name=role]").value = data.role;

        // Show password field (empty, optional)
            const passField = form.querySelector("[name=password]");
            passField.style.display = "block";
            passField.value = "";

        // Enable fields
            form.querySelectorAll("input, select").forEach(el => el.disabled = false);

        // Set mode to edit and store the user ID
            form.querySelector("[name=mode]").value = "edit";
            form.dataset.id = id;


            document.getElementById("resetPasswordBtn").style.display = "inline-block";
            document.querySelectorAll(".password-group").forEach(g => g.style.display = "none");
            modal.style.display = "block";
        };
    });

/* =========================
   VIEW USER
========================= */
    document.querySelectorAll(".view-btn").forEach(btn => {
        btn.onclick = async function() {

            const id = this.dataset.id;

            async function fetchUser(id) {
                const res = await fetch(
            `json/${id}.json?nocache=${Date.now()}`
            );
                return await res.json();
            }
            const data = await fetchUser(id);

            modalTitle.textContent = "User Details";
            saveBtn.style.display = "none";

            form.querySelector("[name=first_name]").value = data.first_name;
            form.querySelector("[name=last_name]").value = data.last_name;
            form.querySelector("[name=email]").value = data.email;
            form.querySelector("[name=role]").value = data.role;

            document.querySelectorAll(".password-group").forEach(g => g.style.display = "none");

            form.querySelectorAll("input, select").forEach(el => el.disabled = true);



            if (data.logged_in && data.logged_in.length > 0) {
                [...data.logged_in].reverse().forEach(date => {
                    const p = document.createElement("p");
                    p.textContent = date;

                });
            } 

            document.getElementById("resetPasswordBtn").style.display = "none";
            modal.style.display = "block";
        };
    });


/* =========================
   SUBMIT (ADD OR EDIT)
========================= */
    form.addEventListener("submit", async function(e) {

        e.preventDefault();

    /*
     * Prevent double submit
     */

        if (saveBtn.disabled) return;

        saveBtn.disabled = true;

        const originalText =
        saveBtn.innerText;

        saveBtn.innerText = "Saving...";

        try {

            const mode =
            form.querySelector("[name=mode]").value;

            const formData =
            new FormData(this);

            formData.append("action", mode);

        /*
         * Include ID when editing
         */

            if (mode === "edit") {

                formData.append(
                    "id",
                    form.dataset.id
                    );

            }

            const res = await fetch(
                "functions/admin-user.php",
                {
                    method: "POST",
                    body: formData
                }
                );

            const result = await res.json();

            if (result.success) {

                location.reload();

            } else {

                alert(
                    result.message || "Error saving user."
                    );

            }

        } catch (err) {

            console.error(err);

            alert(
                "Failed to save user."
                );

        } finally {

            saveBtn.disabled = false;

            saveBtn.innerText =
            originalText;

        }

    });

}

/* =========================
   CLOSE MODAL
========================= */
document.getElementById("closeModal").onclick = () => {
    modal.style.display = "none";
    saveBtn.style.display = "inline-block";

    document.querySelector('.platform-tab[data-platform="all"]').click();
};


if (document.getElementById("userContainer")) {

    const deleteModal = document.getElementById("deleteModal");
    let deleteUserId = null;

    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.onclick = function() {
            deleteUserId = this.dataset.id;
            deleteModal.style.display = "block";
        };
    });

    document.getElementById("confirmDelete").onclick = async function() {

        const formData = new FormData();
        formData.append("action", "delete");
        formData.append("id", deleteUserId);

        const res = await fetch("functions/admin-user.php", {
            method: "POST",
            body: formData
        });

        const result = await res.json();

        if (result.success) {
            location.reload();
        }
    };

    document.getElementById("cancelDelete").onclick = function() {
        deleteModal.style.display = "none";
    };

    const users = document.querySelectorAll("#userContainer .user-card");
    const loadMoreBtn = document.getElementById("loadMoreBtn");

    let visibleCount = 5;

    function updateUserVisibility() {

        users.forEach((user, index) => {
            user.style.display = index < visibleCount ? "block" : "none";
        });

        if (visibleCount >= users.length) {
            loadMoreBtn.style.display = "none";
        }
    }

    updateUserVisibility();

    loadMoreBtn.onclick = function() {
        visibleCount += 5;
        updateUserVisibility();
    };

    document.getElementById("resetPasswordBtn").onclick = async function() {

        if (!confirm("Send new password to this user?")) return;

        const id = form.dataset.id;

        const formData = new FormData();
        formData.append("action", "reset_password");
        formData.append("id", id);

        const res = await fetch("functions/admin-user.php", {
            method: "POST",
            body: formData
        });

        const result = await res.json();

        if (result.success) {
            alert("New password sent successfully.");
        } else {
            alert("Error resetting password.");
        }
    };


}

document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', function () {

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.remove('active');
        });

        this.classList.add('active');

        const target = this.getAttribute('data-tab');
        document.getElementById(target).classList.add('active');

        // SAVE TAB
        localStorage.setItem("activeTab", target);
    });
});


// for project modals 

// add 

document.getElementById("addProjectBtn").onclick = () => {

    projectForm.reset();

    projectForm.querySelector("[name=mode]").value = "add";
    projectForm.querySelector("[name=id]").value = "";

    selectedViewers = [];
    selectedViewersContainer.innerHTML = "";
    viewerSelect.value = "";

    projectModalTitle.textContent = "Add Project";

    projectModal.style.display = "block";
};


// edit 
document.querySelectorAll(".edit-project").forEach(btn => {



    btn.onclick = async function() {

         // Enable all inputs
        projectForm.querySelectorAll("input, select").forEach(el => {
            el.disabled = false;
        });

        document.querySelector('.platform-tab[data-platform="all"]').click();

        const id = this.dataset.id;

        const res = await fetch(
    `Projects/${id}.json?nocache=${Date.now()}`
    );

        const data = await res.json();


        projectModalTitle.textContent = "Edit Project";

        projectForm.querySelector("[name=mode]").value = "edit";
        projectForm.querySelector("[name=id]").value = id;

        projectForm.querySelector("[name=company_name]").value = data.company_name;
        projectForm.querySelector("[name=client_name]").value = data.client_name;
        projectForm.querySelector("[name=email]").value = data.email;

        projectForm.querySelector("[name=fb_page_id]").value = data.keys.fb_page_id;
        projectForm.querySelector("[name=fb_token]").value = "********";
        projectForm.querySelector("[name=ig_id]").value = data.keys.ig_id;
        projectForm.querySelector("[name=ig_token]").value = "********";
        projectForm.querySelector("[name=tw_id]").value = data.keys.tw_id || "";
        projectForm.querySelector("[name=tw_token]").value = data.keys.tw_token ? "********" : "";
        projectForm.querySelector("[name=li_id]").value = data.keys.li_id || "";
        projectForm.querySelector("[name=li_token]").value = data.keys.li_token ? "********" : "";


       // Reset previous selections
        selectedViewers = [];
        selectedViewersContainer.innerHTML = "";
        viewerSelect.style.display = "block"; // <-- ensure it’s visible for edit

// Add existing viewers
        data.viewers.forEach(id => {

            const option = [...viewerSelect.options].find(opt => opt.value === id);
            if (!option) return;

            selectedViewers.push(id);

            const tag = document.createElement("div");
            tag.classList.add("viewer-tag");
            tag.dataset.id = id;

            tag.innerHTML = `
        ${option.text}
        <button type="button" class="remove-viewer">×</button>
        <input type="hidden" name="viewers[]" value="${id}">
            `;

            selectedViewersContainer.appendChild(tag);
        });

        projectModal.style.display = "block";
    };



});

// delete 

let deleteProjectId = null;

document.querySelectorAll(".delete-project").forEach(btn => {

    btn.onclick = function() {
        deleteProjectId = this.dataset.id;
        if (confirm("Delete this project?")) {
            deleteProject(deleteProjectId);
        }
    };

});

async function deleteProject(id) {

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);

    const res = await fetch("functions/admin-project.php", {
        method: "POST",
        body: formData
    });

    const result = await res.json();

    if (result.success) {
        location.reload();
    }
}


// submit 

projectForm.addEventListener("submit", async function(e){

    e.preventDefault();

    const projectSaveBtn =
    projectForm.querySelector('button[type="submit"]');

    /*
     * Prevent double submit
     */

    if (projectSaveBtn.disabled) return;

    projectSaveBtn.disabled = true;

    const originalText =
    projectSaveBtn.innerText;

    projectSaveBtn.innerText = "Saving...";

    try {

        const mode =
        projectForm.querySelector("[name=mode]").value;

        const formData =
        new FormData(this);

        formData.append("action", mode);

        const res = await fetch(
            "functions/admin-project.php",
            {
                method: "POST",
                body: formData
            }
            );

        const result = await res.json();

        console.log("FB TOKEN (DECRYPTED):", result.fb_token_plain);
        console.log("IG TOKEN (DECRYPTED):", result.ig_token_plain);

        if (result.success) {

            location.reload();

        } else {

            alert(result.message || "Error");

        }

    } catch (err) {

        console.error(err);

        alert("Failed to save project.");

    } finally {

        projectSaveBtn.disabled = false;

        projectSaveBtn.innerText =
        originalText;

    }

});


// load more 

const projects = document.querySelectorAll("#projectContainer .project-card");
const loadMoreProjects = document.getElementById("loadMoreProjects");

let visibleProjects = 5;

function updateProjectVisibility() {

    projects.forEach((proj, index) => {
        proj.style.display = index < visibleProjects ? "block" : "none";
    });

    if (visibleProjects >= projects.length) {
        loadMoreProjects.style.display = "none";
    }
}

updateProjectVisibility();

loadMoreProjects.onclick = function() {
    visibleProjects += 5;
    updateProjectVisibility();
};

const viewerSelect = document.getElementById("viewerSelect");
const selectedViewersContainer = document.getElementById("selectedViewers");

let selectedViewers = [];

/* =========================
   ADD VIEWER
========================= */

viewerSelect.addEventListener("change", function() {

    const id = this.value;
    const text = this.options[this.selectedIndex].text;

    if (!id) return;

    // Prevent duplicates
    if (selectedViewers.includes(id)) {
        this.value = "";
        return;
    }

    selectedViewers.push(id);

    const tag = document.createElement("div");
    tag.classList.add("viewer-tag");
    tag.dataset.id = id;

    tag.innerHTML = `
        ${text}
        <button type="button" class="remove-viewer">×</button>
        <input type="hidden" name="viewers[]" value="${id}">
    `;

    selectedViewersContainer.appendChild(tag);

    this.value = "";
});

selectedViewersContainer.addEventListener("click", function(e){

    if (e.target.classList.contains("remove-viewer")) {

        const tag = e.target.closest(".viewer-tag");
        const id = tag.dataset.id;

        selectedViewers = selectedViewers.filter(v => v !== id);

        tag.remove();
    }

});

document.getElementById("closeProjectModal").onclick = () => {

    projectModal.style.display = "none";

    projectForm.reset();
    selectedViewers = [];
    selectedViewersContainer.innerHTML = "";
    viewerSelect.value = "";
};

// modal view project 
document.querySelectorAll(".view-project").forEach(btn => {

    btn.onclick = async function() {

        const id = this.dataset.id;

        const res = await fetch(
    `Projects/${id}.json?nocache=${Date.now()}`
    );

        const data = await res.json();

        const accounts = data.accounts || {};

        const fb = accounts.facebook || {};
        const ig = accounts.instagram || {};
        const tw = accounts.twitter || {};
        const li = accounts.linkedin || {};

        projectModalTitle.textContent = "Project Details";

        projectForm.querySelector("[name=mode]").value = "view";

        projectForm.querySelector("[name=company_name]").value = data.company_name;
        projectForm.querySelector("[name=client_name]").value = data.client_name;
        projectForm.querySelector("[name=email]").value = data.email;

        projectForm.querySelector("[name=fb_page_id]").value = fb.page_id;
        projectForm.querySelector("[name=fb_token]").value = fb.token ;
        projectForm.querySelector("[name=ig_id]").value = ig.id;
        projectForm.querySelector("[name=ig_token]").value = ig.token;
        projectForm.querySelector("[name=tw_id]").value = tw.id;
        projectForm.querySelector("[name=tw_token]").value = tw.token;
        projectForm.querySelector("[name=li_id]").value = li.organization_id;
        projectForm.querySelector("[name=li_token]").value = li.token;


        document.querySelector('.platform-tab[data-platform="all"]').click();
        // Reset previous selections
        selectedViewers = [];
        selectedViewersContainer.innerHTML = "";

// Add existing viewers
        data.viewers.forEach(id => {

            const option = [...viewerSelect.options].find(opt => opt.value === id);
            if (!option) return;

            selectedViewers.push(id);

            const tag = document.createElement("div");
            tag.classList.add("viewer-tag");
            tag.dataset.id = id;

            tag.innerHTML = `
        ${option.text}
        <input type="hidden" name="viewers[]" value="${id}">
            `;

            selectedViewersContainer.appendChild(tag);
        });

        // Disable all inputs
        projectForm.querySelectorAll("input, select").forEach(el => {
            el.disabled = true;
        });


        document.getElementById("viewerSelect").style.display ="none";
        projectModal.style.display = "block";
    };

});

const platformTabs = document.querySelectorAll(".platform-tab");
const platformSections = document.querySelectorAll(".platform-section");

platformTabs.forEach(tab => {

    tab.addEventListener("click", function(){

        platformTabs.forEach(t => t.classList.remove("active"));
        this.classList.add("active");

        const platform = this.dataset.platform;

        platformSections.forEach(section => {

            if (
                platform === "all" ||
                section.dataset.platform === platform
            ) {
                section.style.display = "block";
            } else {
                section.style.display = "none";
            }

        });

    });

});