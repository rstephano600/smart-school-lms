<?php
// =====================================================
// FUNCTIONS FILE - SMART SCHOOL LMS (COMPLETE - NO DUPLICATES)
// =====================================================
// NOTE: The following functions are defined elsewhere:
// - getUserRole()           → auth.php
// - logActivity()           → config.php
// - sanitizeInput()         → config.php
// - generateRandomPassword() → config.php
// =====================================================

// =====================================================
// NOTIFICATION FUNCTIONS
// =====================================================

function getNotificationCount($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data['count'] ?? 0;
}

function getUnreadMessagesCount($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data['count'] ?? 0;
}

// =====================================================
// TIME AGO FUNCTION
// =====================================================

function timeAgo($timestamp) {
    if (empty($timestamp)) {
        return 'Just now';
    }
    
    if (is_string($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    
    if (!$timestamp || $timestamp > time()) {
        return 'Just now';
    }
    
    $time_ago = time() - $timestamp;
    
    if ($time_ago < 1) {
        return 'Just now';
    }
    
    $time_ago = (int)$time_ago;
    
    $time_blocks = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
        1        => 'second'
    ];
    
    foreach ($time_blocks as $seconds => $unit) {
        if ($time_ago >= $seconds) {
            $count = floor($time_ago / $seconds);
            $plural = $count > 1 ? 's' : '';
            return $count . ' ' . $unit . $plural . ' ago';
        }
    }
    
    return 'Just now';
}

function timeAgoSwahili($timestamp) {
    if (empty($timestamp)) {
        return 'Sasa hivi';
    }
    
    if (is_string($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    
    if (!$timestamp || $timestamp > time()) {
        return 'Sasa hivi';
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Sasa hivi';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' dakika iliyopita';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' saa iliyopita';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' siku iliyopita';
    } elseif ($diff < 2592000) {
        return floor($diff / 604800) . ' wiki iliyopita';
    } elseif ($diff < 31536000) {
        return floor($diff / 2592000) . ' mwezi iliyopita';
    } else {
        return floor($diff / 31536000) . ' mwaka iliyopita';
    }
}

// =====================================================
// FORMAT FUNCTIONS
// =====================================================

function formatDate($date, $format = 'M d, Y h:i A') {
    if (empty($date)) {
        return 'N/A';
    }
    
    if (is_string($date)) {
        $timestamp = strtotime($date);
    } else {
        $timestamp = $date;
    }
    
    if (!$timestamp) {
        return 'N/A';
    }
    
    return date($format, $timestamp);
}

function formatDateShort($date) {
    return formatDate($date, 'M d, Y');
}

function formatDateTime($date) {
    return formatDate($date, 'M d, Y h:i A');
}

function formatCurrency($amount, $currency = 'TZS') {
    return $currency . ' ' . number_format($amount, 2);
}

function truncateText($text, $length = 100, $suffix = '...') {
    if (empty($text)) {
        return '';
    }
    
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

// =====================================================
// STUDENT FUNCTIONS
// =====================================================

function getStudentId($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $student = $result->fetch_assoc();
    return $student['id'] ?? null;
}

function getStudentData($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("
        SELECT s.*, c.name as class_name, CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.user_id = ?
    ");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    return $result->fetch_assoc();
}

function getStudentFullName($student_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'Unknown Student';
    }
    
    $query = $conn->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as full_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ");
    $query->bind_param("i", $student_id);
    $query->execute();
    $result = $query->get_result();
    $student = $result->fetch_assoc();
    return $student['full_name'] ?? 'Unknown Student';
}

function getStudentClass($student_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("
        SELECT c.id, c.name, c.code
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.id = ?
    ");
    $query->bind_param("i", $student_id);
    $query->execute();
    $result = $query->get_result();
    return $result->fetch_assoc();
}

function getStudentClassName($student_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'Unknown';
    }
    
    $query = $conn->prepare("
        SELECT c.name 
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.id = ?
    ");
    $query->bind_param("i", $student_id);
    $query->execute();
    $result = $query->get_result();
    $data = $result->fetch_assoc();
    return $data['name'] ?? 'Unknown';
}

// =====================================================
// TEACHER FUNCTIONS
// =====================================================

function getTeacherId($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $teacher = $result->fetch_assoc();
    return $teacher['id'] ?? null;
}

function getTeacherData($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("
        SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ?
    ");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    return $result->fetch_assoc();
}

function getTeacherFullName($teacher_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'Unknown Teacher';
    }
    
    $query = $conn->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as full_name 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?
    ");
    $query->bind_param("i", $teacher_id);
    $query->execute();
    $result = $query->get_result();
    $teacher = $result->fetch_assoc();
    return $teacher['full_name'] ?? 'Unknown Teacher';
}

// =====================================================
// PARENT FUNCTIONS
// =====================================================

function getParentId($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("SELECT id FROM parents WHERE user_id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $parent = $result->fetch_assoc();
    return $parent['id'] ?? null;
}

// =====================================================
// USER FUNCTIONS
// =====================================================

function getUserFullName($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'Unknown User';
    }
    
    $query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    return $user['full_name'] ?? 'Unknown User';
}

function getUserInitials($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'U';
    }
    
    $query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        return 'U';
    }
    
    $name = $user['name'];
    $parts = explode(' ', $name);
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function getUserAvatar($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && !empty($user['avatar']) && file_exists($user['avatar'])) {
        return $user['avatar'];
    }
    
    $name_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
    $name_query->bind_param("i", $user_id);
    $name_query->execute();
    $name_result = $name_query->get_result();
    $name_data = $name_result->fetch_assoc();
    $name = $name_data['name'] ?? 'User';
    
    return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=3b82f6&color=fff&size=128";
}

function getAvatarColor($text) {
    $colors = [
        '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7',
        '#ec4899', '#f43f5e', '#ef4444', '#f97316',
        '#f59e0b', '#eab308', '#22c55e', '#10b981',
        '#06b6d4', '#0ea5e9'
    ];
    $hash = md5($text);
    $index = hexdec(substr($hash, 0, 2)) % count($colors);
    return $colors[$index];
}

// =====================================================
// ROLE CHECK FUNCTIONS
// =====================================================

function isTeacher($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    return $user && $user['role'] === 'teacher';
}

function isStudent($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    return $user && $user['role'] === 'student';
}

function isAdmin($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    return $user && $user['role'] === 'admin';
}

// =====================================================
// CLASS AND SUBJECT FUNCTIONS
// =====================================================

function getClassName($class_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'Unknown Class';
    }
    
    $query = $conn->prepare("SELECT name FROM classes WHERE id = ?");
    $query->bind_param("i", $class_id);
    $query->execute();
    $result = $query->get_result();
    $class = $result->fetch_assoc();
    return $class['name'] ?? 'Unknown Class';
}

function getSubjectName($subject_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 'Unknown Subject';
    }
    
    $query = $conn->prepare("SELECT name FROM subjects WHERE id = ?");
    $query->bind_param("i", $subject_id);
    $query->execute();
    $result = $query->get_result();
    $subject = $result->fetch_assoc();
    return $subject['name'] ?? 'Unknown Subject';
}

// =====================================================
// DISCUSSION GROUP FUNCTIONS
// =====================================================

function isStudentInGroup($student_id, $group_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = $conn->prepare("SELECT id FROM group_members WHERE student_id = ? AND group_id = ?");
    $query->bind_param("ii", $student_id, $group_id);
    $query->execute();
    $result = $query->get_result();
    return $result->num_rows > 0;
}

function getGroupMembersCount($group_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 0;
    }
    
    $query = $conn->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = ?");
    $query->bind_param("i", $group_id);
    $query->execute();
    $result = $query->get_result();
    $data = $result->fetch_assoc();
    return $data['count'] ?? 0;
}

