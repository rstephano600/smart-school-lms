<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireRole('teacher');

$page_title = 'Upload Material';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get teacher ID
$teacher_query = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_query->bind_param("i", $_SESSION['user_id']);
$teacher_query->execute();
$teacher = $teacher_query->get_result()->fetch_assoc();
$teacher_id = $teacher['id'] ?? 0;

// Get classes and subjects taught by this teacher
$classes_subjects = $conn->prepare("
    SELECT DISTINCT c.id as class_id, c.name as class_name, 
           s.id as subject_id, s.name as subject_name
    FROM class_subject cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, s.name
");
$classes_subjects->bind_param("i", $teacher_id);
$classes_subjects->execute();
$teaching = $classes_subjects->get_result();

$error = '';
$success = '';

// Initialize POST variables with default values
$post_title = $_POST['title'] ?? '';
$post_description = $_POST['description'] ?? '';
$post_class_id = $_POST['class_id'] ?? '';
$post_subject_id = $_POST['subject_id'] ?? '';
$post_type = $_POST['type'] ?? '';
$post_content_type = $_POST['content_type'] ?? 'file';
$post_youtube_url = $_POST['youtube_url'] ?? '';
$post_html_content = $_POST['html_content'] ?? '';

// Only process if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $title = sanitizeInput($post_title);
    $description = sanitizeInput($post_description);
    $class_id = intval($post_class_id);
    $subject_id = intval($post_subject_id);
    $type = $post_type;
    $content_type = $post_content_type;
    $youtube_url = sanitizeInput($post_youtube_url);
    $html_content = $_POST['html_content'] ?? '';
    
    if (empty($title) || empty($class_id) || empty($subject_id)) {
        $error = "Please fill all required fields";
    } elseif ($content_type == 'youtube') {
        // Handle YouTube URL
        if (empty($youtube_url)) {
            $error = "Please enter a YouTube URL";
        } else {
            // Extract video ID from YouTube URL
            $video_id = '';
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $youtube_url, $match)) {
                $video_id = $match[1];
                $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                
                $insert = $conn->prepare("
                    INSERT INTO learning_materials (title, description, subject_id, class_id, file_url, type, uploaded_by, youtube_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $file_url = 'youtube:' . $embed_url;
                $insert->bind_param("ssiissss", $title, $description, $subject_id, $class_id, $file_url, $type, $_SESSION['user_id'], $youtube_url);
                
                if ($insert->execute()) {
                    logActivity($_SESSION['user_id'], 'uploaded YouTube video', 'learning_materials', $insert->insert_id);
                    $success = "YouTube video added successfully!";
                    $post_title = $post_description = $post_youtube_url = '';
                    $post_class_id = $post_subject_id = '';
                } else {
                    $error = "Failed to save: " . $conn->error;
                }
            } else {
                $error = "Invalid YouTube URL. Please use a valid YouTube link.";
            }
        }
    } elseif ($content_type == 'html') {
        // Handle HTML content
        if (empty($html_content)) {
            $error = "Please enter HTML content";
        } else {
            $insert = $conn->prepare("
                INSERT INTO learning_materials (title, description, subject_id, class_id, file_url, type, uploaded_by, html_content) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $file_url = 'html:' . $title;
            $insert->bind_param("ssiissss", $title, $description, $subject_id, $class_id, $file_url, $type, $_SESSION['user_id'], $html_content);
            
            if ($insert->execute()) {
                logActivity($_SESSION['user_id'], 'uploaded HTML content', 'learning_materials', $insert->insert_id);
                $success = "HTML content added successfully!";
                $post_title = $post_description = $post_html_content = '';
                $post_class_id = $post_subject_id = '';
            } else {
                $error = "Failed to save: " . $conn->error;
            }
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        // Upload file
        $upload_result = uploadFile($_FILES['file'], MATERIALS_PATH, ['pdf', 'docx', 'pptx', 'mp4', 'jpg', 'png', 'zip', 'html', 'htm']);
        
        if ($upload_result['success']) {
            $file_url = 'uploads/materials/' . $upload_result['filename'];
            
            $insert = $conn->prepare("
                INSERT INTO learning_materials (title, description, subject_id, class_id, file_url, type, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param("ssiissi", $title, $description, $subject_id, $class_id, $file_url, $type, $_SESSION['user_id']);
            
            if ($insert->execute()) {
                logActivity($_SESSION['user_id'], 'uploaded learning material', 'learning_materials', $insert->insert_id);
                $success = "Material uploaded successfully!";
                $post_title = $post_description = '';
                $post_class_id = $post_subject_id = '';
            } else {
                $error = "Failed to save to database: " . $conn->error;
            }
        } else {
            $error = $upload_result['error'];
        }
    } else {
        $error = "Please select a file or enter content";
    }
}
?>

<!-- Include TinyMCE Editor -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Upload Learning Material</h1>
            <p class="text-gray-500 mt-1">Share notes, videos, presentations, or HTML content with your students</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="submit" value="1">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($post_title); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                        <select name="class_id" id="class_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Select Class</option>
                            <?php 
                            $teaching->data_seek(0);
                            $classes_list = [];
                            while($item = $teaching->fetch_assoc()):
                                if (!in_array($item['class_id'], array_column($classes_list, 'id'))):
                                    $classes_list[] = ['id' => $item['class_id'], 'name' => $item['class_name']];
                                endif;
                            endwhile;
                            foreach($classes_list as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $post_class_id == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                        <select name="subject_id" id="subject_id" required class="w-full border rounded-lg px-3 py-2">
                            <option value="">Select Subject</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Material Type *</label>
                    <select name="type" required class="w-full border rounded-lg px-3 py-2">
                        <option value="">Select Type</option>
                        <option value="note" <?php echo $post_type == 'note' ? 'selected' : ''; ?>>📝 Notes</option>
                        <option value="video" <?php echo $post_type == 'video' ? 'selected' : ''; ?>>🎬 Video</option>
                        <option value="presentation" <?php echo $post_type == 'presentation' ? 'selected' : ''; ?>>📊 Presentation</option>
                        <option value="resource" <?php echo $post_type == 'resource' ? 'selected' : ''; ?>>📚 Resource</option>
                        <option value="html" <?php echo $post_type == 'html' ? 'selected' : ''; ?>>🌐 HTML Content</option>
                    </select>
                </div>

                <!-- Content Type Selector -->
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Choose Content Type</label>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="content_type" value="file" <?php echo ($post_content_type ?? 'file') == 'file' ? 'checked' : ''; ?> onchange="toggleContentType('file')">
                            <span>📁 Upload File</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="content_type" value="youtube" <?php echo ($post_content_type ?? '') == 'youtube' ? 'checked' : ''; ?> onchange="toggleContentType('youtube')">
                            <span>▶️ YouTube Video</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="content_type" value="html" <?php echo ($post_content_type ?? '') == 'html' ? 'checked' : ''; ?> onchange="toggleContentType('html')">
                            <span>🌐 HTML Content</span>
                        </label>
                    </div>
                </div>

                <!-- File Upload Section -->
                <div id="fileSection" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">File *</label>
                    <input type="file" name="file" accept=".pdf,.docx,.pptx,.mp4,.jpg,.png,.zip,.html,.htm"
                           class="w-full border rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">Allowed: PDF, DOCX, PPTX, MP4, JPG, PNG, ZIP, HTML (Max 25MB)</p>
                </div>

                <!-- YouTube Section -->
                <div id="youtubeSection" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                    <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=VIDEO_ID" 
                           value="<?php echo htmlspecialchars($post_youtube_url); ?>"
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Paste any YouTube video URL. Students can watch directly in the LMS.</p>
                </div>

                <!-- HTML Content Section with WYSIWYG Editor -->
                <div id="htmlSection" class="mb-4" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">HTML Content *</label>
                    <textarea name="html_content" id="htmlEditor" rows="10" 
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Write your HTML content here..."><?php echo htmlspecialchars($post_html_content); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use the toolbar to format your content. Supports text, images, videos, tables, and more.</p>
                    
                    <!-- Quick Tools -->
                    <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm font-medium text-blue-800 mb-2">Quick Tools:</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="insertHTML('<h1>Heading 1</h1>')" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">H1</button>
                            <button type="button" onclick="insertHTML('<h2>Heading 2</h2>')" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">H2</button>
                            <button type="button" onclick="insertHTML('<h3>Heading 3</h3>')" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">H3</button>
                            <button type="button" onclick="insertHTML('<p>Paragraph text here...</p>')" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">Paragraph</button>
                            <button type="button" onclick="insertHTML('<ul><li>Item 1</li><li>Item 2</li></ul>')" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">Bullet List</button>
                            <button type="button" onclick="insertHTML('<ol><li>Item 1</li><li>Item 2</li></ol>')" class="px-3 py-1 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">Numbered List</button>
                            <button type="button" onclick="insertHTML('<img src=\'https://via.placeholder.com/300x200\' alt=\'Image\' style=\'max-width:100%;\'>')" class="px-3 py-1 bg-purple-600 text-white rounded text-sm hover:bg-purple-700">Image</button>
                            <button type="button" onclick="insertHTML('<a href=\'https://example.com\' target=\'_blank\'>Link Text</a>')" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">Link</button>
                            <button type="button" onclick="insertHTML('<div style=\'background:#f0f0f0;padding:15px;border-radius:8px;\'>Note box here...</div>')" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">Note Box</button>
                            <button type="button" onclick="insertHTML('<hr>')" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">Divider</button>
                            <button type="button" onclick="insertHTML('<br>')" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">Line Break</button>
                        </div>
                    </div>
                    
                    <!-- Templates -->
                    <div class="mt-3 p-3 bg-green-50 rounded-lg">
                        <p class="text-sm font-medium text-green-800 mb-2">Templates:</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="loadTemplate('note')" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">📝 Note Template</button>
                            <button type="button" onclick="loadTemplate('lesson')" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">📚 Lesson Template</button>
                            <button type="button" onclick="loadTemplate('exercise')" class="px-3 py-1 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">✏️ Exercise Template</button>
                            <button type="button" onclick="loadTemplate('summary')" class="px-3 py-1 bg-purple-600 text-white rounded text-sm hover:bg-purple-700">📋 Summary Template</button>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($post_description); ?></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <a href="index.php" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:shadow-lg transition-all">
                        <i class="fas fa-upload mr-2"></i> Upload Material
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load subjects when class is selected
document.getElementById('class_id').addEventListener('change', function() {
    const classId = this.value;
    const subjectSelect = document.getElementById('subject_id');
    
    if (classId) {
        <?php 
        $teaching->data_seek(0);
        $subjects_by_class = [];
        while($item = $teaching->fetch_assoc()) {
            $subjects_by_class[$item['class_id']][] = ['id' => $item['subject_id'], 'name' => $item['subject_name']];
        }
        ?>
        const subjectsByClass = <?php echo json_encode($subjects_by_class); ?>;
        
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        if (subjectsByClass[classId]) {
            subjectsByClass[classId].forEach(subject => {
                subjectSelect.innerHTML += `<option value="${subject.id}">${subject.name}</option>`;
            });
        }
    } else {
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    }
});

// Toggle content type visibility
function toggleContentType(type) {
    document.getElementById('fileSection').style.display = type === 'file' ? 'block' : 'none';
    document.getElementById('youtubeSection').style.display = type === 'youtube' ? 'block' : 'none';
    document.getElementById('htmlSection').style.display = type === 'html' ? 'block' : 'none';
}

// Trigger on page load
if (document.getElementById('class_id').value) {
    document.getElementById('class_id').dispatchEvent(new Event('change'));
}

// Set initial content type
const initialContentType = document.querySelector('input[name="content_type"]:checked');
if (initialContentType) {
    toggleContentType(initialContentType.value);
}

// Insert HTML at cursor position
function insertHTML(html) {
    const editor = document.getElementById('htmlEditor');
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const text = editor.value;
    editor.value = text.substring(0, start) + html + text.substring(end);
    editor.focus();
    editor.selectionStart = editor.selectionEnd = start + html.length;
}

// Load templates
function loadTemplate(type) {
    const templates = {
        'note': `
<h2>📝 Notes</h2>
<p><strong>Topic:</strong> [Insert topic here]</p>
<h3>Key Points:</h3>
<ul>
    <li>Point 1</li>
    <li>Point 2</li>
    <li>Point 3</li>
</ul>
<h3>Summary:</h3>
<p>Summary goes here...</p>
        `,
        'lesson': `
<h2>📚 Lesson Content</h2>
<h3>Learning Objectives:</h3>
<ul>
    <li>Objective 1</li>
    <li>Objective 2</li>
    <li>Objective 3</li>
</ul>
<h3>Main Content:</h3>
<p>Lesson content goes here...</p>
<h3>Key Takeaways:</h3>
<ul>
    <li>Takeaway 1</li>
    <li>Takeaway 2</li>
</ul>
        `,
        'exercise': `
<h2>✏️ Exercise / Assignment</h2>
<h3>Instructions:</h3>
<p>Read the questions carefully and answer all of them.</p>
<h4>Question 1:</h4>
<p>[Question text]</p>
<h4>Question 2:</h4>
<p>[Question text]</p>
<h4>Question 3:</h4>
<p>[Question text]</p>
        `,
        'summary': `
<h2>📋 Summary</h2>
<h3>What We Learned:</h3>
<ul>
    <li>Key concept 1</li>
    <li>Key concept 2</li>
    <li>Key concept 3</li>
</ul>
<h3>Important Formulas/Definitions:</h3>
<ul>
    <li>Definition 1</li>
    <li>Definition 2</li>
</ul>
<h3>Next Steps:</h3>
<p>What to review next...</p>
        `
    };
    
    const editor = document.getElementById('htmlEditor');
    editor.value = templates[type] || '';
}
</script>

<?php include '../../includes/footer.php'; ?>