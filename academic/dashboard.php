<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('academic');

$page_title = 'Academic Office Dashboard';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

// Get statistics
$stats = [];

// Total exams this term
$query = "SELECT COUNT(*) as count FROM exams WHERE term = (SELECT current_term FROM school_settings LIMIT 1) AND year = (SELECT academic_year FROM school_settings LIMIT 1)";
$result = $conn->query($query);
$stats['total_exams'] = $result->fetch_assoc()['count'];

// Published results
$query = "SELECT COUNT(DISTINCT exam_id) as count FROM exam_results WHERE marks_obtained IS NOT NULL";
$result = $conn->query($query);
$stats['published_results'] = $result->fetch_assoc()['count'];

// Total classes
$query = "SELECT COUNT(*) as count FROM classes";
$result = $conn->query($query);
$stats['total_classes'] = $result->fetch_assoc()['count'];

// Timetable entries
$query = "SELECT COUNT(*) as count FROM timetable_entries";
$result = $conn->query($query);
$stats['timetable_entries'] = $result->fetch_assoc()['count'];

// Upcoming exams (next 7 days)
$query = "SELECT COUNT(*) as count FROM exams WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$result = $conn->query($query);
$stats['upcoming_exams'] = $result->fetch_assoc()['count'];

// Syllabus coverage average
$stats['syllabus_coverage'] = 65; // Sample data - implement actual calculation

// Recent exams
$query = "SELECT * FROM exams ORDER BY created_at DESC LIMIT 5";
$recent_exams = $conn->query($query);
?>

<div class="ml-64 mt-16 p-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-green-500 to-teal-600 rounded-2xl p-6 mb-6 text-white">
        <h2 class="text-2xl font-bold">Welcome to Academic Office</h2>
        <p class="mt-2">Manage examinations, timetable, and academic records</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Total Exams</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_exams']; ?></p>
                </div>
                <i class="fas fa-pen-alt text-green-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Results Published</p>
                    <p class="text-2xl font-bold"><?php echo $stats['published_results']; ?></p>
                </div>
                <i class="fas fa-file-alt text-blue-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Classes</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_classes']; ?></p>
                </div>
                <i class="fas fa-school text-purple-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Timetable Entries</p>
                    <p class="text-2xl font-bold"><?php echo $stats['timetable_entries']; ?></p>
                </div>
                <i class="fas fa-calendar-alt text-yellow-500 text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-xs">Upcoming Exams</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $stats['upcoming_exams']; ?></p>
                </div>
                <i class="fas fa-clock text-orange-500 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <a href="timetable/create.php" class="bg-blue-50 hover:bg-blue-100 rounded-xl p-4 text-center transition">
            <i class="fas fa-plus-circle text-blue-600 text-2xl mb-2 block"></i>
            <span class="font-semibold">Add Timetable</span>
        </a>
        <a href="exams/create.php" class="bg-green-50 hover:bg-green-100 rounded-xl p-4 text-center transition">
            <i class="fas fa-plus-circle text-green-600 text-2xl mb-2 block"></i>
            <span class="font-semibold">Create Exam</span>
        </a>
        <a href="exams/results/upload.php" class="bg-yellow-50 hover:bg-yellow-100 rounded-xl p-4 text-center transition">
            <i class="fas fa-upload text-yellow-600 text-2xl mb-2 block"></i>
            <span class="font-semibold">Upload Results</span>
        </a>
        <a href="report-cards/generate.php" class="bg-purple-50 hover:bg-purple-100 rounded-xl p-4 text-center transition">
            <i class="fas fa-file-pdf text-purple-600 text-2xl mb-2 block"></i>
            <span class="font-semibold">Generate Report Cards</span>
        </a>
    </div>

    <!-- Recent Exams & Timetable -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Exams -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold">Recent Exams</h3>
                <a href="exams/index.php" class="text-blue-600 text-sm">View All →</a>
            </div>
            <div class="divide-y">
                <?php if ($recent_exams && $recent_exams->num_rows > 0): ?>
                    <?php while($exam = $recent_exams->fetch_assoc()): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($exam['name']); ?></p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo ucfirst($exam['type']); ?> - <?php echo ucfirst($exam['term']); ?> <?php echo $exam['year']; ?>
                                    </p>
                                </div>
                                <span class="text-xs text-gray-400"><?php echo date('M d', strtotime($exam['start_date'])); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">No exams created yet</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Weekly Timestamp Preview -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold">Today's Timetable</h3>
                <a href="timetable/index.php" class="text-blue-600 text-sm">Manage →</a>
            </div>
            <div class="p-4">
                <?php
                $today = strtolower(date('l'));
                $query = "SELECT te.*, s.name as subject_name, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
                          FROM timetable_entries te
                          JOIN subjects s ON te.subject_id = s.id
                          JOIN teachers t ON te.teacher_id = t.id
                          JOIN users u ON t.user_id = u.id
                          WHERE te.day_of_week = '$today'
                          ORDER BY te.start_time LIMIT 5";
                $today_timetable = $conn->query($query);
                ?>
                <?php if ($today_timetable && $today_timetable->num_rows > 0): ?>
                    <div class="space-y-3">
                        <?php while($entry = $today_timetable->fetch_assoc()): ?>
                            <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($entry['subject_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($entry['teacher_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium"><?php echo date('h:i A', strtotime($entry['start_time'])); ?></p>
                                    <p class="text-xs text-gray-500">Room: <?php echo $entry['classroom'] ?? 'TBA'; ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-500 py-4">No classes scheduled for today</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Syllabus Coverage -->
    <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-semibold mb-4">Overall Syllabus Coverage</h3>
        <div class="relative pt-1">
            <div class="flex mb-2 items-center justify-between">
                <div>
                    <span class="text-xs font-semibold inline-block text-blue-600">Progress</span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold inline-block text-blue-600"><?php echo $stats['syllabus_coverage']; ?>%</span>
                </div>
            </div>
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200">
                <div style="width:<?php echo $stats['syllabus_coverage']; ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-600"></div>
            </div>
        </div>
        <a href="syllabus/track.php" class="text-blue-600 text-sm">View detailed syllabus tracking →</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>