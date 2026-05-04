const roleButtons = document.querySelectorAll(".role-btn");
const navButtons = document.querySelectorAll(".nav-item");
const panels = document.querySelectorAll(".panel");
const facultyOnly = document.querySelectorAll(".faculty-only");
const studentOnly = document.querySelectorAll(".student-only");
const profileRole = document.getElementById("profileRole");
const heroEyebrow = document.getElementById("heroEyebrow");
const heroTitle = document.getElementById("heroTitle");
const heroCopy = document.getElementById("heroCopy");
const classHeading = document.getElementById("classHeading");
const classCount = document.getElementById("classCount");
const classGrid = document.getElementById("classGrid");
const deadlineList = document.getElementById("deadlineList");
const modalBackdrop = document.getElementById("modalBackdrop");
const peopleGrid = document.getElementById("peopleGrid");
const heroCard = document.getElementById("heroCard");
const openModalBtn = document.getElementById("openModalBtn");
const closeModalBtn = document.getElementById("closeModalBtn");
const generateCodeBtn = document.getElementById("generateCodeBtn");
const generatedCode = document.getElementById("generatedCode");
const classForm = document.getElementById("classForm");
const joinClassBtn = document.getElementById("joinClassBtn");
const joinCodeInput = document.getElementById("joinCode");  
const announcementList = document.getElementById("announcementList");
const manualAnnouncementBtn = document.getElementById("manualAnnouncementBtn");
const authOverlay = document.getElementById("authOverlay");
const profileForm = document.getElementById("profileForm");
const passwordForm = document.getElementById("passwordForm");
const loginForm = document.getElementById("loginForm");
const loginEmail = document.getElementById("loginEmail");
const loginPassword = document.getElementById("loginPassword");
const loginError = document.getElementById("loginError");
const loginPanel = document.getElementById("loginPanel");
const registerPanel = document.getElementById("registerPanel");
const showRegisterBtn = document.getElementById("showRegisterBtn");
const showLoginBtn = document.getElementById("showLoginBtn");
const registerName = document.getElementById("registerName");
const registerEmail = document.getElementById("registerEmail");
const registerPassword = document.getElementById("registerPassword");
const registerPasswordConfirm = document.getElementById("registerPasswordConfirm");
const registerError = document.getElementById("registerError");
let authenticatedUser = null;

let appData = null;

const rotatingAnnouncements = [
    { tag: "Reminder", title: "Quiz 2 opens at 6:00 PM", message: "Please review modules 3 and 4 before the quiz window closes on April 14." },
    { tag: "New", title: "Room change for Friday session", message: "Web Standards will meet in Lab 402 this Friday due to projector maintenance." },
    { tag: "Update", title: "Assignment deadline extended", message: "The reflection paper deadline has been moved to April 18." },
    { tag: "Important", title: "Midterm schedule posted", message: "Check the calendar for your midterm exam dates and times." }
];

// API request function
async function apiRequest(action, options = {}) {
    const url = new URL("api.php", window.location.href);
    url.searchParams.set("action", action);

    const response = await fetch(url.toString(), {
        credentials: "same-origin",
        ...options
    });

    const raw = await response.text();
    let data = {};
    try {
        data = raw ? JSON.parse(raw) : {};
    } catch {
        return {
            success: false,
            message: response.ok
                ? "Invalid server response."
                : (raw.slice(0, 120) || "Server error (check PHP / MySQL).")
        };
    }

    if (!response.ok) {
        return {
            success: false,
            message: data.message || "Request failed."
        };
    }

    return data;
}

// Load data from database
async function loadAppData() {
    try {
        const result = await apiRequest("getUserData");
        if (result.success) {
            appData = result.data;
            updateUIWithDatabaseData();
        }
    } catch (error) {
        console.error("Failed to load app data:", error);
    }
}

// Update UI with database data
function updateUIWithDatabaseData() {
    if (!appData) return;

    const user = appData.user;
    const classes = appData.classes;
    const deadlines = appData.deadlines;

    // Update profile
    updateProfile(user, user.role);

    // Update classes
    renderClassesFromDB(classes);

    // Update deadlines (student only)
    if (user.role === 'student' && deadlines.length > 0) {
        renderDeadlinesFromDB(deadlines);
    }

    // Update announcements
    if (appData.announcements.length > 0) {
        renderAnnouncementsFromDB(appData.announcements);
    }

    // Update calendar (student only)
    if (user.role === 'student' && appData.calendar) {
        renderCalendarFromDB(appData.calendar);
    }

    // Update instructors (student only)
    if (user.role === 'student' && appData.instructors) {
        renderInstructorsFromDB(appData.instructors);
    }
}

