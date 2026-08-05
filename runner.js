/**
 * Instant System Launcher & Runner for Office File Management CRM
 */

const { spawn, execSync } = require('child_process');
const fs = require('fs');
const http = require('http');
const path = require('path');

const PORT = 8000;

console.log("\n=======================================================");
console.log("   🚀 Office File Management CRM - Local Launcher");
console.log("=======================================================\n");

// Check possible PHP executable paths
const possiblePhpPaths = [
    'php',
    'C:\\php\\php.exe',
    'C:\\xampp\\php\\php.exe',
    'C:\\wamp64\\bin\\php\\php.exe',
    'C:\\laragon\\bin\\php\\php.exe'
];

let phpCmd = null;

for (const p of possiblePhpPaths) {
    try {
        if (p === 'php') {
            execSync('php -v', { stdio: 'ignore' });
            phpCmd = 'php';
            break;
        } else if (fs.existsSync(p)) {
            phpCmd = p;
            break;
        }
    } catch (e) {}
}

if (phpCmd) {
    console.log(`✅ PHP Binary Found: ${phpCmd}`);
    console.log(`📡 Starting Local PHP Server on http://localhost:${PORT} ...\n`);

    const server = spawn(phpCmd, ['-S', `localhost:${PORT}`], {
        cwd: __DIR__ || process.cwd(),
        stdio: 'inherit'
    });

    server.on('error', (err) => {
        console.error('Failed to start PHP server:', err.message);
    });
} else {
    console.log("⚠️  PHP binary is not detected in standard system paths.");
    console.log("-------------------------------------------------------");
    console.log("To run the full PHP + Database backend:");
    console.log("1. Extract PHP to C:\\php or install XAMPP.");
    console.log("2. Run command: C:\\php\\php.exe -S localhost:8000\n");
}
