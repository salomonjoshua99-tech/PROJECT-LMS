<?php
session_start();

// Check if user is not logged in, redirect to login page
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header('Location: src/Frontend/pages/loginPage.php');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';


use App\Controllers\ApiController;
use App\Helpers\Database;
use App\Models\UserModel;
use App\Models\ClassModel;


// Get user data from session
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortal Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/homePage.css">
</head>

<body>

    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand-block">
                <p class="eyebrow">Campus Workspace</p>
                <h1>LEAR<span style="color: teal;">N</span>TEACH</h1>
                <p class="brand-copy">A role-aware classroom dashboard for faculty and students.</p>
            </div>

            <nav class="nav-list">
                <button class="nav-item active" data-panel="overview">Overview</button>
                <button class="nav-item faculty-only" data-panel="students">Master Student List</button>
                <button class="nav-item student-only hidden" data-panel="announcements">Announcement Portal</button>
                <button class="nav-item student-only hidden" data-panel="classwork">Classwork</button>
                <button class="nav-item" data-panel="settings">Settings</button>
            </nav>

            <div class="role-switcher">
                <p class="role-label">Class Role</p>
                <div class="toggle-wrap">
                    <button class="role-btn active" data-role="faculty">Faculty</button>
                    <button class="role-btn" data-role="student">Student</button>
                </div>
            </div>

            <div class="profile-card">
                <div class="avatar"><?php echo strtoupper(substr($user['name'], 0, 2)); ?></div>
                <div class="profile-meta">
                    <p class="profile-name"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="profile-role" id="profileRole"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
                </div>
                <button class="logout-btn" type="button" onclick="logout()">Log Out</button>
            </div>
        </aside>

        <main class="main-content">
            <section class="hero-card" id="heroCard">
                <div class="hero-text">
                    <p class="eyebrow" id="heroEyebrow">Faculty Home</p>
                    <h3 id="heroTitle">Manage classes, students, and deadlines from one clean workspace.</h3>
                    <p class="hero-copy" id="heroCopy">Create classes, share generated codes, and monitor activity at a glance.</p>
                </div>

                <div class="hero-actions faculty-only">
                    <button class="primary-btn" id="openModalBtn" type="button">Create New Class</button>
                    <button class="ghost-btn" type="button">Export Schedule</button>
                </div>

                <div class="hero-actions student-only hidden">
                    <div class="join-box">
                        <label for="joinCode">Join Class</label>
                        <div class="join-input-row">
                            <input id="joinCode" type="text" placeholder="Enter class code">
                            <button class="primary-btn compact-btn" id="joinClassBtn" type="button">Join</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="banner student-only hidden">
                <div>
                    <p class="eyebrow">Pending Activities</p>
                    <h3>Closest deadlines across all your subjects</h3>
                </div>
                <ul class="deadline-list" id="deadlineList">
                    <li><span>Loading deadlines...</span></li>
                </ul>
            </section>

            <section class="panel active" id="overview">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Classes</p>
                        <h3 id="classHeading">Subjects You Teach</h3>
                    </div>
                    <span class="section-chip" id="classCount">3 active classes</span>
                </div>

                <div class="class-grid" id="classGrid">
                    <p>Loading classes...</p>
                </div>
            </section>

            <section class="panel" id="classManager">
                <div class="classroom-header">
                    <button class="ghost-btn compact-btn" id="backToClassesBtn" type="button">Back</button>
                    <div class="classroom-title-block">
                        <p class="eyebrow" id="classManagerCourse">Classroom</p>
                        <h3 id="classManagerTitle">Select a class</h3>
                        <p id="classManagerSection">Open a subject to manage its class space.</p>
                    </div>
                    <div class="classroom-code-box">
                        <span>Class Code</span>
                        <strong id="classManagerCode">------</strong>
                    </div>
                </div>

                <div class="classroom-layout">
                    <div class="classroom-main">
                        <article class="stream-composer faculty-only">
                            <p class="eyebrow">Stream</p>
                            <h4>Share something with this class</h4>
                            <textarea id="classStreamInput" placeholder="Announce something to your class..." rows="3"></textarea>
                            <input class="hidden" id="classAttachmentInput" type="file" multiple>
                            <div class="attachment-preview hidden" id="classAttachmentPreview"></div>
                            <div class="stream-actions">
                                <button class="ghost-btn compact-btn" id="classAttachBtn" type="button">Attach</button>
                                <button class="primary-btn compact-btn" id="classStreamPostBtn" type="button">Post</button>
                            </div>
                        </article>

                        <div class="stack-list" id="classStreamList">
                            <article class="info-card">
                                <p class="announcement-tag">Class Update</p>
                                <h4>Welcome to your class space</h4>
                                <p>Use this area for announcements, reminders, and class updates.</p>
                            </article>
                        </div>
                    </div>

                    <aside class="classroom-side">
                        <article class="classroom-widget">
                            <p class="eyebrow">Students</p>
                            <h4 id="classManagerStudentCount">0 enrolled</h4>
                            <div class="people-list compact-people-list" id="classManagerStudents">
                                <p>No students enrolled yet.</p>
                            </div>
                        </article>

                        <article class="classroom-widget">
                            <p class="eyebrow">Classwork</p>
                            <div class="classwork-actions faculty-only">
                                <button class="ghost-btn compact-btn" id="createActivityBtn" type="button">Create Activity</button>
                                <button class="ghost-btn compact-btn" type="button">Add Deadline</button>
                            </div>
                            <form class="activity-form faculty-only hidden" id="activityForm">
                                <label>
                                    Activity Title
                                    <input type="text" id="activityTitle" placeholder="e.g. Lab Activity 1" required>
                                </label>
                                <label>
                                    Type
                                    <select id="activityType" required>
                                        <option value="Assignment">Assignment</option>
                                        <option value="Quiz">Quiz</option>
                                        <option value="Lab">Lab</option>
                                        <option value="Project">Project</option>
                                    </select>
                                </label>
                                <label>
                                    Instructions
                                    <textarea id="activityInstructions" rows="3" placeholder="Write the instructions for this activity..." required></textarea>
                                </label>
                                <label>
                                    Due Date
                                    <input type="date" id="activityDueDate">
                                </label>
                                <input class="hidden" id="activityAttachmentInput" type="file" multiple>
                                <div class="attachment-preview hidden" id="activityAttachmentPreview"></div>
                                <div class="stream-actions">
                                    <button class="ghost-btn compact-btn" id="activityAttachBtn" type="button">Attach</button>
                                    <button class="ghost-btn compact-btn" id="cancelActivityBtn" type="button">Cancel</button>
                                    <button class="primary-btn compact-btn" type="submit">Save</button>
                                </div>
                            </form>
                            <div class="classwork-list" id="classworkList">
                                <p>No activities yet.</p>
                            </div>
                        </article>
                    </aside>
                </div>
            </section>

            <section class="panel" id="students">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Faculty Tool</p>
                        <h3>Master Student List</h3>
                    </div>
                </div>

                <div class="table-card">
                    <div class="filter-bar">
                        <label for="subjectFilter">Filter by Subject:</label>
                        <select id="subjectFilter" class="subject-filter-select">
                            <option value="">Select a subject...</option>
                        </select>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>ID Number</th>
                                <th>Subject</th>
                                <th>Section</th>
                                <th>Current Grade</th>
                                <th>Status / Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5">Loading students...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" id="announcements">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Student Tool</p>
                        <h3>Announcement Portal</h3>
                    </div>
                    <span class="section-chip">Live Feed</span>
                </div>

                <div class="announcement-toolbar">
                    <p class="live-note">Sample updates appear automatically to simulate a real-time feed.</p>
                    <button class="ghost-btn compact-btn" id="manualAnnouncementBtn" type="button">Add Update</button>
                </div>

                <div class="stack-list" id="announcementList">
                    <article class="info-card">
                        <p class="announcement-tag">New</p>
                        <h4>Room change for Friday session</h4>
                        <p>Web Standards will meet in Lab 402 this Friday due to projector maintenance.</p>
                    </article>
                    <article class="info-card">
                        <p class="announcement-tag">Reminder</p>
                        <h4>Quiz 2 opens at 6:00 PM</h4>
                        <p>Please review modules 3 and 4 before the quiz window closes on April 14.</p>
                    </article>
                </div>
            </section>

            <section class="panel" id="classwork">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Student Tool</p>
                        <h3>Classwork Modules</h3>
                    </div>
                </div>

                <div class="module-grid">
                    <article class="module-card">
                        <h4>Lessons</h4>
                        <ul>
                            <li>Semantic HTML Structures</li>
                            <li>CSS Layout Systems</li>
                            <li>Accessible Form Design</li>
                        </ul>
                    </article>
                    <article class="module-card">
                        <h4>Labs</h4>
                        <ul>
                            <li>Responsive Dashboard Layout</li>
                            <li>Form Validation Exercise</li>
                            <li>Calendar UI Build</li>
                        </ul>
                    </article>
                    <article class="module-card">
                        <h4>Quizzes</h4>
                        <ul>
                            <li>HTML Elements Checkpoint</li>
                            <li>CSS Specificity Drill</li>
                            <li>JavaScript DOM Basics</li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="panel" id="studentList">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Class Management</p>
                        <h3 id="studentListTitle">Student List</h3>
                    </div>
                    <button class="ghost-btn compact-btn" id="backToClassBtn" type="button">Back to Class</button>
                </div>

                <div class="table-card">
                    <div class="class-info-header">
                        <h4 id="studentListClassInfo">Class: Loading...</h4>
                        <span id="studentListCount">0 students</span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>ID Number</th>
                                <th>Email</th>
                                <th>Current Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentListTable">
                            <tr>
                                <td colspan="5">Loading students...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" id="settings">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Account Settings</p>
                        <h3>Settings</h3>
                    </div>
                </div>

                <div class="settings-grid">
                    <article class="settings-card">
                        <h4>Profile Information</h4>
                        <form id="profileForm" class="settings-form">
                            <label>
                                Full Name
                                <input type="text" id="profileName" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </label>
                            <label>
                                Email Address
                                <input type="email" id="profileEmail" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </label>
                            <label>
                                Birthdate
                                <input type="date" id="profileBirthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>" disabled>
                            </label>
                            <fieldset class="role-fieldset">
                                <legend>Sex</legend>
                                <label class="role-option"><input type="radio" name="sex" id="profileSexMale" value="male" <?php echo (isset($user['sex']) && $user['sex'] === 'male') ? 'checked' : ''; ?> required> Male</label>
                                <label class="role-option"><input type="radio" name="sex" id="profileSexFemale" value="female" <?php echo (isset($user['sex']) && $user['sex'] === 'female') ? 'checked' : ''; ?>> Female</label>
                                <label class="role-option"><input type="radio" name="sex" id="profileSexOther" value="other" <?php echo (isset($user['sex']) && $user['sex'] === 'other') ? 'checked' : ''; ?>> Other</label>
                            </fieldset>
                            <label>
                                Profile Initials (Auto-generated from name)
                                <input type="text" id="profileInitials" value="<?php echo strtoupper(substr($user['name'], 0, 2)); ?>">
                            </label>
                            <button class="primary-btn" type="submit">Update Profile</button>
                        </form>
                    </article>

                    <article class="settings-card">
                        <h4>Change Password</h4>
                        <form id="passwordForm" class="settings-form">
                            <label>
                                Current Password
                                <input type="password" id="currentPassword" required>
                            </label>
                            <label>
                                New Password
                                <input type="password" id="newPassword" required>
                            </label>
                            <label>
                                Confirm New Password
                                <input type="password" id="confirmPassword" required>
                            </label>
                            <button class="primary-btn" type="submit">Change Password</button>
                        </form>
                    </article>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-backdrop hidden" id="modalBackdrop">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Faculty Action</p>
                    <h3 id="modalTitle">Create New Class</h3>
                </div>
                <button class="icon-btn" id="closeModalBtn" type="button" aria-label="Close modal">&times;</button>
            </div>

            <form id="classForm" class="modal-form">
                <label>
                    Subject Name
                    <input type="text" id="subjectName" placeholder="e.g. Web Standards and Practices" required>
                </label>
                <label>
                    Section
                    <input type="text" id="sectionName" placeholder="e.g. BSIT-3A" required>
                </label>
                <label>
                    Course Code
                    <input type="text" id="courseCode" placeholder="e.g. IT-WS101" required>
                </label>

                <div class="generated-box">
                    <div>
                        <p class="generated-label">Generated Class Code</p>
                        <strong id="generatedCode">------</strong>
                    </div>
                    <button class="ghost-btn compact-btn" id="generateCodeBtn" type="button">Generate Code</button>
                </div>

                <button class="primary-btn" type="submit">Save Class</button>
            </form>
        </div>
    </div>

    <script>
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to log out?')) {
                fetch('api.php?action=logout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'logout'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Logout response:', data);
                        if (data.success) {
                            // Clear session and redirect
                            alert(`Logout Success: ${data.message}`);
                            window.location.href = 'pages/loginPage.php';
                        } else {
                            alert('Logout failed: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        alert('Logout error. Please try again.');
                    });
            }
        }

        // Set initial role based on user session
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.body;
            body.setAttribute('data-role', '<?php echo htmlspecialchars($user['role']); ?>');
            body.setAttribute('data-user-id', '<?php echo (int) $user['id']; ?>');
            const userRole = '<?php echo htmlspecialchars($user['role']); ?>';
            const roleBtns = document.querySelectorAll('.role-btn');
            const facultyOnly = document.querySelectorAll('.faculty-only');
            const studentOnly = document.querySelectorAll('.student-only');

            // Set active role button
            roleBtns.forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.role === userRole) {
                    btn.classList.add('active');
                }
            });

            // Show/hide role-specific content
            if (userRole === 'faculty') {
                facultyOnly.forEach(el => el.classList.remove('hidden'));
                studentOnly.forEach(el => el.classList.add('hidden'));
                document.getElementById('heroEyebrow').textContent = 'Faculty Home';
                document.getElementById('heroTitle').textContent = 'Manage classes, students, and deadlines from one clean workspace.';
                document.getElementById('heroCopy').textContent = 'Create classes, share generated codes, and monitor activity at a glance.';
                document.getElementById('classHeading').textContent = 'Subjects You Teach';
            } else if (userRole === 'student') {
                facultyOnly.forEach(el => el.classList.add('hidden'));
                studentOnly.forEach(el => el.classList.remove('hidden'));
                document.getElementById('heroEyebrow').textContent = 'Student Home';
                document.getElementById('heroTitle').textContent = 'Track your progress and stay updated with your classes.';
                document.getElementById('heroCopy').textContent = 'Join classes, view announcements, and manage your coursework.';
                document.getElementById('classHeading').textContent = 'Your Classes';
            }
        });
    </script>
    <script src="assets/JavaScript/homePage.js"></script>
</body>

</html>