// Render classes from database
function renderClassesFromDB(classes) {
    if (!classes || classes.length === 0) {
        classGrid.innerHTML = '<p>No classes found.</p>';
        classCount.textContent = '0 classes';
        return;
    }

    classGrid.innerHTML = classes.map((item) => {
        const metaOne = item.status === 'active' ? 
            (authenticatedUser.role === 'faculty' ? `${item.student_count || 0} students` : 'Active') : 
            item.status;
        const metaTwo = `Code: ${item.class_code}`;

        return `
            <article class="class-card">
                <div class="class-card-top">
                    <p class="course-code">${item.course_code}</p>
                    <span class="status-pill">${item.status}</span>
                </div>
                <h4>${item.title}</h4>
                <p>${item.section}</p>
                <div class="card-meta">
                    <span>${metaOne}</span>
                    <span>${metaTwo}</span>
                </div>
            </article>
        `;
    }).join("");

    classCount.textContent = `${classes.length} ${authenticatedUser.role === 'faculty' ? 'active' : 'enrolled'} classes`;
}

// Render deadlines from database
function renderDeadlinesFromDB(deadlines) {
    if (!deadlines || deadlines.length === 0) {
        deadlineList.innerHTML = '<li>No upcoming deadlines.</li>';
        return;
    }

    deadlineList.innerHTML = deadlines.map((item) => {
        const dueDate = new Date(item.due_date);
        const formattedDate = dueDate.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });

        return `
            <li>
                <span>${item.title}</span>
                <strong>${formattedDate}</strong>
            </li>
        `;
    }).join("");
}

// Render announcements from database
function renderAnnouncementsFromDB(announcements) {
    if (!announcements || announcements.length === 0) return;

    // Clear existing announcements
    announcementList.innerHTML = '';

    // Add announcements
    announcements.forEach((item) => {
        const card = document.createElement("article");
        card.className = "info-card";
        card.innerHTML = `
            <p class="announcement-tag">${item.tag}</p>
            <h4>${item.title}</h4>
            <p>${item.message}</p>
        `;
        announcementList.prepend(card);
    });
}

// Render calendar from database
function renderCalendarFromDB(calendarData) {
    if (!calendarGrid || !calendarData || calendarData.length === 0) return;

    calendarGrid.innerHTML = calendarData.map((day) => {
        const dayClass = day.has_deadline ? 'calendar-day due' : 'calendar-day';
        const titleHtml = day.has_deadline ? `<small>${day.title}</small>` : '';

        return `<div class="${dayClass}">${day.day}${titleHtml}</div>`;
    }).join("");
}

// Render instructors from database
function renderInstructorsFromDB(instructors) {
    if (!peopleGrid || !instructors || instructors.length === 0) return;

    const instructorsHtml = instructors.map((instructor) => `
        <article class="person-card">
            <h4>Instructor</h4>
            <p><strong>${instructor.name}</strong></p>
            <p>${instructor.title}</p>
            <p>${instructor.email}</p>
        </article>
    `).join("");

    // Replace the first instructor card with dynamic content
    const firstInstructorCard = peopleGrid.querySelector('.person-card:first-child');
    if (firstInstructorCard) {
        firstInstructorCard.outerHTML = instructorsHtml;
    }
}

function renderClasses(classes) {
    classGrid.innerHTML = classes.map((item) => `
        <article class="class-card">
            <div class="class-card-top">
                <p class="course-code">${item.courseCode}</p>
                <span class="status-pill">${item.status}</span>
            </div>
            <h4>${item.title}</h4>
            <p>${item.section}</p>
            <div class="card-meta">
                <span>${item.metaOne}</span>
                <span>${item.metaTwo}</span>
            </div>
        </article>
    `).join("");
}

function renderDeadlines(items) {
    deadlineList.innerHTML = items.map((item) => `
        <li>
            <span>${item.title}</span>
            <strong>${item.date}</strong>
        </li>
    `).join("");
}

