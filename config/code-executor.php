<?php
// =====================================================
// CODE EXECUTOR CONFIGURATION - COMPLETE
// =====================================================

// OneCompiler API Configuration
define('ONECOMPILER_API_KEY', 'oc_44tm7b9km_44tm7b9m7_cd8494c8047a546ed5022b06f6d656c1029425df6beba5ad');
define('ONECOMPILER_API_URL', 'https://api.onecompiler.com/v1/run');

// Supported languages
$supported_languages = [
    'c' => 'C',
    'cpp' => 'C++',
    'python' => 'Python',
    'java' => 'Java',
    'php' => 'PHP',
    'sql' => 'SQL',
    'javascript' => 'JavaScript',
    'ruby' => 'Ruby',
    'go' => 'Go',
    'rust' => 'Rust',
    'typescript' => 'TypeScript',
    'csharp' => 'C#',
    'kotlin' => 'Kotlin',
    'swift' => 'Swift',
    'bash' => 'Bash',
];

// Language file extensions
$language_extensions = [
    'c' => 'c',
    'cpp' => 'cpp',
    'python' => 'py',
    'java' => 'java',
    'php' => 'php',
    'sql' => 'sql',
    'javascript' => 'js',
    'ruby' => 'rb',
    'go' => 'go',
    'rust' => 'rs',
    'typescript' => 'ts',
    'csharp' => 'cs',
    'kotlin' => 'kt',
    'swift' => 'swift',
    'bash' => 'sh',
];

// Language boilerplate code
$language_boilerplate = [
    'c' => '#include <stdio.h>\n\nint main() {\n    printf("Hello, World!\\n");\n    return 0;\n}',
    'cpp' => '#include <iostream>\nusing namespace std;\n\nint main() {\n    cout << "Hello, World!" << endl;\n    return 0;\n}',
    'python' => 'print("Hello, World!")',
    'java' => 'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello, World!");\n    }\n}',
    'php' => '<?php\necho "Hello, World!\\n";\n?>',
    'javascript' => 'console.log("Hello, World!");',
    'ruby' => 'puts "Hello, World!"',
    'go' => 'package main\n\nimport "fmt"\n\nfunc main() {\n    fmt.Println("Hello, World!")\n}',
    'rust' => 'fn main() {\n    println!("Hello, World!");\n}',
    'sql' => 'SELECT "Hello, World!";',
    'typescript' => 'console.log("Hello, World!");',
    'csharp' => 'using System;\n\nclass Program {\n    static void Main() {\n        Console.WriteLine("Hello, World!");\n    }\n}',
    'kotlin' => 'fun main() {\n    println("Hello, World!")\n}',
    'swift' => 'print("Hello, World!")',
    'bash' => 'echo "Hello, World!"',
];
?>