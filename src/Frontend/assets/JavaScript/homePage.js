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
const backToClassesBtn = document.getElementById("backToClassesBtn");
const classManagerCourse = document.getElementById("classManagerCourse");
const classManagerTitle = document.getElementById("classManagerTitle");
const classManagerSection = document.getElementById("classManagerSection");
const classManagerCode = document.getElementById("classManagerCode");
const classManagerStudentCount = document.getElementById("classManagerStudentCount");
const classManagerStudents = document.getElementById("classManagerStudents");
const classStreamList = document.getElementById("classStreamList");
const classStreamInput = document.getElementById("classStreamInput");
const classStreamPostBtn = document.getElementById("classStreamPostBtn");
const classAttachBtn = document.getElementById("classAttachBtn");
const classAttachmentInput = document.getElementById("classAttachmentInput");
const classAttachmentPreview = document.getElementById("classAttachmentPreview");
const createActivityBtn = document.getElementById("createActivityBtn");
const activityForm = document.getElementById("activityForm");
const cancelActivityBtn = document.getElementById("cancelActivityBtn");
const activityTitle = document.getElementById("activityTitle");
const activityType = document.getElementById("activityType");
const activityInstructions = document.getElementById("activityInstructions");
const activityDueDate = document.getElementById("activityDueDate");
const activityAttachBtn = document.getElementById("activityAttachBtn");
const activityAttachmentInput = document.getElementById("activityAttachmentInput");
const activityAttachmentPreview = document.getElementById("activityAttachmentPreview");
const classworkList = document.getElementById("classworkList");
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
let allStudents = [];
let subjectFilter = null;
let selectedClassId = null;
let expandedSubmissionActivityId = null;
let expandedStudentGradeKey = null;
const localClassActivities = {};

const rotatingAnnouncements = [
    { tag: "Reminder", title: "Quiz 2 opens at 6:00 PM", message: "Please review modules 3 and 4 before the quiz window closes on April 14." },
    { tag: "New", title: "Room change for Friday session", message: "Web Standards will meet in Lab 402 this Friday due to projector maintenance." },
    { tag: "Update", title: "Assignment deadline extended", message: "The reflection paper deadline has been moved to April 18." },
    { tag: "Important", title: "Midterm schedule posted", message: "Check the announcements for your midterm exam dates and times." }
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

    return data;
}