function addAnnouncement(item) {
    const card = document.createElement("article");
    card.className = "info-card";
    card.innerHTML = `
        <p class="announcement-tag">${item.tag}</p>
        <h4>${item.title}</h4>
        <p>${item.message}</p>
    `;
    announcementList.prepend(card);
}

function setActivePanel(panelId) {
    navButtons.forEach((button) => {
        button.classList.toggle("active", button.dataset.panel === panelId);
    });

    panels.forEach((panel) => {
        panel.classList.toggle("active", panel.id === panelId);
    });

    // Show/hide hero-card based on panel
    if (heroCard) {
        heroCard.classList.toggle("hidden", panelId !== "overview");
    }
}

function updateProfile(user, role) {
    const profileName = document.querySelector(".profile-name");

    profileName.textContent = user ? user.name : "Guest User";

    if (role === "faculty") {
        profileRole.textContent = "Faculty Coordinator";
        heroEyebrow.textContent = "Faculty Home";
        heroTitle.textContent = "Manage classes, students, and deadlines from one clean workspace.";
        heroCopy.textContent = "Create classes, share generated codes, and monitor activity at a glance.";
        classHeading.textContent = "Subjects You Teach";
    } else {
        profileRole.textContent = "Student";
        heroEyebrow.textContent = "Student Home";
        heroTitle.textContent = "Track your progress and stay updated with your classes.";
        heroCopy.textContent = "Join classes, view announcements, and manage your coursework.";
        classHeading.textContent = "Your Classes";
    }
}

function updateRole(role, user = null) {
    document.body.dataset.role = role;

    roleButtons.forEach((button) => {
        button.classList.toggle("active", button.dataset.role === role);
    });

    facultyOnly.forEach((element) => {
        element.classList.toggle("hidden", role !== "faculty");
    });

    studentOnly.forEach((element) => {
        element.classList.toggle("hidden", role !== "student");
    });

    updateProfile(user, role);
    setActivePanel("overview");
}

function randomCode(length = 6) {
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    let result = "";

    for (let i = 0; i < length; i += 1) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }

    return result;
}

function showDashboard() {
    authOverlay.classList.add("hidden");
    document.body.classList.remove("auth-locked");
}

function showLogin() {
    authOverlay.classList.remove("hidden");
    document.body.classList.add("auth-locked");
    showLoginPanel();
}

function setLoginError(message) {
    if (!message) {
        loginError.textContent = "";
        loginError.classList.add("hidden");
        return;
    }

    loginError.textContent = message;
    loginError.classList.remove("hidden");
}

function setRegisterError(message) {
    if (!message) {
        registerError.textContent = "";
        registerError.classList.add("hidden");
        return;
    }

    registerError.textContent = message;
    registerError.classList.remove("hidden");
}

function showLoginPanel() {
    loginPanel.classList.remove("hidden");
    registerPanel.classList.add("hidden");
    setRegisterError("");
}

function showRegisterPanel() {
    loginPanel.classList.add("hidden");
    registerPanel.classList.remove("hidden");
    setLoginError("");
}

if (showRegisterBtn) {
    showRegisterBtn.addEventListener("click", () => showRegisterPanel());
}
if (showLoginBtn) {
    showLoginBtn.addEventListener("click", () => showLoginPanel());
}

function bindAuthForms() {
    if (loginForm) {
        loginForm.addEventListener("submit", async (event) => {
            event.preventDefault();

            try {
                setLoginError("");

                const email = loginEmail.value.trim().toLowerCase();
                const password = loginPassword.value;
                const result = await apiRequest("login", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ email, password })
                });

                if (!result.success) {
                    setLoginError(result.message);
                    return;
                }

                authenticatedUser = result.user;
                loginForm.reset();
                showDashboard();
                updateRole(result.user.role, result.user);
                lockRoleSwitcher(result.user.role);
            } catch (error) {
                console.error("Login submit failed:", error);
                setLoginError("Login failed. Please try again.");
            }
        });
    }

    const registerFormEl = document.getElementById("registerForm");
    if (registerFormEl) {
        registerFormEl.addEventListener("submit", async (event) => {
            event.preventDefault();

            try {
                setRegisterError("");

                const roleInput = registerFormEl.querySelector('input[name="registerRole"]:checked');
                const role = roleInput ? roleInput.value : "student";
                const name = registerName.value.trim();
                const email = registerEmail.value.trim().toLowerCase();
                const password = registerPassword.value;
                const password_confirm = registerPasswordConfirm.value;

                const result = await apiRequest("register", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        name,
                        email,
                        password,
                        password_confirm,
                        role
                    })
                });

                if (!result.success) {
                    setRegisterError(result.message);
                    return;
                }

                authenticatedUser = result.user;
                registerFormEl.reset();
                showLoginPanel();
                showDashboard();
                updateRole(result.user.role, result.user);
                lockRoleSwitcher(result.user.role);
            } catch (error) {
                console.error("Register failed:", error);
                setRegisterError("Could not create account. Please try again.");
            }
        });
    }
}

