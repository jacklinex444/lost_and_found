<?php
// Simple password hashing utility
// Place this file in your project root and access via browser

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Also show different hashing algorithms for demonstration
    $hashed_bcrypt = password_hash($password, PASSWORD_BCRYPT);
    $hashed_argon2i = password_hash($password, PASSWORD_ARGON2I);
    $hashed_argon2id = password_hash($password, PASSWORD_ARGON2ID);
    
    // Verify the hash
    $verify = password_verify($password, $hashed_password);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .hash-container {
            max-width: 800px;
            margin: auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hash-result {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            word-break: break-all;
        }
        .copy-btn {
            cursor: pointer;
            transition: all 0.3s;
        }
        .copy-btn:hover {
            transform: scale(1.1);
        }
        .algorithm-card {
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container hash-container">
        <div class="card">
            <div class="card-header bg-primary text-white text-center">
                <i class="fas fa-lock fa-2x"></i>
                <h3 class="mt-2">Password Hash Generator</h3>
                <p class="mb-0">Secure password hashing for Lost & Found System</p>
            </div>
            <div class="card-body">
                <!-- Password Input Form -->
                <form method="POST" action="" id="hashForm">
                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-key"></i> Enter Password to Hash
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                   required placeholder="Type your password here...">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Password will be hashed using PHP's password_hash() function</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-hashtag"></i> Generate Hash
                        </button>
                    </div>
                </form>

                <?php if(isset($hashed_password)): ?>
                <hr>
                
                <!-- Results -->
                <div class="mt-4">
                    <h5 class="text-success">
                        <i class="fas fa-check-circle"></i> Hash Generated Successfully!
                    </h5>
                    
                    <!-- Main Hash Result -->
                    <div class="alert alert-success mt-3">
                        <strong>Verification Status:</strong> 
                        <?php if($verify): ?>
                            <span class="text-success">✓ Hash is valid and matches the password</span>
                        <?php else: ?>
                            <span class="text-danger">✗ Hash verification failed</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hash-result p-3 rounded">
                        <label class="fw-bold text-primary">
                            <i class="fas fa-code"></i> Hashed Password (Default - BCRYPT):
                        </label>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control font-monospace" id="hashResult" 
                                   value="<?php echo htmlspecialchars($hashed_password); ?>" readonly>
                            <button class="btn btn-outline-success copy-btn" onclick="copyToClipboard('hashResult')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle"></i> 
                            This hash can be directly inserted into your database's password field.
                        </small>
                    </div>
                    
                    <!-- Different Algorithm Options -->
                    <div class="mt-4">
                        <h6><i class="fas fa-chart-line"></i> Alternative Algorithms:</h6>
                        
                        <div class="algorithm-card p-3">
                            <label class="fw-bold">BCRYPT (Cost 10):</label>
                            <div class="input-group mt-1">
                                <input type="text" class="form-control font-monospace small" 
                                       value="<?php echo htmlspecialchars($hashed_bcrypt); ?>" readonly>
                                <button class="btn btn-outline-secondary copy-btn" onclick="copyText('<?php echo htmlspecialchars($hashed_bcrypt); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <small class="text-muted">Default algorithm, good compatibility</small>
                        </div>
                        
                        <div class="algorithm-card p-3">
                            <label class="fw-bold">ARGON2I:</label>
                            <div class="input-group mt-1">
                                <input type="text" class="form-control font-monospace small" 
                                       value="<?php echo htmlspecialchars($hashed_argon2i); ?>" readonly>
                                <button class="btn btn-outline-secondary copy-btn" onclick="copyText('<?php echo htmlspecialchars($hashed_argon2i); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <small class="text-muted">More secure, requires PHP 7.2+</small>
                        </div>
                        
                        <div class="algorithm-card p-3">
                            <label class="fw-bold">ARGON2ID (Recommended):</label>
                            <div class="input-group mt-1">
                                <input type="text" class="form-control font-monospace small" 
                                       value="<?php echo htmlspecialchars($hashed_argon2id); ?>" readonly>
                                <button class="btn btn-outline-secondary copy-btn" onclick="copyText('<?php echo htmlspecialchars($hashed_argon2id); ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <small class="text-muted">Most secure, requires PHP 7.3+</small>
                        </div>
                    </div>
                    
                    <!-- SQL Insert Statement -->
                    <div class="mt-4">
                        <h6><i class="fas fa-database"></i> SQL Insert Statement:</h6>
                        <div class="bg-dark text-white p-3 rounded">
                            <code class="small">
                                INSERT INTO users (username, email, password, role) VALUES <br>
                                ('your_username', 'your_email@example.com', '<?php echo addslashes($hashed_password); ?>', 'user');
                            </code>
                            <button class="btn btn-sm btn-light mt-2 copy-btn" onclick="copySQL()">
                                <i class="fas fa-copy"></i> Copy SQL
                            </button>
                        </div>
                    </div>
                    
                    <!-- Update Password SQL -->
                    <div class="mt-3">
                        <h6><i class="fas fa-user-edit"></i> Update Existing User Password:</h6>
                        <div class="bg-light p-3 rounded">
                            <code class="small">
                                UPDATE users SET password = '<?php echo addslashes($hashed_password); ?>' WHERE email = 'user@example.com';
                            </code>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Information Box -->
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important Notes:</strong>
                    <ul class="mb-0 mt-2">
                        <li>This hash is one-way encrypted and cannot be decrypted</li>
                        <li>Always hash passwords before storing in database</li>
                        <li>Use <code>password_verify()</code> function to check passwords during login</li>
                        <li>Default admin password is: <strong>admin123</strong></li>
                        <li>Hash generated using PHP 7.4+ with BCRYPT algorithm</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Quick Reference -->
        <div class="card mt-3">
            <div class="card-body">
                <h6><i class="fas fa-code"></i> PHP Code Reference:</h6>
                <pre class="bg-light p-3 rounded"><code>
// To hash a password:
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// To verify a password during login:
if(password_verify($input_password, $hashed_password_from_db)) {
    // Password is correct
}

// To check if password needs rehashing:
if(password_needs_rehash($hashed_password, PASSWORD_DEFAULT)) {
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    // Update database with new hash
}
                </code></pre>
                
                <button class="btn btn-sm btn-outline-primary" onclick="testPassword()">
                    <i class="fas fa-vial"></i> Test Password Verification
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        // Copy to clipboard function
        function copyToClipboard(elementId) {
            const input = document.getElementById(elementId);
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show feedback
            const btn = event.target.closest('.copy-btn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }
        
        function copyText(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            // Show feedback
            const btn = event.target.closest('.copy-btn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }
        
        function copySQL() {
            const sql = `INSERT INTO users (username, email, password, role) VALUES ('your_username', 'your_email@example.com', '${document.getElementById('hashResult').value}', 'user');`;
            copyText(sql);
        }
        
        function testPassword() {
            const password = prompt("Enter a password to test against the generated hash:");
            if(password) {
                alert("This feature requires AJAX. In production, you'd send this to server for verification.");
            }
        }
        
        // Auto-submit if password parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        const passwordParam = urlParams.get('password');
        if(passwordParam) {
            document.getElementById('password').value = passwordParam;
            document.getElementById('hashForm').submit();
        }
    </script>
</body>
</html>