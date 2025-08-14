<!DOCTYPE html>
<html>
<head>
    <title>Password Reset</title>
</head>
<body>
    <h2>Admin Password Reset Request</h2>
    <p>You are receiving this email because we received a password reset request for your admin account.</p>
    
    <p>Click the link below to reset your password:</p>
    <a href="{{ url('admin/reset-password/' . $token . '?email=' . urlencode($email)) }}" 
       style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
        Reset Password
    </a>
    
    <p>This password reset link will expire in 60 minutes.</p>
    
    <p>If you did not request a password reset, no further action is required.</p>
    
    <hr>
    <small>This is an automated email. Please do not reply.</small>
</body>
</html>