bindAuthForms();

async function startAppSession() {
    try {
        const result = await apiRequest("session");
        const currentUser = result.user;

        if (currentUser) {
            authenticatedUser = currentUser;
            showDashboard();
            updateRole(currentUser.role, currentUser);
            lockRoleSwitcher(currentUser.role);
            return;
        }

        showLogin();
    } catch (error) {
        console.error("App startup failed:", error);
        setLoginError("Unable to start the login system.");
        showLogin();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Set authenticated user from PHP session data
    const userRole = document.body.dataset.role;
    const userId = document.body.dataset.userId;
    if (userRole) {
        authenticatedUser = {
            id: userId ? parseInt(userId) : null,
            role: userRole,
            name: document.querySelector('.profile-name')?.textContent || 'User'
        };
    }
    
    // Load data from database
    loadAppData();
});

roleButtons.forEach((button) => {
    button.addEventListener("click", () => {
        try {
            if (!authenticatedUser) {
                return;
            }

            if (button.dataset.role !== authenticatedUser.role) {
                return;
            }

            updateRole(authenticatedUser.role, authenticatedUser);
        } catch (error) {
            console.error("Role update failed:", error);
        }
    });
});

navButtons.forEach((button) => {
    button.addEventListener("click", () => {
        if (button.classList.contains("hidden")) {
            return;
        }

        setActivePanel(button.dataset.panel);
    });
});

if (openModalBtn) {
    openModalBtn.addEventListener("click", () => {
        modalBackdrop.classList.remove("hidden");
    });
}

if (closeModalBtn) {
    closeModalBtn.addEventListener("click", () => {
        modalBackdrop.classList.add("hidden");
    });
}

if (modalBackdrop) {
    modalBackdrop.addEventListener("click", (event) => {
        if (event.target === modalBackdrop) {
            modalBackdrop.classList.add("hidden");
        }
    });
}

if (generateCodeBtn) {
    generateCodeBtn.addEventListener("click", async () => {
        console.log("Generate code button clicked");
        try {
            const result = await apiRequest("generateCode", {
                method: "POST"
            });
            
            console.log("Generate code result:", result);
            
            if (result.success) {
                generatedCode.textContent = result.code;
            } else {
                console.error("Generate code failed:", result);
                window.alert("Failed to generate class code: " + (result.message || "Unknown error"));
            }
        } catch (error) {
            console.error("Generate code error:", error);
            window.alert("Error generating class code.");
        }
    });
} else {
    console.error("generateCodeBtn element not found");
}

if (profileForm) {
    profileForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        try {
            const name = document.getElementById("profileName").value.trim();
            const email = document.getElementById("profileEmail").value.trim();

            const result = await apiRequest("updateProfile", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    name: name,
                    email: email
                })
            });

            if (result.success) {
                // Update session data
                authenticatedUser.name = name;
                authenticatedUser.email = email;

                // Update UI
                document.querySelector('.profile-name').textContent = name;
                document.querySelector('.avatar').textContent = name.substring(0, 2).toUpperCase();
                document.getElementById('profileInitials').value = name.substring(0, 2).toUpperCase();

                window.alert("Profile updated successfully!");
            } else {
                console.error("Update profile failed:", result);
                window.alert("Failed to update profile: " + (result.message || "Unknown error"));
            }
        } catch (error) {
            console.error("Update profile error:", error);
            window.alert("Error updating profile.");
        }
    });
}

