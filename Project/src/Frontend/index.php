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
                <button class="nav-item student-only hidden" data-panel="calendar">Course Calendar</button>
                <button class="nav-item student-only hidden" data-panel="announcements">Announcement Portal</button>
                <button class="nav-item student-only hidden" data-panel="classwork">Classwork</button>
                <button class="nav-item student-only hidden" data-panel="people">People</button>
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
                    <li><span>Lab Report 4</span><strong>Apr 13</strong></li>
                    <li><span>Quiz 2 in Web Standards</span><strong>Apr 14</strong></li>
                    <li><span>Reflection Paper</span><strong>Apr 16</strong></li>
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
                    <article class="class-card">
                        <div class="class-card-top">
                            <p class="course-code">IT-WS101</p>
                            <span class="status-pill">Live</span>
                        </div>
                        <h4>Web Standards and Practices</h4>
                        <p>Section BSIT-3A</p>
                        <div class="card-meta">
                            <span>38 students</span>
                            <span>Code: WS3A71</span>
                        </div>
                    </article>
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
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>ID Number</th>
                                <th>Subject</th>
                                <th>Section</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Mariel Santos</td>
                                <td>2023-00124</td>
                                <td>Web Standards and Practices</td>
                                <td>BSIT-3A</td>
                                <td>Active</td>
                            </tr>
                            <tr>
                                <td>Kevin Ramos</td>
                                <td>2023-00491</td>
                                <td>Human Computer Interaction</td>
                                <td>BSCS-2B</td>
                                <td>Active</td>
                            </tr>
                            <tr>
                                <td>Andrea Lopez</td>
                                <td>2022-00617</td>
                                <td>Networking Fundamentals</td>
                                <td>BSIT-3C</td>
                                <td>Needs Follow-up</td>
                            </tr>
                            <tr>
                                <td>Paolo Cruz</td>
                                <td>2023-00208</td>
                                <td>Web Standards and Practices</td>
                                <td>BSIT-3A</td>
                                <td>Active</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" id="calendar">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Student Tool</p>
                        <h3>Course Calendar</h3>
                    </div>
                </div>

                <div class="calendar-card">
                    <div class="calendar-header">
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                        <span>Sun</span>
                    </div>
                    <div class="calendar-grid">
                        <div class="calendar-day muted">1</div>
                        <div class="calendar-day muted">2</div>
                        <div class="calendar-day muted">3</div>
                        <div class="calendar-day">4</div>
                        <div class="calendar-day">5</div>
                        <div class="calendar-day">6</div>
                        <div class="calendar-day">7</div>
                        <div class="calendar-day">8</div>
                        <div class="calendar-day">9</div>
                        <div class="calendar-day">10</div>
                        <div class="calendar-day due">11<small>Quiz</small></div>
                        <div class="calendar-day">12</div>
                        <div class="calendar-day due">13<small>Lab</small></div>
                        <div class="calendar-day">14</div>
                        <div class="calendar-day">15</div>
                        <div class="calendar-day due">16<small>Paper</small></div>
                        <div class="calendar-day">17</div>
                        <div class="calendar-day">18</div>
                        <div class="calendar-day">19</div>
                        <div class="calendar-day">20</div>
                        <div class="calendar-day">21</div>
                    </div>
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

            <section class="panel" id="people">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Student Tool</p>
                        <h3>People</h3>
                    </div>
                </div>

                <div class="people-grid" id="peopleGrid">
                    <article class="person-card">
                        <h4>Instructor</h4>
                        <p><strong>Prof. Luna Mercado</strong></p>
                        <p>Web Standards and Practices</p>
                        <p>luna.mercado@school.edu</p>
                    </article>

                    <article class="person-card">
                        <h4>Classmates</h4>
                        <ul class="people-list">
                            <li>Mariel Santos</li>
                            <li>Kevin Ramos</li>
                            <li>Andrea Lopez</li>
                            <li>Paolo Cruz</li>
                            <li>Trisha Valdez</li>
                        </ul>
                    </article>
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