function getGroupMessagesCount($group_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return 0;
    }
    
    $query = $conn->prepare("SELECT COUNT(*) as count FROM group_messages WHERE group_id = ?");
    $query->bind_param("i", $group_id);
    $query->execute();
    $result = $query->get_result();
    $data = $result->fetch_assoc();
    return $data['count'] ?? 0;
}

function getGroupDetails($group_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $query = $conn->prepare("
        SELECT g.*, 
               c.name as class_name,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
               (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id) as message_count
        FROM discussion_groups g
        JOIN classes c ON g.class_id = c.id
        JOIN teachers t ON g.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE g.id = ?
    ");
    $query->bind_param("i", $group_id);
    $query->execute();
    $result = $query->get_result();
    return $result->fetch_assoc();
}

function getStudentDiscussionGroups($student_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return [];
    }
    
    $query = $conn->prepare("
        SELECT g.*, 
               c.name as class_name,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
               (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id) as message_count,
               (SELECT MAX(created_at) FROM group_messages WHERE group_id = g.id) as last_activity,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND student_id = ?) as is_member
        FROM discussion_groups g
        JOIN classes c ON g.class_id = c.id
        JOIN teachers t ON g.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE g.class_id = (SELECT class_id FROM students WHERE id = ?) 
        AND g.is_active = 1
        ORDER BY is_member DESC, g.created_at DESC
    ");
    $query->bind_param("ii", $student_id, $student_id);
    $query->execute();
    $result = $query->get_result();
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }
    return $groups;
}