if (passwordForm) {
    passwordForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        try {
            const currentPassword = document.getElementById("currentPassword").value;
            const newPassword = document.getElementById("newPassword").value;
            const confirmPassword = document.getElementById("confirmPassword").value;

            if (newPassword !== confirmPassword) {
                window.alert("New passwords do not match.");
                return;
            }

            const result = await apiRequest("changePassword", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            });

            if (result.success) {
                passwordForm.reset();
                window.alert("Password changed successfully!");
            } else {
                console.error("Change password failed:", result);
                window.alert("Failed to change password: " + (result.message || "Unknown error"));
            }
        } catch (error) {
            console.error("Change password error:", error);
            window.alert("Error changing password.");
        }
    });
}

if (classForm) {
    classForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        try {
            // Get form data directly from inputs
            const subjectName = document.getElementById("subjectName").value.trim();
            const sectionName = document.getElementById("sectionName").value.trim();
            const courseCode = document.getElementById("courseCode").value.trim();
            
            // Generate code if not already generated
            let classCode = generatedCode.textContent;
            if (classCode === "------" || !classCode) {
                const codeResult = await apiRequest("generateCode", {
                    method: "POST"
                });
                if (codeResult.success) {
                    classCode = codeResult.code;
                } else {
                    window.alert("Failed to generate class code.");
                    return;
                }
            }

            const createClassResult = await apiRequest("createClass", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    courseCode: courseCode,
                    title: subjectName,
                    section: sectionName,
                    class_code: classCode
                })
            });
            
            if (createClassResult.success) {
                // Refresh data from server
                await loadAppData();
                classForm.reset();
                generatedCode.textContent = "------";
                modalBackdrop.classList.add("hidden");
                window.alert("Class created successfully!");
            } else {
                console.error("Create class failed:", createClassResult);
                window.alert("Failed to create class: " + (createClassResult.message || "Unknown error"));
            }
        } catch (error) {
            console.error("Create class error:", error);
            window.alert("Error creating class.");
        }
    });
}


if (joinClassBtn) {
    joinClassBtn.addEventListener("click", async () => {
        try {
            const code = joinCodeInput.value.trim().toUpperCase();

            if (!code) {
                window.alert("Please enter a class code first.");
                return;
            }

            const result = await apiRequest("joinClass", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    class_code: code
                })
            });
            
            if (result.success) {
                // Refresh data from server
                await loadAppData();
                joinCodeInput.value = "";
                window.alert("Class joined successfully!");
            } else {
                console.error("Join class failed:", result);
                window.alert("Failed to join class: " + (result.message || "Unknown error"));
            }
        } catch (error) {
            console.error("Join class error:", error);
            window.alert("Error joining class.");
        }
    });
}


/*

HYS MAY LOGOUTBTN NA MAY ONLICK LOGOUT PA SA INDEX HALION MO NA INI

*/
// const logoutBtn = document.querySelector(".logout-btn");
// if (logoutBtn) {
//     logoutBtn.addEventListener("click", async () => {
//         try {
//             const result = await apiRequest("logout", {
//                 method: "POST"
//             });
            
//             if (result.success) {
//                 // Clear local data and redirect to login page
//                 authenticatedUser = null;
//                 appData = null;
//                 window.location.href = 'pages/loginPage.php';
//             } else {
//                 console.error("Logout API error:", result);
//                 window.alert("Logout failed: " + (result.message || "Unknown error"));
//             }
//         } catch (error) {
//             console.error("Logout failed:", error);
//             window.alert("Logout error. Please try again.");
//         }
//     });
// }

if (manualAnnouncementBtn) {
    manualAnnouncementBtn.addEventListener("click", () => {
    const item = rotatingAnnouncements[Math.floor(Math.random() * rotatingAnnouncements.length)];
    addAnnouncement(item);
    });
}

setInterval(() => {
    const item = rotatingAnnouncements[Math.floor(Math.random() * rotatingAnnouncements.length)];
    addAnnouncement(item);
}, 12000);

function lockRoleSwitcher(role) {
    roleButtons.forEach((button) => {
        const isUserRole = button.dataset.role === role;
        button.classList.toggle("active", isUserRole);
        button.disabled = !isUserRole;
    });
}

startAppSession();
