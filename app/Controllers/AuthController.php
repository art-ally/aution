<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonDatabase;
use App\Core\Session;

class AuthController
{
    private JsonDatabase $db;

    public function __construct()
    {
        $this->db = JsonDatabase::getInstance();
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        view('auth/login', [
            'pageTitle' => 'Sign In — ArtAlly',
        ]);
    }

    public function login(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        // Simple rate limiting in session
        $attempts = (int)Session::get('login_attempts', 0);
        $lastAttempt = (int)Session::get('login_last_attempt', 0);
        if ($attempts >= 8 && (time() - $lastAttempt) < 120) {
            flash('error', 'Too many sign-in attempts. Please wait two minutes before trying again.');
            redirect('/login');
        }

        $user = $this->db->findBy('users', 'email', $email);
        if (!$user || !password_verify($password, (string)$user['password'])) {
            Session::set('login_attempts', $attempts + 1);
            Session::set('login_last_attempt', time());
            flash('error', 'Invalid email address or password.');
            redirect('/login');
        }

        if (($user['status'] ?? 'active') === 'suspended') {
            flash('error', 'This account has been suspended by an administrator.');
            redirect('/login');
        }

        Session::forget('login_attempts');
        Auth::login($user);

        flash('success', 'Welcome back to ArtAlly, ' . $user['name'] . '.');

        if (Auth::hasRole('admin')) {
            redirect('/admin/dashboard');
        }
        if (($user['role'] ?? '') === 'artist') {
            redirect('/artist/dashboard');
        }
        redirect('/buyer/dashboard');
    }

    public function quickDemoLogin(): void
    {
        $role = $_POST['demo_role'] ?? 'buyer';
        $email = match ($role) {
            'admin' => 'admin@ateliernoir.art',
            'artist' => 'soren@ateliernoir.art',
            default => 'collector@ateliernoir.art',
        };
        $user = $this->db->findBy('users', 'email', $email);
        if ($user) {
            Auth::login($user);
            flash('success', 'Signed in as ' . $user['name'] . ' (' . strtoupper($role) . ' Account).');
            if ($role === 'admin') {
                redirect('/admin/dashboard');
            }
            if ($role === 'artist') {
                redirect('/artist/dashboard');
            }
            redirect('/buyer/dashboard');
        }
        redirect('/login');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $settings = $this->db->find('platform_settings', 1) ?? [];
        $buyerVerificationCode = (string)($settings['buyer_verification_code'] ?? 'NOIR-BUYER-2026');
        $selectedRole = ($_GET['role'] ?? 'artist') === 'buyer' ? 'buyer' : 'artist';
        view('auth/register', [
            'pageTitle' => 'Artist Studio & Verified Collector Registration — ArtAlly',
            'selectedRole' => $selectedRole,
            'buyerVerificationCode' => $buyerVerificationCode,
        ]);
    }

    public function register(): void
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $country = trim((string)($_POST['country'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $accountType = ($_POST['account_type'] ?? 'artist') === 'buyer' ? 'buyer' : 'artist';
        $discipline = trim((string)($_POST['discipline'] ?? 'Contemporary Visual Art'));
        $biography = trim((string)($_POST['biography'] ?? 'Independent artist registered with ArtAlly.'));
        $termsAccepted = !empty($_POST['terms_accepted']);

        // Buyer path requires a valid Buyer Verification Code
        if ($accountType === 'buyer') {
            $settings = $this->db->find('platform_settings', 1) ?? [];
            $expectedCode = trim((string)($settings['buyer_verification_code'] ?? 'NOIR-BUYER-2026'));
            $submittedCode = trim((string)($_POST['buyer_verification_code'] ?? ''));
            if ($submittedCode === '' || strcasecmp($submittedCode, $expectedCode) !== 0) {
                flash('error', 'Verification Code Required: This registration portal is open for Artists only. To access the Collector/Buyer path, you must enter a valid Buyer Verification Code issued by Administration.');
                redirect('/register?role=buyer');
            }
        }

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $country === '' || !$termsAccepted) {
            flash('error', 'Please complete all required fields, use a password of at least 8 characters, and accept the Conditions of Sale.');
            redirect('/register?role=' . $accountType);
        }

        $existing = $this->db->findBy('users', 'email', $email);
        if ($existing) {
            flash('error', 'An account with this email address is already registered.');
            redirect('/login');
        }

        $roles = $accountType === 'artist' ? ['artist', 'buyer'] : ['buyer'];

        $user = $this->db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'country' => $country,
            'role' => $accountType,
            'roles' => $roles,
            'email_verified_at' => gmdate('Y-m-d H:i:s'),
            'bidding_approved' => true,
            'status' => 'active',
            'is_seeded' => false,
        ]);