// =====================================================
// GRADING FUNCTIONS
// =====================================================

function getGrade($score, $max_score = 100) {
    $percentage = ($max_score > 0) ? ($score / $max_score) * 100 : 0;
    
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    if ($percentage >= 40) return 'E';
    return 'F';
}

function getGradePoints($grade) {
    $points = [
        'A' => 5.0,
        'B' => 4.0,
        'C' => 3.0,
        'D' => 2.0,
        'E' => 1.0,
        'F' => 0.0
    ];
    return $points[$grade] ?? 0.0;
}

function getRemark($score, $max_score = 100) {
    $percentage = ($max_score > 0) ? ($score / $max_score) * 100 : 0;
    
    if ($percentage >= 80) return 'Excellent';
    if ($percentage >= 70) return 'Very Good';
    if ($percentage >= 60) return 'Good';
    if ($percentage >= 50) return 'Satisfactory';
    if ($percentage >= 40) return 'Average';
    return 'Needs Improvement';
}

// =====================================================
// GRADE INFO FUNCTION (USED IN RESULTS)
// =====================================================

function getGradeInfo($percentage) {
    if ($percentage >= 75) {
        return ['grade' => 'A', 'status' => 'Excellent', 'color' => 'bg-green-100 text-green-700', 'textColor' => 'text-green-600'];
    } elseif ($percentage >= 65) {
        return ['grade' => 'B', 'status' => 'Very Good', 'color' => 'bg-blue-100 text-blue-700', 'textColor' => 'text-blue-600'];
    } elseif ($percentage >= 45) {
        return ['grade' => 'C', 'status' => 'Good', 'color' => 'bg-cyan-100 text-cyan-700', 'textColor' => 'text-cyan-600'];
    } elseif ($percentage >= 30) {
        return ['grade' => 'D', 'status' => 'Satisfactory', 'color' => 'bg-yellow-100 text-yellow-700', 'textColor' => 'text-yellow-600'];
    } else {
        return ['grade' => 'F', 'status' => 'Needs Improvement', 'color' => 'bg-red-100 text-red-700', 'textColor' => 'text-red-600'];
    }
}

// =====================================================
// AUTO-GRADE FUNCTION
// =====================================================

