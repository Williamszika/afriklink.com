<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;

/**
 * Route table: [HTTP method, path, [Controller, action], [middleware...]].
 *
 * Middleware aliases:
 *   guest                  — only for logged-out visitors
 *   auth                   — requires a logged-in user (auth:vendor / auth:admin for a role)
 *   csrf                   — verifies CSRF token on mutating requests
 *   throttle:bucket,max,window — per-IP rate limit
 *
 * LocaleMiddleware runs globally before any route middleware (see Router::dispatch).
 */

return [
    // ---- Public -------------------------------------------------------
    ['GET',  '/',                  [HomeController::class, 'index'],          []],
    ['GET',  '/health',            [HomeController::class, 'health'],         []],
    ['GET',  '/lang/{locale}',     [HomeController::class, 'switchLanguage'], []],

    // ---- Authentication (guests) -------------------------------------
    ['GET',  '/register',               [AuthController::class, 'showRegisterChoice'],     ['guest']],
    ['GET',  '/register/particulier',   [AuthController::class, 'showRegisterParticulier'],['guest']],
    ['POST', '/register/particulier',   [AuthController::class, 'registerParticulier'],    ['guest', 'csrf', 'throttle:register,10,3600']],
    ['GET',  '/register/professionnel', [AuthController::class, 'showRegisterPro'],         ['guest']],

    ['GET',  '/login',             [AuthController::class, 'showLogin'],      ['guest']],
    ['POST', '/login',             [AuthController::class, 'login'],          ['guest', 'csrf', 'throttle:login,10,900']],

    ['GET',  '/forgot-password',   [AuthController::class, 'showForgot'],     ['guest']],
    ['POST', '/forgot-password',   [AuthController::class, 'sendReset'],      ['guest', 'csrf', 'throttle:forgot,5,3600']],

    ['GET',  '/reset-password',    [AuthController::class, 'showReset'],      ['guest']],
    ['POST', '/reset-password',    [AuthController::class, 'reset'],          ['guest', 'csrf', 'throttle:reset,10,3600']],

    ['GET',  '/verify-email',      [AuthController::class, 'verifyEmail'],    ['throttle:verify,30,3600']],

    // ---- Authenticated ------------------------------------------------
    ['GET',  '/verify-email/notice',  [AuthController::class, 'verifyNotice'],        ['auth']],
    ['POST', '/verify-email/resend',  [AuthController::class, 'resendVerification'],  ['auth', 'csrf', 'throttle:resend,5,3600']],

    ['POST', '/logout',            [AuthController::class, 'logout'],         ['auth', 'csrf']],
    ['GET',  '/dashboard',         [DashboardController::class, 'index'],     ['auth']],

    // Account self-service
    ['GET',  '/profile',           [ProfileController::class, 'edit'],            ['auth']],
    ['POST', '/profile',           [ProfileController::class, 'update'],          ['auth', 'csrf', 'throttle:profile,30,3600']],
    ['POST', '/profile/password',  [ProfileController::class, 'updatePassword'],  ['auth', 'csrf', 'throttle:pwd,10,3600']],

    // Roadmap interstitials for not-yet-built dashboard actions
    ['GET',  '/bientot/{feature}', [DashboardController::class, 'comingSoon'],    ['auth']],
];