        if ($accountType === 'artist') {
            $profilePhoto = 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&w=800&q=80';
            $targetDir = PUBLIC_PATH . '/uploads/profiles';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            if (!empty($_FILES['profile_photo_file']['tmp_name']) && $_FILES['profile_photo_file']['error'] === UPLOAD_ERR_OK) {
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif', 'bmp'];
                $ext = strtolower(pathinfo((string)$_FILES['profile_photo_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) {
                    $ext = 'jpg';
                }
                $filename = 'artist_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_photo_file']['tmp_name'], $targetDir . '/' . $filename)) {
                    $profilePhoto = '/uploads/profiles/' . $filename;
                }
            } elseif (!empty($_POST['profile_photo_data']) && str_starts_with((string)$_POST['profile_photo_data'], 'data:image/')) {
                if (preg_match('/^data:image\/(\w+);base64,(.+)$/', (string)$_POST['profile_photo_data'], $matches)) {
                    $ext = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
                    $decoded = base64_decode($matches[2], true);
                    if ($decoded !== false) {
                        $filename = 'artist_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        if (file_put_contents($targetDir . '/' . $filename, $decoded) !== false) {
                            $profilePhoto = '/uploads/profiles/' . $filename;
                        }
                    }
                }
            }

            $this->db->insert('artist_profiles', [
                'user_id' => (int)$user['id'],
                'display_name' => $name,
                'slug' => slugify($name . '-' . $user['id']),
                'biography' => $biography !== '' ? $biography : 'Independent artist registered with ArtAlly.',
                'artist_statement' => '',
                'profile_photo' => $profilePhoto,
                'country' => $country,
                'city' => $city,
                'discipline' => $discipline !== '' ? $discipline : 'Contemporary Art',
                'years_active' => gmdate('Y') . ' – Present',
                'website' => '',
                'social_instagram' => '',
                'featured' => false,
                'verified_artist' => false,
                'approval_status' => 'pending', // Awaiting admin approval before public display
                'portfolio_images' => [],
            ]);
        }

        $this->db->insert('buyer_profiles', [
            'user_id' => (int)$user['id'],
            'collecting_focus' => 'Contemporary & Modern Works',
            'verified_payment_method' => 'Private Client Account Verified',
            'shipping_address' => $country,
            'identity_verified' => true,
        ]);

        $token = bin2hex(random_bytes(20));
        $this->db->insert('email_verifications', [
            'user_id' => (int)$user['id'],
            'email' => $email,
            'token' => $token,
            'verified_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->db->insert('notifications', [
            'user_id' => (int)$user['id'],
            'type' => 'account_verified',
            'title' => 'Welcome to ArtAlly',
            'message' => $accountType === 'artist'
                ? 'Your Artist & Collector account is active. You may now complete your public portfolio or submit artwork for curatorial review.'
                : 'Your Collector account and anonymous bidding paddle are active. You may now watch lots and place verified bids.',
            'action_url' => $accountType === 'artist' ? '/artist/dashboard' : '/buyer/dashboard',
            'read_at' => null,
        ]);

        Auth::login($user);
        flash('success', 'Your ArtAlly account has been created and verified.');

        if ($accountType === 'artist') {
            redirect('/artist/dashboard');
        }
        redirect('/buyer/dashboard');
    }

    public function showVerifyEmail(): void
    {
        $user = Auth::requireAuth();
        view('auth/verify-email', [
            'pageTitle' => 'Email & Paddle Verification — ArtAlly',
            'user' => $user,
        ]);
    }

    public function verifyEmailAction(): void
    {
        $user = Auth::requireAuth();
        $this->db->update('users', $user['id'], [
            'email_verified_at' => gmdate('Y-m-d H:i:s'),
            'bidding_approved' => true,
        ]);
        flash('success', 'Your email address and collector bidding paddle have been verified.');
        redirect('/buyer/dashboard');
    }

    public function showForgotPassword(): void
    {
        view('auth/forgot-password', [
            'pageTitle' => 'Password Recovery — ArtAlly',
        ]);
    }

    public function sendResetLink(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $user = $this->db->findBy('users', 'email', $email);
        $token = bin2hex(random_bytes(24));

        if ($user) {
            $this->db->insert('password_reset_tokens', [
                'email' => $email,
                'token' => $token,
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+1 hour')),
            ]);
            Session::set('demo_reset_token', $token);
            Session::set('demo_reset_email', $email);
        }

        flash('success', 'Password recovery link generated. You may proceed to reset your password below.');
        redirect('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
    }

    public function showResetPassword(): void
    {
        $token = trim((string)($_GET['token'] ?? Session::get('demo_reset_token', '')));
        $email = trim((string)($_GET['email'] ?? Session::get('demo_reset_email', '')));
        view('auth/reset-password', [
            'pageTitle' => 'Set New Password — ArtAlly',
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(): void
    {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $token = trim((string)($_POST['token'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (strlen($password) < 8) {
            flash('error', 'New password must be at least 8 characters long.');
            redirect('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
        }

        $record = $this->db->findBy('password_reset_tokens', 'token', $token);
        $user = $this->db->findBy('users', 'email', $email);

        if (!$record || !$user || strtolower((string)$record['email']) !== $email) {
            flash('error', 'Invalid or expired password recovery token.');
            redirect('/forgot-password');
        }

        $this->db->update('users', $user['id'], [
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $this->db->delete('password_reset_tokens', $record['id']);
        flash('success', 'Your password has been updated. Please sign in with your new credentials.');
        redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'You have safely signed out of ArtAlly.');
        redirect('/');
    }
}