function autoGradeExam($submission_id) {
    global $conn;
    
    $result = [
        'success' => false,
        'total_questions' => 0,
        'correct_count' => 0,
        'total_marks' => 0,
        'earned_marks' => 0,
        'percentage' => 0,
        'grade' => 'F',
        'grade_text' => 'Needs Improvement',
        'details' => []
    ];
    
    $sub_query = $conn->prepare("
        SELECT es.*, te.total_marks, te.passing_marks
        FROM exam_submissions es
        JOIN teacher_exams te ON es.exam_id = te.id
        WHERE es.id = ?
    ");
    $sub_query->bind_param("i", $submission_id);
    $sub_query->execute();
    $submission = $sub_query->get_result()->fetch_assoc();
    
    if (!$submission) {
        return $result;
    }
    
    $questions_query = $conn->prepare("
        SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_number
    ");
    $questions_query->bind_param("i", $submission['exam_id']);
    $questions_query->execute();
    $all_questions = $questions_query->get_result();
    
    $questions_data = [];
    while ($q = $all_questions->fetch_assoc()) {
        $questions_data[$q['id']] = [
            'question_text' => $q['question_text'],
            'question_type' => $q['question_type'],
            'correct_answer' => $q['correct_answer'] ?? '',
            'marks' => $q['marks'],
            'options' => $q['options']
        ];
    }
    
    $answers = !empty($submission['answers']) ? json_decode($submission['answers'], true) : [];
    
    $total_questions = 0;
    $correct_count = 0;
    $earned_marks = 0;
    $total_marks = $submission['total_marks'] ?? 0;
    $details = [];
    
    foreach ($questions_data as $q_id => $q_info) {
        $total_questions++;
        $student_answer = $answers[$q_id] ?? 'Not answered';
        $correct_answer = $q_info['correct_answer'];
        $is_correct = false;
        $earned = 0;
        
        switch ($q_info['question_type']) {
            case 'mcq':
                if (is_array($student_answer)) {
                    $student_answer_str = implode(', ', $student_answer);
                } else {
                    $student_answer_str = $student_answer;
                }
                $is_correct = strtoupper(trim($student_answer_str)) == strtoupper(trim($correct_answer));
                break;
                
            case 'truefalse':
                if (is_array($student_answer)) {
                    $student_answer_str = implode(', ', $student_answer);
                } else {
                    $student_answer_str = $student_answer;
                }
                $is_correct = strtolower(trim($student_answer_str)) == strtolower(trim($correct_answer));
                break;
                
            case 'fill_blanks':
                if (!empty($correct_answer) && !empty($student_answer)) {
                    $correct_keywords = array_map('trim', explode(',', strtolower($correct_answer)));
                    $student_answer_lower = strtolower(trim($student_answer));
                    $is_correct = false;
                    foreach ($correct_keywords as $keyword) {
                        if (!empty($keyword) && strpos($student_answer_lower, $keyword) !== false) {
                            $is_correct = true;
                            break;
                        }
                    }
                }
                break;
                
            case 'short_answer':
                if (!empty($correct_answer) && !empty($student_answer)) {
                    $correct_keywords = array_map('trim', explode(',', strtolower($correct_answer)));
                    $student_answer_lower = strtolower(trim($student_answer));
                    $match_count = 0;
                    foreach ($correct_keywords as $keyword) {
                        if (!empty($keyword) && strpos($student_answer_lower, $keyword) !== false) {
                            $match_count++;
                        }
                    }
                    $is_correct = ($match_count / count($correct_keywords)) >= 0.5;
                }
                break;
                
            case 'essay':
                if (!empty($correct_answer) && !empty($student_answer)) {
                    $correct_keywords = array_map('trim', explode(',', strtolower($correct_answer)));
                    $student_answer_lower = strtolower(trim($student_answer));
                    $match_count = 0;
                    foreach ($correct_keywords as $keyword) {
                        if (!empty($keyword) && strpos($student_answer_lower, $keyword) !== false) {
                            $match_count++;
                        }
                    }
                    $is_correct = ($match_count / count($correct_keywords)) >= 0.3;
                }
                break;
                
            default:
                $is_correct = false;
        }
        
        if ($is_correct) {
            $correct_count++;
            $earned = $q_info['marks'];
        }
        $earned_marks += $earned;
        
        $details[] = [
            'question_id' => $q_id,
            'is_correct' => $is_correct,
            'earned' => $earned,
            'student_answer' => $student_answer,
            'correct_answer' => $correct_answer
        ];
    }
    
    $percentage = $total_marks > 0 ? round(($earned_marks / $total_marks) * 100, 1) : 0;
    $grade_info = getGradeInfo($percentage);
    
    $update = $conn->prepare("
        UPDATE exam_submissions 
        SET 
            total_score = ?,
            percentage = ?,
            grade = ?,
            auto_graded = 1,
            auto_score = ?,
            auto_percentage = ?,
            auto_grade = ?,
            is_graded = 1,
            graded_at = NOW()
        WHERE id = ?
    ");
    $update->bind_param(
        "dddddii", 
        $earned_marks, 
        $percentage, 
        $grade_info['grade'], 
        $earned_marks, 
        $percentage, 
        $grade_info['grade'], 
        $submission_id
    );
    $update->execute();
    
    return [
        'success' => true,
        'total_questions' => $total_questions,
        'correct_count' => $correct_count,
        'total_marks' => $total_marks,
        'earned_marks' => $earned_marks,
        'percentage' => $percentage,
        'grade' => $grade_info['grade'],
        'grade_text' => $grade_info['status'],
        'details' => $details
    ];
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

function generateUniqueCode($prefix, $length = 6) {
    $code = $prefix . strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length));
    return $code;
}

// ✅ generateRandomPassword() is defined in config.php - DO NOT REDECLARE

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $phone);
}

// ✅ sanitizeInput() is defined in config.php - DO NOT REDECLARE

function tableExists($table_name) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = "SHOW TABLES LIKE '$table_name'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

// ✅ logActivity() is defined in config.php - DO NOT REDECLARE

function isUserOnline($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = $conn->prepare("
        SELECT id FROM user_online_status 
        WHERE user_id = ? AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    return $result->num_rows > 0;
}

function updateOnlineStatus($user_id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $query = $conn->prepare("
        INSERT INTO user_online_status (user_id, last_activity, status) 
        VALUES (?, NOW(), 'online')
        ON DUPLICATE KEY UPDATE last_activity = NOW(), status = 'online'
    ");
    $query->bind_param("i", $user_id);
    return $query->execute();
}

// ✅ getUserRole() is defined in auth.php - DO NOT REDECLARE
?>