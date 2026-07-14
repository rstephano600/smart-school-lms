<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/code-executor.php';
requireRole('student');

$page_title = 'Coding Playground';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';

// Get available exercises
$exercises = $conn->query("SELECT * FROM coding_exercises ORDER BY difficulty, title");
?>

<style>
/* Console Styling - Interactive like Thonny/DevC++ */
.console-container {
    background: #1a1a2e;
    border-radius: 12px;
    padding: 0;
    overflow: hidden;
    font-family: 'Consolas', 'Courier New', monospace;
    height: 100%;
    min-height: 500px;
    display: flex;
    flex-direction: column;
}
.console-header {
    background: #2d2d44;
    padding: 8px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #3d3d5c;
    flex-shrink: 0;
}
.console-header .title {
    color: #8888aa;
    font-size: 12px;
    font-weight: bold;
}
.console-header .dots {
    display: flex;
    gap: 6px;
}
.console-header .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.console-header .dot.red { background: #ff5f56; }
.console-header .dot.yellow { background: #ffbd2e; }
.console-header .dot.green { background: #27c93f; }
.console-body {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    color: #c8c8d4;
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
    min-height: 400px;
    max-height: 450px;
}
.console-body .prompt-text {
    color: #10b981;
}
.console-body .output-text {
    color: #f0f0f0;
}
.console-body .error-text {
    color: #ef4444;
}
.console-body .input-line {
    display: flex;
    align-items: center;
    gap: 8px;
}
.console-body .input-line .input-label {
    color: #10b981;
}
.console-body .input-line input {
    background: transparent;
    border: none;
    outline: none;
    color: #f0f0f0;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 14px;
    flex: 1;
}
.console-body .input-line input:focus {
    border-bottom: 2px solid #10b981;
}
.cursor-blink {
    display: inline-block;
    width: 8px;
    height: 16px;
    background: #10b981;
    animation: blink 1s step-end infinite;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
</style>

<div class="ml-64 mt-16 p-6 bg-gray-50 min-h-screen">
    <div class="max-w-full mx-auto">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">💻 Coding Playground</h1>
                <p class="text-gray-500 mt-1">Write, run, and test your code online</p>
            </div>
            <div class="flex gap-2">
                <button onclick="runCode()" class="bg-gradient-to-r from-green-500 to-teal-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-play mr-2"></i> Run
                </button>
                <button onclick="resetCode()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button onclick="clearConsole()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-eraser mr-2"></i> Clear
                </button>
            </div>
        </div>

        <!-- Language & Controls -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                    <select id="language" class="w-full border rounded-lg px-3 py-2">
                        <?php foreach($supported_languages as $key => $name): ?>
                            <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expected Output (Optional)</label>
                    <input type="text" id="expectedOutput" placeholder="Expected result..." 
                           class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="md:col-span-2 flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quick Examples</label>
                        <select id="exampleSelect" class="w-full border rounded-lg px-3 py-2" onchange="loadExample()">
                            <option value="">-- Select Example --</option>
                            <option value="hello">Hello World</option>
                            <option value="input">Input/Output Example</option>
                            <option value="math">Math Operations</option>
                            <option value="loop">Loop Example</option>
                            <option value="function">Function Example</option>
                            <option value="array">Array Example</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Editor & Console Area -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Code Editor -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-2 bg-gray-100 border-b flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">
                        <i class="fas fa-code mr-2"></i> Editor
                    </span>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span id="lineCount">Lines: 0</span>
                        <span id="cursorPosition">Ln 1, Col 1</span>
                    </div>
                </div>
                <textarea id="codeEditor" class="w-full h-[500px] p-4 font-mono text-sm focus:outline-none resize-none"
                          placeholder="Write your code here..." spellcheck="false"></textarea>
            </div>

            <!-- Interactive Console -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="console-container">
                    <div class="console-header">
                        <span class="title">
                            <i class="fas fa-terminal mr-2"></i> Console (Interactive)
                        </span>
                        <div class="dots">
                            <span class="dot red"></span>
                            <span class="dot yellow"></span>
                            <span class="dot green"></span>
                        </div>
                    </div>
                    <div id="consoleBody" class="console-body">
                        <div class="output-text">// Welcome to Interactive Coding Playground</div>
                        <div class="output-text">// Write your code and click 'Run'</div>
                        <div class="output-text">// </div>
                        <div class="prompt-text">$ <span class="cursor-blink"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exercises Section -->
        <div class="mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-tasks text-blue-500 mr-2"></i> Coding Exercises
                </h3>
                <span class="text-xs text-gray-400">Click "Try" to load exercise</span>
            </div>
            <div class="divide-y max-h-60 overflow-y-auto">
                <?php if ($exercises && $exercises->num_rows > 0): ?>
                    <?php while($ex = $exercises->fetch_assoc()): 
                        $difficulty_color = $ex['difficulty'] == 'easy' ? 'bg-green-100 text-green-700' : ($ex['difficulty'] == 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                    ?>
                        <div class="p-4 hover:bg-gray-50 flex justify-between items-center">
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($ex['title']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($ex['description']); ?></p>
                                <div class="flex gap-2 mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full <?php echo $difficulty_color; ?>">
                                        <?php echo ucfirst($ex['difficulty']); ?>
                                    </span>
                                    <?php if($ex['language']): ?>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"><?php echo ucfirst($ex['language']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button onclick="loadExercise(<?php echo $ex['id']; ?>)" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                <i class="fas fa-arrow-right mr-1"></i> Try
                            </button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">No exercises available yet</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-xl p-8 text-center">
                <div class="loader mx-auto mb-4"></div>
                <p class="text-gray-700 font-medium">Executing code...</p>
                <p class="text-sm text-gray-500">Please wait</p>
            </div>
        </div>
    </div>
</div>

<style>
.loader {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3b82f6;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// ============================================
// CODE EDITOR FUNCTIONS
// ============================================

// Default boilerplate codes
const boilerplate = <?php echo json_encode($language_boilerplate); ?>;

// DOM Elements
const editor = document.getElementById('codeEditor');
const languageSelect = document.getElementById('language');
const expectedOutputInput = document.getElementById('expectedOutput');
const consoleBody = document.getElementById('consoleBody');
const loadingOverlay = document.getElementById('loadingOverlay');
const lineCount = document.getElementById('lineCount');
const cursorPosition = document.getElementById('cursorPosition');

// ============================================
// LOAD DEFAULT CODE
// ============================================
function loadDefaultCode() {
    const lang = languageSelect.value;
    editor.value = boilerplate[lang] || '// Write your code here';
    updateEditorInfo();
}

// Load default on page load
loadDefaultCode();

// Change language - update boilerplate
languageSelect.addEventListener('change', loadDefaultCode);

// Update editor info
function updateEditorInfo() {
    const lines = editor.value.split('\n').length;
    lineCount.textContent = 'Lines: ' + lines;
    
    const pos = editor.selectionStart;
    const text = editor.value.substring(0, pos);
    const lines_up_to_cursor = text.split('\n');
    const col = lines_up_to_cursor[lines_up_to_cursor.length - 1].length + 1;
    cursorPosition.textContent = `Ln ${lines_up_to_cursor.length}, Col ${col}`;
}

editor.addEventListener('input', updateEditorInfo);
editor.addEventListener('click', updateEditorInfo);
editor.addEventListener('keyup', updateEditorInfo);

// ============================================
// CONSOLE FUNCTIONS
// ============================================
function consolePrint(text, type = 'output-text') {
    const div = document.createElement('div');
    div.className = type;
    div.textContent = text;
    consoleBody.appendChild(div);
    consoleBody.scrollTop = consoleBody.scrollHeight;
}

function consolePrintHTML(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    consoleBody.appendChild(div);
    consoleBody.scrollTop = consoleBody.scrollHeight;
}

function clearConsole() {
    consoleBody.innerHTML = `
        <div class="output-text">// Console cleared</div>
        <div class="prompt-text">$ <span class="cursor-blink"></span></div>
    `;
}

function showCursor() {
    const cursorDiv = document.createElement('div');
    cursorDiv.className = 'prompt-text';
    cursorDiv.innerHTML = '$ <span class="cursor-blink"></span>';
    consoleBody.appendChild(cursorDiv);
    consoleBody.scrollTop = consoleBody.scrollHeight;
}

// ============================================
// INTERACTIVE INPUT HANDLER
// ============================================
function getInputFromUser(promptText) {
    return new Promise((resolve) => {
        // Remove existing cursor
        const lastChild = consoleBody.lastElementChild;
        if (lastChild && lastChild.classList.contains('prompt-text')) {
            lastChild.remove();
        }
        
        // Show prompt
        const container = document.createElement('div');
        container.className = 'input-line';
        container.innerHTML = `
            <span class="input-label">${promptText}</span>
            <input type="text" id="consoleInput" autofocus>
        `;
        consoleBody.appendChild(container);
        consoleBody.scrollTop = consoleBody.scrollHeight;
        
        const input = document.getElementById('consoleInput');
        input.focus();
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const value = this.value;
                // Remove input line
                this.parentElement.remove();
                // Show the input value in console
                consolePrint(value, 'output-text');
                resolve(value);
            }
        });
    });
}

// ============================================
// RUN CODE WITH INTERACTIVE INPUT
// ============================================
async function runCode() {
    const language = languageSelect.value;
    const code = editor.value;
    const expectedOutput = expectedOutputInput.value;
    
    if (!code.trim()) {
        alert('Please write some code first');
        return;
    }
    
    // Clear console
    consoleBody.innerHTML = `
        <div class="output-text">// Executing code...</div>
        <div class="output-text">// Language: ${language}</div>
        <div class="output-text">// </div>
    `;
    
    // Check if code contains input() or scanf() - need interactive input
    const hasInput = code.includes('input(') || 
                     code.includes('scanf') || 
                     code.includes('cin >>') ||
                     code.includes('Scanner') ||
                     code.includes('readline');
    
    let stdin = '';
    
    if (hasInput) {
        // For Python input() - collect all inputs
        consolePrintHTML('<span class="prompt-text">🔹 Interactive Mode: Enter input values when prompted</span>');
        
        // Parse input prompts from code
        const inputPrompts = [];
        if (language === 'python') {
            const matches = code.match(/input\s*\(([^)]*)\)/g);
            if (matches) {
                for (const match of matches) {
                    const prompt = match.match(/input\s*\(\s*["\']([^"\']*)["\']\s*\)/);
                    if (prompt) {
                        inputPrompts.push(prompt[1]);
                    } else {
                        inputPrompts.push('Enter value: ');
                    }
                }
            }
        } else if (language === 'c' || language === 'cpp') {
            // For C/C++ scanf/printf patterns
            const printfMatches = code.match(/printf\s*\(\s*["\']([^"\']*)["\']\s*\)/g);
            const scanfMatches = code.match(/scanf/g);
            if (scanfMatches) {
                // Find printf prompts before scanf
                const lines = code.split('\n');
                for (let i = 0; i < lines.length; i++) {
                    if (lines[i].includes('scanf') || lines[i].includes('cin')) {
                        // Look backwards for printf
                        for (let j = i - 1; j >= Math.max(0, i - 3); j--) {
                            const match = lines[j].match(/printf\s*\(\s*["\']([^"\']*)["\']\s*\)/);
                            if (match) {
                                inputPrompts.push(match[1]);
                                break;
                            }
                        }
                        if (inputPrompts.length <= inputPrompts.length - 1) {
                            inputPrompts.push('Enter value: ');
                        }
                    }
                }
            }
        }
        
        // If no prompts found, use default
        if (inputPrompts.length === 0) {
            inputPrompts.push('Enter value: ');
        }
        
        // Collect all inputs
        const inputValues = [];
        for (const prompt of inputPrompts) {
            // Remove newline characters from prompt
            const cleanPrompt = prompt.replace(/\\n/g, '').trim();
            const value = await getInputFromUser(cleanPrompt);
            inputValues.push(value);
        }
        
        // Join inputs with newline for stdin
        stdin = inputValues.join('\n');
    } else {
        // No input needed
        stdin = '';
    }
    
    // Show loading
    loadingOverlay.classList.remove('hidden');
    
    // Prepare data
    const data = {
        language: language,
        code: code,
        stdin: stdin,
        expectedOutput: expectedOutput
    };
    
    // Send request
    try {
        const response = await fetch('../../api/execute-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        loadingOverlay.classList.add('hidden');
        displayResultInConsole(result);
    } catch (error) {
        loadingOverlay.classList.add('hidden');
        consolePrint(`❌ Error: ${error.message}`, 'error-text');
        showCursor();
    }
}

// ============================================
// DISPLAY RESULT IN CONSOLE
// ============================================
function displayResultInConsole(result) {
    // Remove cursor if exists
    const lastChild = consoleBody.lastElementChild;
    if (lastChild && lastChild.classList.contains('prompt-text')) {
        lastChild.remove();
    }
    
    consolePrint('', 'output-text');
    
    if (result.success) {
        if (result.stdout) {
            consolePrint('📤 Output:', 'prompt-text');
            consolePrint(result.stdout, 'output-text');
        }
        
        if (result.stderr) {
            consolePrint('⚠️ Warnings/Errors:', 'prompt-text');
            consolePrint(result.stderr, 'error-text');
        }
        
        consolePrint(`⏱️ Execution: ${result.execution_time}ms | 💾 Memory: ${result.memory_used}KB`, 'output-text');
        
        // Check expected output
        const expectedOutput = expectedOutputInput.value;
        if (expectedOutput) {
            const isMatch = result.stdout && result.stdout.trim() === expectedOutput.trim();
            consolePrint('📊 Expected Output:', 'prompt-text');
            consolePrint(expectedOutput, 'output-text');
            if (isMatch) {
                consolePrint('✅ Test Passed! 🎉', 'prompt-text');
            } else {
                consolePrint('❌ Test Failed!', 'error-text');
            }
        }
    } else {
        consolePrint('❌ Error:', 'error-text');
        consolePrint(result.error || 'Unknown error', 'error-text');
    }
    
    // Add cursor
    showCursor();
}

// ============================================
// RESET CODE
// ============================================
function resetCode() {
    if (confirm('Reset code to default?')) {
        loadDefaultCode();
        expectedOutputInput.value = '';
        clearConsole();
    }
}

// ============================================
// LOAD EXAMPLE
// ============================================
function loadExample() {
    const example = document.getElementById('exampleSelect').value;
    const lang = languageSelect.value;
    const examples = {
        'hello': {
            'python': 'print("Hello, World!")',
            'c': '#include <stdio.h>\nint main() {\n    printf("Hello, World!\\n");\n    return 0;\n}',
            'cpp': '#include <iostream>\nusing namespace std;\nint main() {\n    cout << "Hello, World!" << endl;\n    return 0;\n}',
            'javascript': 'console.log("Hello, World!");',
            'java': 'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello, World!");\n    }\n}',
            'php': '<?php\necho "Hello, World!\\n";\n?>'
        },
        'input': {
            'python': 'num1 = int(input("Enter the first number: "))\nnum2 = int(input("Enter the second number: "))\nsum = num1 + num2\nprint("The sum is:", sum)',
            'c': '#include <stdio.h>\nint main() {\n    int num1, num2;\n    printf("Enter first number: ");\n    scanf("%d", &num1);\n    printf("Enter second number: ");\n    scanf("%d", &num2);\n    int sum = num1 + num2;\n    printf("The sum is: %d\\n", sum);\n    return 0;\n}',
            'cpp': '#include <iostream>\nusing namespace std;\nint main() {\n    int num1, num2;\n    cout << "Enter first number: ";\n    cin >> num1;\n    cout << "Enter second number: ";\n    cin >> num2;\n    int sum = num1 + num2;\n    cout << "The sum is: " << sum << endl;\n    return 0;\n}'
        },
        'math': {
            'python': 'a = 10\nb = 5\nprint(f"Addition: {a + b}")\nprint(f"Subtraction: {a - b}")\nprint(f"Multiplication: {a * b}")\nprint(f"Division: {a / b}")',
            'c': '#include <stdio.h>\nint main() {\n    int a = 10, b = 5;\n    printf("Addition: %d\\n", a + b);\n    printf("Subtraction: %d\\n", a - b);\n    printf("Multiplication: %d\\n", a * b);\n    printf("Division: %d\\n", a / b);\n    return 0;\n}',
            'cpp': '#include <iostream>\nusing namespace std;\nint main() {\n    int a = 10, b = 5;\n    cout << "Addition: " << a + b << endl;\n    cout << "Subtraction: " << a - b << endl;\n    cout << "Multiplication: " << a * b << endl;\n    cout << "Division: " << a / b << endl;\n    return 0;\n}'
        },
        'loop': {
            'python': 'for i in range(1, 6):\n    print(f"Number: {i}")',
            'c': '#include <stdio.h>\nint main() {\n    for(int i = 1; i <= 5; i++) {\n        printf("Number: %d\\n", i);\n    }\n    return 0;\n}',
            'cpp': '#include <iostream>\nusing namespace std;\nint main() {\n    for(int i = 1; i <= 5; i++) {\n        cout << "Number: " << i << endl;\n    }\n    return 0;\n}'
        }
    };
    
    if (example && examples[example] && examples[example][lang]) {
        editor.value = examples[example][lang];
        updateEditorInfo();
        clearConsole();
    } else if (example) {
        const defaultExample = examples[example];
        if (defaultExample) {
            const keys = Object.keys(defaultExample);
            if (keys.length > 0) {
                editor.value = defaultExample[keys[0]];
                updateEditorInfo();
                clearConsole();
            }
        }
    }
}

// ============================================
// LOAD EXERCISE
// ============================================
function loadExercise(exerciseId) {
    fetch(`../../api/get-exercise.php?id=${exerciseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.language) {
                    languageSelect.value = data.language;
                }
                if (data.starter_code) {
                    editor.value = data.starter_code;
                } else {
                    editor.value = `// ${data.title}\n// ${data.description}\n\n`;
                }
                updateEditorInfo();
                clearConsole();
                consolePrint(`📚 Loaded Exercise: ${data.title}`, 'prompt-text');
                consolePrint(data.description, 'output-text');
                consolePrint(`Difficulty: ${data.difficulty}`, 'output-text');
            } else {
                alert('❌ Failed to load exercise');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Failed to load exercise');
        });
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        runCode();
    }
});

console.log('✅ Coding Playground loaded successfully');
console.log('💡 Tip: Press Ctrl+Enter to run code');
console.log('🖥️ Interactive console: Enter values when prompted');
</script>

<?php include '../../includes/footer.php'; ?>