// Load data from database
async function loadAppData() {
    console.log('loadAppData() called');
    try {
        const result = await apiRequest("getUserData");
        console.log('API result:', result);
        if (result.success) {
            appData = result.data;
            console.log('App data:', appData);
            console.log('Classes in app data:', appData.classes);
            console.log('Classes count:', appData.classes ? appData.classes.length : 0);
            updateUIWithDatabaseData();
        } else {
            console.error('API failed - Response:', JSON.stringify(result));
            console.error('Response status:', result.status || 'unknown');
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
    const students = appData.students;

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

    // Update students (faculty only)
    if (user.role === 'faculty') {
        if (appData.students) {
            allStudents = appData.students;
            renderStudentsFromDB(appData.students, null);
        }
        if (appData.classes) {
            populateSubjectDropdowns(appData.classes);
        }
        if (selectedClassId) {
            renderClassManager(selectedClassId);
        }
    }

    if (user.role !== 'faculty' && selectedClassId) {
        renderClassManager(selectedClassId);
    }
}

// Populate subject dropdown from classes data
function populateSubjectDropdowns(classes) {
    console.log('populateSubjectDropdowns called with classes:', classes);

    // Find the dropdown element
    const dropdown = document.getElementById("subjectFilter");
    if (!dropdown) {
        console.error('subjectFilter element not found in DOM');
        return;
    }

    // Clear existing options
    dropdown.innerHTML = '';

    if (!classes || classes.length === 0) {
        console.log('No classes data provided');
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No subjects available';
        dropdown.appendChild(option);
        return;
    }

    // Extract unique subjects from classes data using title field
    const uniqueSubjects = [...new Set(classes.map(c => c.title).filter(Boolean))];
    console.log('Unique subjects extracted:', uniqueSubjects);

    if (uniqueSubjects.length === 0) {
        console.log('No valid subjects found in classes data');
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No subjects available';
        dropdown.appendChild(option);
        return;
    }

    // Sort subjects alphabetically
    uniqueSubjects.sort();

    // Add default "All Subjects" option
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'All Subjects';
    dropdown.appendChild(defaultOption);

    // Add each subject as an option
    uniqueSubjects.forEach(subject => {
        const option = document.createElement('option');
        option.value = subject;
        option.textContent = subject;
        dropdown.appendChild(option);
    });

    console.log('Dropdown populated successfully with', uniqueSubjects.length, 'subjects');
}

// Render classes from database
function escapeHTML(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatFileSize(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) {
        return `${size} B`;
    }
    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function renderAttachmentLinks(attachments = []) {
    if (!Array.isArray(attachments) || attachments.length === 0) {
        return '';
    }

    return `
        <div class="post-attachments">
            ${attachments.map((attachment) => `
                <a class="post-attachment-link" href="${escapeHTML(attachment.url || attachment.file_path || '#')}" target="_blank" rel="noopener">
                    <span>${escapeHTML(attachment.original_name || attachment.filename || 'Attachment')}</span>
                    <small>${escapeHTML(formatFileSize(attachment.file_size))}</small>
                </a>
            `).join("")}
        </div>
    `;
}

function formatGrade(grade) {
    if (!grade || grade.score === null || grade.max === null) {
        return "No grade";
    }

    const percent = Number(grade.percent ?? ((grade.score / grade.max) * 100));
    return `${Number(grade.score)} / ${Number(grade.max)} (${percent.toFixed(1)}%)`;
}

function getClassGradeSummary(classId, studentId = null) {
    const activities = getClassActivities(classId);
    let earned = 0;
    let possible = 0;
    let graded = 0;

    activities.forEach((activity) => {
        if (studentId) {
            const submission = (activity.submissions || []).find((item) => String(item.student_id) === String(studentId)) || activity.submission;
            if (submission?.grade) {
                earned += Number(submission.grade.score);
                possible += Number(submission.grade.max);
                graded += 1;
            }
            return;
        }

        if (activity.submission?.grade) {
            earned += Number(activity.submission.grade.score);
            possible += Number(activity.submission.grade.max);
            graded += 1;
            return;
        }

        (activity.submissions || []).forEach((submission) => {
            if (submission.grade) {
                earned += Number(submission.grade.score);
                possible += Number(submission.grade.max);
                graded += 1;
            }
        });
    });

    return {
        earned,
        possible,
        graded,
        percent: possible > 0 ? (earned / possible) * 100 : null
    };
}

function renderGradeSummaryText(summary) {
    if (!summary || summary.percent === null) {
        return "No grades yet";
    }

    return `${summary.percent.toFixed(1)}% (${summary.graded} graded)`;
}

function getStudentGradeRows(classId, studentId) {
    return getClassActivities(classId).map((activity) => {
        const submission = (activity.submissions || []).find((item) => String(item.student_id) === String(studentId));
        return {
            activity,
            submission
        };
    });
}

class ClassAttachmentManager {
    constructor({ button, input, preview }) {
        this.button = button;
        this.input = input;
        this.preview = preview;
        this.files = [];
        this.maxFileSize = 10 * 1024 * 1024;
        this.bindEvents();
        this.render();
    }

    bindEvents() {
        if (!this.button || !this.input || !this.preview) {
            return;
        }

        this.button.addEventListener("click", () => this.input.click());
        this.input.addEventListener("change", () => {
            this.attach(Array.from(this.input.files || []));
            this.input.value = "";
        });
        this.preview.addEventListener("click", (event) => {
            const detachButton = event.target.closest(".detach-attachment-btn");
            if (!detachButton) {
                return;
            }

            this.detach(Number(detachButton.dataset.fileIndex));
        });
    }

    attach(files) {
        const acceptedFiles = files.filter((file) => {
            if (file.size > this.maxFileSize) {
                window.alert(`${file.name} is larger than 10 MB.`);
                return false;
            }
            return true;
        });

        this.files.push(...acceptedFiles);
        this.render();
    }

    detach(index) {
        if (Number.isNaN(index)) {
            return;
        }

        this.files.splice(index, 1);
        this.render();
    }

    appendToFormData(formData) {
        this.files.forEach((file) => {
            formData.append("attachments[]", file);
        });
    }

    toAttachmentData() {
        return this.files.map((file) => ({
            filename: file.name,
            original_name: file.name,
            mime_type: file.type,
            file_size: file.size,
            url: URL.createObjectURL(file)
        }));
    }

    clear() {
        this.files = [];
        this.render();
    }

    hasFiles() {
        return this.files.length > 0;
    }

    render() {
        if (!this.preview) {
            return;
        }

        if (this.files.length === 0) {
            this.preview.classList.add("hidden");
            this.preview.innerHTML = "";
            return;
        }

        this.preview.classList.remove("hidden");
        this.preview.innerHTML = this.files.map((file, index) => `
            <div class="attachment-chip">
                <span>${escapeHTML(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
                <button class="ghost-btn compact-btn detach-attachment-btn" type="button" data-file-index="${index}">Detach</button>
            </div>
        `).join("");
    }
}

const classAttachmentManager = new ClassAttachmentManager({
    button: classAttachBtn,
    input: classAttachmentInput,
    preview: classAttachmentPreview
});

const activityAttachmentManager = new ClassAttachmentManager({
    button: activityAttachBtn,
    input: activityAttachmentInput,
    preview: activityAttachmentPreview
});

class ActivitySubmissionManager {
    constructor() {
        this.pendingAttachments = {};
        this.fileInput = document.createElement("input");
        this.fileInput.type = "file";
        this.fileInput.multiple = true;
        this.fileInput.className = "hidden";
        this.activeActivityId = null;
        document.body.appendChild(this.fileInput);
        this.bindEvents();
    }

    bindEvents() {
        this.fileInput.addEventListener("change", () => {
            if (!this.activeActivityId) {
                return;
            }

            this.attach(this.activeActivityId, Array.from(this.fileInput.files || []));
            this.fileInput.value = "";
        });

        document.addEventListener("click", (event) => {
            const attachButton = event.target.closest(".student-attach-activity-btn");
            if (attachButton) {
                this.activeActivityId = attachButton.dataset.activityId;
                this.fileInput.click();
                return;
            }

            const detachButton = event.target.closest(".student-detach-activity-btn");
            if (detachButton) {
                this.detach(detachButton.dataset.activityId, Number(detachButton.dataset.fileIndex));
                return;
            }

            const submitButton = event.target.closest(".student-submit-activity-btn");
            if (submitButton) {
                this.submit(submitButton.dataset.activityId);
            }
        });
    }

    attach(activityId, files) {
        if (!this.pendingAttachments[activityId]) {
            this.pendingAttachments[activityId] = [];
        }

        this.pendingAttachments[activityId].push(...files);
        renderClassManager(selectedClassId);
    }

    detach(activityId, index) {
        const files = this.pendingAttachments[activityId] || [];
        if (Number.isNaN(index)) {
            return;
        }

        files.splice(index, 1);
        renderClassManager(selectedClassId);
    }

    async submit(activityId) {
        const activity = this.findActivity(activityId);
        if (!activity) {
            return;
        }

        const files = this.pendingAttachments[activityId] || [];
        if (files.length === 0) {
            window.alert("Attach at least one file before submitting.");
            return;
        }

        const formData = new FormData();
        formData.append("activity_id", activityId);
        files.forEach((file) => {
            formData.append("attachments[]", file);
        });

        try {
            const result = await apiRequest("submitActivity", {
                method: "POST",
                body: formData
            });

            if (!result.success) {
                window.alert("Failed to submit activity: " + (result.message || "Unknown error"));
                return;
            }

            activity.submission = result.submission;
            this.pendingAttachments[activityId] = [];
            renderClassManager(selectedClassId);
        } catch (error) {
            console.error("Submit activity error:", error);
            window.alert("Error submitting activity.");
        }
    }

    findActivity(activityId) {
        const activities = getClassActivities(selectedClassId);
        return activities.find((activity) => String(activity.id) === String(activityId));
    }

    renderPending(activityId) {
        const files = this.pendingAttachments[activityId] || [];
        if (files.length === 0) {
            return "";
        }

        return `
            <div class="attachment-preview">
                ${files.map((file, index) => `
                    <div class="attachment-chip">
                        <span>${escapeHTML(file.name)}</span>
                        <small>${formatFileSize(file.size)}</small>
                        <button class="ghost-btn compact-btn student-detach-activity-btn" type="button" data-activity-id="${escapeHTML(activityId)}" data-file-index="${index}">Detach</button>
                    </div>
                `).join("")}
            </div>
        `;
    }
}

const activitySubmissionManager = new ActivitySubmissionManager();

function renderClassesFromDB(classes) {
    if (!classes || classes.length === 0) {
        classGrid.innerHTML = '<p>No classes found.</p>';
        classCount.textContent = '0 classes';
        return;
    }

    classGrid.innerHTML = classes.map((item) => {
        const metaOne = item.status === 'active' ?
            `${item.student_count || 0} students` :
            item.status;
        const metaTwo = `Code: ${item.class_code}`;

        return `
            <article class="class-card clickable-class-card" tabindex="0" role="button" data-class-id="${escapeHTML(item.id)}">
                <div class="class-card-top">
                    <p class="course-code">${escapeHTML(item.course_code)}</p>
                    <span class="status-pill">${escapeHTML(item.status)}</span>
                </div>
                <h4>${escapeHTML(item.title)}</h4>
                <p>${escapeHTML(item.section)}</p>
                <div class="card-meta">
                    <span>${escapeHTML(metaOne)}</span>
                    <span>${escapeHTML(metaTwo)}</span>
                </div>
            </article>
        `;
    }).join("");

    const userRole = authenticatedUser?.role || appData?.user?.role || 'student';
    classCount.textContent = `${classes.length} ${userRole === 'faculty' ? 'active' : 'enrolled'} classes`;
    bindClassCardClicks();

    if (selectedClassId) {
        renderClassManager(selectedClassId, userRole);
    }
}

function openClassFromCard(classId) {
    console.log('openClassFromCard called with classId:', classId);
    if (!classId) {
        console.log('No classId provided, returning');
        return;
    }

    selectedClassId = String(classId);
    const userRole = authenticatedUser?.role || appData?.user?.role || 'student';
    console.log('Calling renderClassManager with:', selectedClassId);
    renderClassManager(selectedClassId, userRole);
    setActivePanel("classManager");
}

function renderClassManager(classId, userRole = null) {
    if (!appData || !appData.classes) {
        return;
    }

    const selectedClass = appData.classes.find((item) => String(item.id) === String(classId));
    if (!selectedClass) {
        return;
    }

    const resolvedUserRole = userRole || authenticatedUser?.role || appData?.user?.role || 'student';
    const classStudents = (appData.students || []).filter((student) => String(student.class_id) === String(selectedClass.id));
    console.log('renderClassManager - appData.students:', appData.students);
    console.log('renderClassManager - selectedClass.id:', selectedClass.id);
    console.log('renderClassManager - classStudents:', classStudents);
    console.log('renderClassManager - classStudents.length:', classStudents.length);

    const classAnnouncements = (appData.announcements || []).filter((announcement) => {
        return announcement.class_id && String(announcement.class_id) === String(selectedClass.id);
    });
    const uniqueClassAnnouncements = classAnnouncements.filter((announcement, index, list) => {
        return list.findIndex((item) => String(item.id) === String(announcement.id)) === index;
    });

    classManagerCourse.textContent = selectedClass.course_code || "Classroom";
    classManagerTitle.textContent = selectedClass.title || "Classroom";
    classManagerSection.textContent = selectedClass.section || "";
    classManagerCode.textContent = selectedClass.class_code || "------";

    // Use the student_count from the class data instead of filtered students
    const studentCount = parseInt(selectedClass.student_count) || 0;
    classManagerStudentCount.textContent = `${studentCount} ${studentCount === 1 ? 'student' : 'students'} enrolled`;

    if (resolvedUserRole === 'student') {
        const ownGrade = getClassGradeSummary(selectedClass.id);
        classManagerStudentCount.textContent = "My Grade";
        classManagerStudents.innerHTML = `
            <div class="enrolled-students-box">
                <p>${escapeHTML(renderGradeSummaryText(ownGrade))}</p>
            </div>
        `;
    } else if (studentCount === 0) {
        classManagerStudents.innerHTML = '<p>No students enrolled yet.</p>';
    } else {
        classManagerStudents.innerHTML = `
            <div class="enrolled-students-box" data-class-id="${selectedClass.id}">
                <p>${studentCount} enrolled students</p>
            </div>
        `;
    }

    if (uniqueClassAnnouncements.length === 0) {
        classStreamList.innerHTML = `
            <article class="info-card">
                <p class="announcement-tag">Class Update</p>
                <h4>No posts yet</h4>
                <p>Announcements and reminders for this class will appear here.</p>
            </article>
        `;
    } else {
        classStreamList.innerHTML = uniqueClassAnnouncements.map((item) => `
            <article class="info-card" data-announcement-id="${item.id}">
                <p class="announcement-tag">${escapeHTML(item.tag || 'Update')}</p>
                <h4>${escapeHTML(item.title)}</h4>
                <p>${escapeHTML(item.message)}</p>
                ${renderAttachmentLinks(item.attachments)}
                ${resolvedUserRole === 'faculty' ?
                `<div class="post-actions">
                        <button class="ghost-btn compact-btn delete-post-btn" data-announcement-id="${item.id}">Delete</button>
                    </div>` :
                ''
            }
            </article>
        `).join("");
    }

    renderClassActivities(selectedClass.id, resolvedUserRole);
}

function getClassActivities(classId) {
    const savedActivities = (appData?.activities || []).filter((activity) => {
        return String(activity.class_id) === String(classId);
    });
    const localActivities = localClassActivities[classId] || [];
    const savedIds = new Set(savedActivities.map((activity) => String(activity.id)));
    const unsavedLocalActivities = localActivities.filter((activity) => !savedIds.has(String(activity.id)));
    return [...savedActivities, ...unsavedLocalActivities];
}

function renderFacultySubmissionPanel(activity) {
    const submissions = activity.submissions || [];
    const submittedCount = Number(activity.submission_count || 0);
    const studentCount = Number(activity.student_count || submissions.length || 0);
    const isExpanded = String(expandedSubmissionActivityId) === String(activity.id);

    return `
        <div class="faculty-submission-box">
            <div class="submission-summary">
                <strong>${submittedCount} submitted / ${studentCount} students</strong>
                <button class="ghost-btn compact-btn view-submissions-btn" type="button" data-activity-id="${escapeHTML(activity.id)}">
                    ${isExpanded ? 'Hide Submissions' : 'View Submissions'}
                </button>
            </div>
            ${isExpanded ? `
                <div class="submission-list">
                    ${submissions.length === 0 ? '<p>No enrolled students yet.</p>' : submissions.map((submission) => `
                        <div class="submission-row">
                            <div>
                                <strong>${escapeHTML(submission.student_name || 'Student')}</strong>
                                <small>${submission.submitted ? `Submitted ${escapeHTML(submission.submittedAt || submission.submitted_at || '')}` : 'Missing'}</small>
                            </div>
                            ${submission.submitted ? `
                                ${renderAttachmentLinks(submission.attachments)}
                                <div class="grade-editor">
                                    <span>${escapeHTML(formatGrade(submission.grade))}</span>
                                    <input type="number" min="0" step="0.01" value="${submission.grade ? escapeHTML(submission.grade.score) : ''}" placeholder="Score" data-grade-score-for="${escapeHTML(submission.submission_id)}">
                                    <input type="number" min="1" step="0.01" value="${submission.grade ? escapeHTML(submission.grade.max) : '100'}" placeholder="Max" data-grade-max-for="${escapeHTML(submission.submission_id)}">
                                    <button class="primary-btn compact-btn save-grade-btn" type="button" data-submission-id="${escapeHTML(submission.submission_id)}">Save Grade</button>
                                    <button class="ghost-btn compact-btn delete-grade-btn" type="button" data-submission-id="${escapeHTML(submission.submission_id)}">Delete Grade</button>
                                </div>
                            ` : '<span class="missing-status">No submission</span>'}
                        </div>
                    `).join("")}
                </div>
            ` : ''}
        </div>
    `;
}

function renderStudentListGradeEditor(classId, studentId) {
    const gradeRows = getStudentGradeRows(classId, studentId);

    if (gradeRows.length === 0) {
        return '<div class="student-list-grade-editor"><p>No activities yet.</p></div>';
    }

    return `
        <div class="student-list-grade-editor">
            ${gradeRows.map(({ activity, submission }) => `
                <div class="student-list-grade-row">
                    <div>
                        <strong>${escapeHTML(activity.title || 'Activity')}</strong>
                        <small>${submission?.submitted ? `Submitted ${escapeHTML(submission.submittedAt || submission.submitted_at || '')}` : 'No submission yet'}</small>
                    </div>
                    ${submission?.submitted ? `
                        <div class="grade-editor compact-grade-editor">
                            <span>${escapeHTML(formatGrade(submission.grade))}</span>
                            <input type="number" min="0" step="0.01" value="${submission.grade ? escapeHTML(submission.grade.score) : ''}" placeholder="Score" data-grade-score-for="${escapeHTML(submission.submission_id)}">
                            <input type="number" min="1" step="0.01" value="${submission.grade ? escapeHTML(submission.grade.max) : '100'}" placeholder="Max" data-grade-max-for="${escapeHTML(submission.submission_id)}">
                            <button class="primary-btn compact-btn save-grade-btn" type="button" data-submission-id="${escapeHTML(submission.submission_id)}">Save</button>
                            <button class="ghost-btn compact-btn delete-grade-btn" type="button" data-submission-id="${escapeHTML(submission.submission_id)}">Delete</button>
                        </div>
                    ` : '<span class="missing-status">Grade available after submission</span>'}
                </div>
            `).join("")}
        </div>
    `;
}

function renderClassActivities(classId, userRole = null) {
    if (!classworkList) {
        return;
    }

    const activities = getClassActivities(classId);
    if (activities.length === 0) {
        classworkList.innerHTML = '<p>No activities yet.</p>';
        return;
    }

    const resolvedUserRole = userRole || authenticatedUser?.role || appData?.user?.role || 'student';

    classworkList.innerHTML = activities.map((activity) => `
        <article class="classwork-item">
            <span>${escapeHTML(activity.type)}</span>
            <h5>${escapeHTML(activity.title)}</h5>
            <p>${escapeHTML(activity.instructions)}</p>
            <small>${activity.due_date || activity.dueDate ? `Due ${escapeHTML(activity.due_date || activity.dueDate)}` : 'No due date'}</small>
            ${renderAttachmentLinks(activity.attachments)}
            ${resolvedUserRole === 'faculty' ? `
                ${renderFacultySubmissionPanel(activity)}
                <div class="post-actions">
                    <button class="ghost-btn compact-btn delete-activity-btn" type="button" data-activity-id="${escapeHTML(activity.id)}">Delete</button>
                </div>
            ` : ''}
            ${resolvedUserRole === 'student' ? `
                <div class="student-submission-box">
                    <div>
                        <strong>${activity.submission ? 'Submitted' : 'Your submission'}</strong>
                        ${activity.submission ? `<small>${escapeHTML(activity.submission.submittedAt)}</small>` : ''}
                    </div>
                    ${activity.submission ? `<div class="student-grade-line"><strong>Grade</strong><span>${escapeHTML(formatGrade(activity.submission.grade))}</span></div>` : ''}
                    ${activity.submission ? renderAttachmentLinks(activity.submission.attachments) : activitySubmissionManager.renderPending(activity.id)}
                    ${activity.submission ? '' : `
                        <div class="stream-actions">
                            <button class="ghost-btn compact-btn student-attach-activity-btn" type="button" data-activity-id="${escapeHTML(activity.id)}">Attach</button>
                            <button class="primary-btn compact-btn student-submit-activity-btn" type="button" data-activity-id="${escapeHTML(activity.id)}">Submit</button>
                        </div>
                    `}
                </div>
            ` : ''}
        </article>
    `).join("");
}

function showStudentList(classId) {
    const selectedClass = appData.classes.find(c => String(c.id) === String(classId));
    if (!selectedClass) return;

    // Get students directly from the class data
    const classStudents = appData.students.filter((student) => String(student.class_id) === String(classId));

    // Use the student_count from the class data for accurate count
    const studentCount = parseInt(selectedClass.student_count) || 0;

    // Update student list page
    document.getElementById('studentListClassInfo').textContent = `Class: ${selectedClass.title} (${selectedClass.section})`;
    document.getElementById('studentListCount').textContent = `${studentCount} student${studentCount !== 1 ? 's' : ''}`;

    const studentListTable = document.getElementById('studentListTable');
    if (classStudents.length === 0) {
        studentListTable.innerHTML = '<tr><td colspan="5">No students enrolled yet.</td></tr>';
    } else {
        const role = authenticatedUser?.role || appData?.user?.role || 'student';
        studentListTable.innerHTML = classStudents.map((student) => {
            const gradeKey = `${student.class_id}:${student.id}`;
            const isGradeEditorOpen = expandedStudentGradeKey === gradeKey;

            return `
                <tr data-student-id="${student.id}" data-class-id="${student.class_id}">
                    <td>${escapeHTML(student.name)}</td>
                    <td>${student.id}</td>
                    <td>${escapeHTML(student.email || 'N/A')}</td>
                    <td>${escapeHTML(renderGradeSummaryText(getClassGradeSummary(student.class_id, student.id)))}</td>
                    <td>
                        ${role === 'faculty' ? `
                            <div class="student-list-actions">
                                <button class="ghost-btn compact-btn edit-student-grade-btn" type="button" data-student-id="${student.id}" data-class-id="${student.class_id}">
                                    ${isGradeEditorOpen ? 'Hide Grades' : 'Edit Grades'}
                                </button>
                                <button class="ghost-btn compact-btn remove-student-from-list-btn" type="button" data-student-id="${student.id}" data-class-id="${student.class_id}">Remove</button>
                            </div>
                        ` : '<span class="no-actions">View only</span>'}
                    </td>
                </tr>
                ${isGradeEditorOpen ? `
                    <tr class="student-grade-detail-row">
                        <td colspan="5">${renderStudentListGradeEditor(student.class_id, student.id)}</td>
                    </tr>
                ` : ''}
            `;
        }).join("");
    }

    // Show student list panel
    setActivePanel('studentList');
}

async function deleteAnnouncement(announcementId) {
    try {
        const result = await apiRequest("deleteAnnouncement", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                announcement_id: announcementId
            })
        });

        if (result.success) {
            // Remove post immediately
            const postCard = document.querySelector(`[data-announcement-id="${announcementId}"]`);
            if (postCard) {
                postCard.remove();
            }

            // Remove from app data to prevent re-rendering
            if (appData.announcements) {
                appData.announcements = appData.announcements.filter(a => String(a.id) !== String(announcementId));
            }
        }
    } catch (error) {
        console.error('Error deleting post:', error);
    }
}

