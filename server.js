const { spawn, execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 8000;
const HOST = 'localhost';

// Helper to check if a command exists in the system PATH
function commandExists(cmd) {
  try {
    execSync(
      process.platform === 'win32'
        ? `where ${cmd}`
        : `which ${cmd}`,
      { stdio: [] }
    );
    return true;
  } catch (e) {
    return false;
  }
}

// Find PHP executable
let phpBin = 'php';
const localPhpPath = path.join(__dirname, 'tools', 'php', 'php.exe');

if (process.platform === 'win32' && fs.existsSync(localPhpPath)) {
  phpBin = localPhpPath;
  console.log(`Using portable PHP found at: ${phpBin}`);
} else {
  console.log('Checking system PATH for PHP...');
  if (!commandExists('php')) {
    console.error('\x1b[31mError: PHP is not installed or not found in system PATH.\x1b[0m');
    console.error('Please run the local setup script first to download portable PHP:');
    console.error('  PowerShell: .\\setup_local.ps1');
    process.exit(1);
  }
}

// Configure PHP built-in server environment
const env = { ...process.env };
if (process.platform !== 'win32') {
  // Set PHP_CLI_SERVER_WORKERS to enable concurrent request handling (not supported on Windows)
  env.PHP_CLI_SERVER_WORKERS = '4';
}

console.log(`\n\x1b[36mStarting local PHP web server on http://${HOST}:${PORT}...\x1b[0m`);
console.log(`Press \x1b[33mCtrl+C\x1b[0m to stop the server.\n`);

// Start the built-in server using local_router.php
const serverProcess = spawn(
  phpBin,
  ['-S', `${HOST}:${PORT}`, 'local_router.php'],
  {
    cwd: __dirname,
    env: env,
    stdio: 'inherit' // Inherit stdio so logs print directly to terminal
  }
);

// Open browser after a small delay to let the server start
setTimeout(() => {
  const url = `http://${HOST}:${PORT}`;
  console.log(`\x1b[32mOpening browser at ${url}...\x1b[0m`);
  
  let startCmd;
  if (process.platform === 'darwin') {
    startCmd = 'open';
  } else if (process.platform === 'win32') {
    startCmd = 'start';
  } else {
    startCmd = 'xdg-open';
  }
  
  const { exec } = require('child_process');
  exec(`${startCmd} ${url}`, (err) => {
    if (err) {
      console.error('Failed to open browser automatically:', err.message);
    }
  });
}, 1000);

// Handle exit signals
process.on('SIGINT', () => {
  console.log('\nStopping server...');
  serverProcess.kill();
  process.exit();
});

process.on('SIGTERM', () => {
  console.log('\nStopping server...');
  serverProcess.kill();
  process.exit();
});

serverProcess.on('error', (err) => {
  console.error('\x1b[31mFailed to start server process:\x1b[0m', err.message);
  process.exit(1);
});

serverProcess.on('exit', (code) => {
  if (code !== null && code !== 0) {
    console.log(`Server process exited with code ${code}`);
  }
});