function bindClassCardClicks() {
    const cards = document.querySelectorAll('.clickable-class-card');
    console.log('bindClassCardClicks - found cards:', cards.length);
    cards.forEach((card) => {
        console.log('Binding card with classId:', card.dataset.classId);
        const openCard = () => openClassFromCard(card.dataset.classId);

        card.addEventListener('click', openCard);
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openCard();
            }
        });
    });
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
            <p class="announcement-tag">${escapeHTML(item.tag)}</p>
            <h4>${escapeHTML(item.title)}</h4>
            <p>${escapeHTML(item.message)}</p>
            ${renderAttachmentLinks(item.attachments)}
        `;
        announcementList.prepend(card);
    });
}

// Render instructors from database
function renderInstructorsFromDB(instructors) {
    if (!peopleGrid || !instructors || instructors.length === 0) {
        if (peopleGrid) {
            const instructorSection = peopleGrid.querySelector('.person-card:first-child');
            if (instructorSection) {
                instructorSection.innerHTML = '<h4>Instructor</h4><p>No instructors found.</p>';
            }
        }
        return;
    }

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

// Render students from database (faculty only)
function renderStudentsFromDB(students, filterSubject = null) {
    const studentsTable = document.querySelector('#students table tbody');
    if (!studentsTable) {
        console.log('Students table not found');
        return;
    }

    console.log('renderStudentsFromDB called with students:', students, 'filter:', filterSubject);

    // Filter by subject if specified
    let filteredStudents = students;
    if (filterSubject && filterSubject !== '') {
        filteredStudents = students.filter(s => s.title === filterSubject);
        console.log('Filtered students:', filteredStudents);
    }

    if (!filteredStudents || filteredStudents.length === 0) {
        const message = filterSubject && filterSubject !== ''
            ? 'No students found for this subject.'
            : 'No students enrolled in your classes yet.';
        studentsTable.innerHTML = `<tr><td colspan="6">${message}</td></tr>`;
        return;
    }

    studentsTable.innerHTML = filteredStudents.map((student) => `
        <tr data-student-id="${student.id}" data-class-id="${student.class_id}">
            <td>${student.name}</td>
            <td>${student.id}</td>
            <td>${student.title}</td>
            <td>${student.section}</td>
            <td>${escapeHTML(renderGradeSummaryText(getClassGradeSummary(student.class_id, student.id)))}</td>
            <td>
                <span class="status-pill">Active</span>
            </td>
        </tr>
    `).join("");

    // Add event listeners for remove buttons (faculty only) - REMOVED FROM MASTER LIST
    // Master student list should not have remove buttons
    if (false && authenticatedUser.role === 'faculty') {
        console.log('Binding remove student buttons, found:', document.querySelectorAll('.remove-student-btn').length);
        document.querySelectorAll('.remove-student-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const studentId = btn.dataset.studentId;
                const classId = btn.dataset.classId;
                console.log('Remove button clicked - Student ID:', studentId, 'Class ID:', classId);
                const studentName = btn.closest('tr').querySelector('td:first-child').textContent;

                if (authenticatedUser.role === 'faculty') {
                    try {
                        console.log('Making API call to remove student...');
                        const result = await apiRequest("removeStudentFromClass", {
                            method: "POST",
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `student_id=${studentId}&class_id=${classId}`
                        });
                        console.log('API response:', result);

                        if (result.success) {
                            // Remove student row immediately
                            btn.closest('tr').remove();

                            // Update student count in current view
                            const remainingStudents = document.querySelectorAll('#students table tbody tr').length;
                            const studentCountElement = document.getElementById('studentListCount');
                            if (studentCountElement) {
                                studentCountElement.textContent = `${remainingStudents} student${remainingStudents !== 1 ? 's' : ''}`;
                            }

                            // Update class manager student count
                            const classManagerCountElement = document.getElementById('classManagerStudentCount');
                            if (classManagerCountElement) {
                                classManagerCountElement.textContent = `${remainingStudents} ${remainingStudents === 1 ? 'student' : 'students'} enrolled`;
                            }

                            // Remove from app data to prevent re-rendering
                            if (appData.students) {
                                appData.students = appData.students.filter(s => !(String(s.id) === String(studentId) && String(s.class_id) === String(classId)));
                            }

                            // Update class student count
                            if (appData.classes) {
                                const classToUpdate = appData.classes.find(c => String(c.id) === String(classId));
                                if (classToUpdate) {
                                    classToUpdate.student_count = Math.max(0, (classToUpdate.student_count || 1) - 1);
                                }
                            }

                            window.alert('Student removed successfully!');
                        } else {
                            window.alert('Failed to remove student: ' + (result.message || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Remove student error:', error);
                        window.alert('Error removing student.');
                    }
                }
            });
        });
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
    if (authOverlay) {
        authOverlay.classList.add("hidden");
    }
    document.body.classList.remove("auth-locked");
}

function showLogin() {
    if (authOverlay) {
        authOverlay.classList.remove("hidden");
    }
    document.body.classList.add("auth-locked");
    if (typeof showLoginPanel === 'function') {
        showLoginPanel();
    }
}

function setLoginError(message) {
    if (!loginError) return;
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

// Initialize the application session by checking whether a user is already logged in.
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

        // Only show login if auth overlay exists (not on index.php which has PHP session)
        if (authOverlay) {
            showLogin();
        }
    } catch (error) {
        console.error("App startup failed:", error);
        if (authOverlay) {
            setLoginError("Unable to start the login system.");
            showLogin();
        }
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

    // Set subjectFilter reference after DOM is ready
    subjectFilter = document.getElementById("subjectFilter");

    // Load data from database
    loadAppData();

    // Add event listener for subject filter
    if (subjectFilter) {
        subjectFilter.addEventListener('change', () => {
            renderStudentsFromDB(allStudents, subjectFilter.value);
        });
    }
});

// Bind click handlers to role buttons so the UI updates for faculty or student views.
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

// Bind navigation buttons to switch panels in the dashboard.
navButtons.forEach((button) => {
    button.addEventListener("click", () => {
        if (button.classList.contains("hidden")) {
            return;
        }

        setActivePanel(button.dataset.panel);
    });
});

if (backToClassesBtn) {
    backToClassesBtn.addEventListener("click", () => {
        setActivePanel("overview");
    });
}

// Add event listener for enrolled students box and remove buttons
document.addEventListener('click', function (e) {
    if (e.target.closest('.enrolled-students-box')) {
        e.preventDefault();
        const classId = e.target.closest('.enrolled-students-box').dataset.classId;
        showStudentList(classId);
    }

    if (e.target.closest('.edit-student-grade-btn')) {
        e.preventDefault();
        const btn = e.target.closest('.edit-student-grade-btn');
        const gradeKey = `${btn.dataset.classId}:${btn.dataset.studentId}`;
        expandedStudentGradeKey = expandedStudentGradeKey === gradeKey ? null : gradeKey;
        showStudentList(btn.dataset.classId);
    }

    // Handle remove student from list button (in individual student list view)
    if (e.target.closest('.remove-student-from-list-btn')) {
        e.preventDefault();
        const btn = e.target.closest('.remove-student-from-list-btn');
        const studentId = btn.dataset.studentId;
        const classId = btn.dataset.classId;
        const studentRow = btn.closest('tr');
        const studentName = studentRow.querySelector('td:first-child').textContent;

        if (confirm(`Remove ${studentName} from this class?`)) {
            removeStudentFromList(studentId, classId, studentRow);
        }
    }
});

async function removeStudentFromList(studentId, classId, studentElement) {
    try {
        console.log('Removing student from list - Student:', studentId, 'Class:', classId);
        const result = await apiRequest("removeStudentFromClass", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `student_id=${studentId}&class_id=${classId}`
        });

        console.log('Remove student response:', result);

        if (result.success) {
            // Remove student row immediately
            studentElement.remove();

            // Update student count
            const currentCount = parseInt(document.getElementById('studentListCount').textContent.match(/\d+/)[0]);
            const newCount = currentCount - 1;
            document.getElementById('studentListCount').textContent = `${newCount} student${newCount !== 1 ? 's' : ''}`;

            // Show empty message if no students left
            if (newCount === 0) {
                document.getElementById('studentListTable').innerHTML = '<tr><td colspan="5">No students enrolled yet.</td></tr>';
            }

            // Remove from app data
            if (appData.students) {
                appData.students = appData.students.filter(s => !(String(s.id) === String(studentId) && String(s.class_id) === String(classId)));
            }
            if (expandedStudentGradeKey === `${classId}:${studentId}`) {
                expandedStudentGradeKey = null;
            }

            // Update class student count
            if (appData.classes) {
                const classToUpdate = appData.classes.find(c => String(c.id) === String(classId));
                if (classToUpdate) {
                    classToUpdate.student_count = Math.max(0, (classToUpdate.student_count || 1) - 1);
                }
            }

            // Update class manager if currently visible
            const classManagerCountElement = document.getElementById('classManagerStudentCount');
            if (classManagerCountElement) {
                classManagerCountElement.textContent = `${newCount} ${newCount === 1 ? 'student' : 'students'} enrolled`;
            }

            window.alert('Student removed successfully!');
        } else {
            window.alert('Failed to remove student: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error removing student from list:', error);
        window.alert('Error removing student.');
    }
}

// Remove a student from a class through the API and refresh relevant UI panels.
async function removeStudentFromClass(studentId, classId, studentElement) {
    try {
        console.log('Removing student from class - Student:', studentId, 'Class:', classId);
        const result = await apiRequest("removeStudentFromClass", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `student_id=${studentId}&class_id=${classId}`
        });

        console.log('Remove student response:', result);

        if (result.success) {
            // Remove student element immediately
            studentElement.remove();

            // Update student counts
            const currentCount = parseInt(document.getElementById('classManagerStudentCount').textContent.match(/\d+/)[0]);
            const newCount = currentCount - 1;
            document.getElementById('classManagerStudentCount').textContent = `${newCount} ${newCount === 1 ? 'student' : 'students'} enrolled`;

            // Update the count in enrolled students box
            const countElement = document.querySelector('.enrolled-students-box p');
            if (countElement) {
                countElement.textContent = `${newCount} enrolled students`;
            }

            // Remove from app data
            if (appData.students) {
                appData.students = appData.students.filter(s => !(String(s.id) === String(studentId) && String(s.class_id) === String(classId)));
            }

            // Update class student count
            if (appData.classes) {
                const classToUpdate = appData.classes.find(c => String(c.id) === String(classId));
                if (classToUpdate) {
                    classToUpdate.student_count = Math.max(0, (classToUpdate.student_count || 1) - 1);
                }
            }

            // Show empty message if no students left
            if (newCount === 0) {
                document.getElementById('classManagerStudents').innerHTML = '<p>No students enrolled yet.</p>';
            }

            window.alert('Student removed successfully!');
        } else {
            window.alert('Failed to remove student: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error removing student from class:', error);
        window.alert('Error removing student.');
    }
}

// Add event listener for back to class button
const backToClassBtn = document.getElementById('backToClassBtn');
if (backToClassBtn) {
    backToClassBtn.addEventListener('click', () => {
        setActivePanel('classManager');
    });
}

// Add event listener for delete post buttons (faculty only)
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('delete-post-btn')) {
        e.preventDefault();
        const announcementId = e.target.dataset.announcementId;
        deleteAnnouncement(announcementId);
    }

    if (e.target.classList.contains('delete-activity-btn')) {
        e.preventDefault();
        deleteActivity(e.target.dataset.activityId);
    }

    if (e.target.classList.contains('view-submissions-btn')) {
        e.preventDefault();
        const activityId = e.target.dataset.activityId;
        expandedSubmissionActivityId = String(expandedSubmissionActivityId) === String(activityId) ? null : activityId;
        renderClassManager(selectedClassId);
    }

    if (e.target.classList.contains('save-grade-btn')) {
        e.preventDefault();
        saveSubmissionGrade(e.target.dataset.submissionId, e.target.closest('.grade-editor'));
    }

    if (e.target.classList.contains('delete-grade-btn')) {
        e.preventDefault();
        deleteSubmissionGrade(e.target.dataset.submissionId);
    }
});

// Locate a submission object by its ID within the loaded activity data.
function findSubmissionById(submissionId) {
    for (const activity of appData?.activities || []) {
        const submission = (activity.submissions || []).find((item) => String(item.submission_id) === String(submissionId));
        if (submission) {
            return submission;
        }
    }
    return null;
}

// Re-render grade-related views after a grade change or activity update.
function refreshGradeViews() {
    renderClassManager(selectedClassId);
    if (document.getElementById('studentList')?.classList.contains('active') && selectedClassId) {
        showStudentList(selectedClassId);
    }
}

// Persist grade data for a submission and refresh the UI on success.
async function saveSubmissionGrade(submissionId, editor = document) {
    const gradeEditor = editor || document;
    const scoreInput = gradeEditor.querySelector(`[data-grade-score-for="${CSS.escape(String(submissionId))}"]`);
    const maxInput = gradeEditor.querySelector(`[data-grade-max-for="${CSS.escape(String(submissionId))}"]`);
    const score = scoreInput?.value;
    const maxScore = maxInput?.value;

    try {
        const result = await apiRequest("saveSubmissionGrade", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                submission_id: submissionId,
                score,
                max_score: maxScore
            })
        });

        if (!result.success) {
            window.alert("Failed to save grade: " + (result.message || "Unknown error"));
            return;
        }

        const submission = findSubmissionById(submissionId);
        if (submission) {
            submission.grade = result.grade;
            submission.grade_score = result.grade.score;
            submission.grade_max = result.grade.max;
        }
        refreshGradeViews();
    } catch (error) {
        console.error("Save grade error:", error);
        window.alert("Error saving grade.");
    }
}

// Delete a grade for a submission and refresh the affected class views.
async function deleteSubmissionGrade(submissionId) {
    try {
        const result = await apiRequest("deleteSubmissionGrade", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                submission_id: submissionId
            })
        });

        if (!result.success) {
            window.alert("Failed to delete grade: " + (result.message || "Unknown error"));
            return;
        }

        const submission = findSubmissionById(submissionId);
        if (submission) {
            submission.grade = null;
            submission.grade_score = null;
            submission.grade_max = null;
        }
        refreshGradeViews();
    } catch (error) {
        console.error("Delete grade error:", error);
        window.alert("Error deleting grade.");
    }
}

// Delete an activity by ID and update the class manager data.
async function deleteActivity(activityId) {
    if (!activityId) {
        return;
    }

    try {
        const result = await apiRequest("deleteActivity", {
            method: "POST",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                activity_id: activityId
            })
        });

        if (!result.success) {
            window.alert("Failed to delete activity: " + (result.message || "Unknown error"));
            return;
        }

        if (appData?.activities) {
            appData.activities = appData.activities.filter((activity) => String(activity.id) !== String(activityId));
        }
        if (localClassActivities[selectedClassId]) {
            localClassActivities[selectedClassId] = localClassActivities[selectedClassId].filter((activity) => String(activity.id) !== String(activityId));
        }
        renderClassManager(selectedClassId);
    } catch (error) {
        console.error("Delete activity error:", error);
        window.alert("Error deleting activity.");
    }
}

// Handle creating a new announcement post with optional attachments.
if (classStreamPostBtn && classStreamInput) {
    classStreamPostBtn.addEventListener("click", async () => {
        const message = classStreamInput.value.trim();
        if (!selectedClassId || (!message && !classAttachmentManager.hasFiles())) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append("class_id", selectedClassId);
            formData.append("title", authenticatedUser?.name || 'Faculty');
            formData.append("message", message || "Shared attachment");
            formData.append("tag", "notice");
            classAttachmentManager.appendToFormData(formData);

            const result = await apiRequest("createAnnouncement", {
                method: "POST",
                body: formData
            });

            if (!result.success) {
                window.alert("Failed to create post: " + (result.message || "Unknown error"));
                return;
            }
            if (!appData.announcements) {
                appData.announcements = [];
            }
            appData.announcements.unshift(result.announcement);
            classStreamInput.value = "";
            classAttachmentManager.clear();
            renderClassManager(selectedClassId);
        } catch (error) {
            console.error("Create post error:", error);
            window.alert("Error creating post.");
        }
    });
}

// Show the activity creation form when the faculty button is clicked.
if (createActivityBtn && activityForm) {
    createActivityBtn.addEventListener("click", () => {
        if (!selectedClassId) {
            window.alert("Please select a class before creating an activity.");
            return;
        }

        activityForm.classList.remove("hidden");
        activityTitle.focus();
    });
}

// Hide the activity form and clear attachments when cancellation is requested.
if (cancelActivityBtn && activityForm) {
    cancelActivityBtn.addEventListener("click", () => {
        activityForm.reset();
        activityAttachmentManager.clear();
        activityForm.classList.add("hidden");
    });
}

// Submit the activity creation form and send data to the server.
if (activityForm) {
    activityForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (!selectedClassId) {
            return;
        }

        const title = activityTitle.value.trim();
        const type = activityType.value;
        const instructions = activityInstructions.value.trim();
        const dueDate = activityDueDate.value;

        if (!title || !instructions) {
            return;
        }

        if (!localClassActivities[selectedClassId]) {
            localClassActivities[selectedClassId] = [];
        }

        const formData = new FormData();
        formData.append("class_id", selectedClassId);
        formData.append("title", title);
        formData.append("type", type);
        formData.append("instructions", instructions);
        formData.append("due_date", dueDate);
        activityAttachmentManager.appendToFormData(formData);

        try {
            const result = await apiRequest("createActivity", {
                method: "POST",
                body: formData
            });

            if (!result.success) {
                window.alert("Failed to create activity: " + (result.message || "Unknown error"));
                return;
            }

            if (!appData.activities) {
                appData.activities = [];
            }
            appData.activities.unshift(result.activity);

            activityForm.reset();
            activityAttachmentManager.clear();
            activityForm.classList.add("hidden");
            renderClassManager(selectedClassId);
        } catch (error) {
            console.error("Create activity error:", error);
            window.alert("Error creating activity.");
        }
    });
}

// Open the create class modal overlay.
if (openModalBtn) {
    openModalBtn.addEventListener("click", () => {
        modalBackdrop.classList.remove("hidden");
    });
}

// Close the create class modal overlay.
if (closeModalBtn) {
    closeModalBtn.addEventListener("click", () => {
        modalBackdrop.classList.add("hidden");
    });
}

// Close modal overlay when clicking outside the modal content.
if (modalBackdrop) {
    modalBackdrop.addEventListener("click", (event) => {
        if (event.target === modalBackdrop) {
            modalBackdrop.classList.add("hidden");
        }
    });
}

// Request a generated class code from the server and display it.
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

// Submit profile updates and refresh the displayed user information.
if (profileForm) {
    profileForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        try {
            const name = document.getElementById("profileName").value.trim();
            const email = document.getElementById("profileEmail").value.trim();
            const sexRadio = document.querySelector('input[name="sex"]:checked');
            const sex = sexRadio ? sexRadio.value : '';

            const result = await apiRequest("updateProfile", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    name: name,
                    email: email,
                    sex: sex
                })
            });

            if (result.success) {
                // Update session data
                authenticatedUser.name = name;
                authenticatedUser.email = email;
                authenticatedUser.sex = sex;

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

// Handle password change requests through the password form.
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

// Submit a new class creation form and refresh the app data afterwards.
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

// Join a class using the provided class code.
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
                // Force UI update
                updateUIWithDatabaseData();
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

// Add a random rotating announcement manually to the dashboard.
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
