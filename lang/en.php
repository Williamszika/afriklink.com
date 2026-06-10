<?php
declare(strict_types=1);

/**
 * English translations. Key => text. No hard-coded interface text in views.
 * :name placeholders are replaced via t('key', ['name' => ...]).
 */

return [
    // Navigation
    'nav.home'      => 'Home',
    'nav.shops'     => 'Explore',
    'nav.login'     => 'Log in',
    'nav.register'  => 'Sign up',
    'nav.dashboard' => 'Dashboard',
    'nav.logout'    => 'Log out',

    // Home
    'home.hero_title'      => 'The marketplace connecting Africa and the world',
    'home.hero_subtitle'   => 'Shops, restaurants, salons and services — sell and buy locally and internationally, across languages and currencies.',
    'home.cta_sell'        => 'Become a seller',
    'home.cta_explore'     => 'Explore the shops',
    'home.cta_login'       => 'Log in',
    'home.cta_register'    => 'Sign up',
    'home.verticals_title' => 'Four worlds, one platform',
    'home.vertical.shop.title'        => 'Shops',
    'home.vertical.shop.desc'         => 'Sell physical products with stock and local or international shipping.',
    'home.vertical.restaurant.title'  => 'Restaurants',
    'home.vertical.restaurant.desc'   => 'Publish your menus, take orders for pickup or delivery.',
    'home.vertical.salon.title'       => 'Salons',
    'home.vertical.salon.desc'        => 'Offer your services and let clients book a time slot.',
    'home.vertical.service.title'     => 'Trades & services',
    'home.vertical.service.desc'      => 'Plumber, tailor, coach… showcase your services and receive requests.',
    'home.trust'           => 'Security by default: protected payments, encrypted data, European compliance.',

    // Footer
    'footer.impressum' => 'Legal notice',
    'footer.terms'     => 'Terms',
    'footer.privacy'   => 'Privacy',

    // Fields
    'field.email'            => 'Email address',
    'field.password'         => 'Password',
    'field.password_new'     => 'New password',
    'field.password_confirm' => 'Confirm password',
    'field.locale'           => 'Language',
    'field.country'          => 'Country',
    'field.full_name'        => 'Full name',
    'field.nickname'         => 'Nickname',
    'field.birthdate'        => 'Date of birth',
    'field.birthdate_hint'   => 'Format: dd/mm/yyyy',
    'field.gender'           => 'Gender',
    'field.city'             => 'City',
    'field.choose'           => 'Choose…',
    'field.phone'            => 'Phone',
    'field.dial_code'        => 'Dialing code',
    'field.phone_placeholder'=> 'Number without the code',
    'field.phone_hint'       => 'Dialing code from your location.',
    'geo.unlock'             => 'Not my country?',
    'field.identifier'       => 'Email or phone',
    'field.identifier_placeholder' => 'you@example.com or +221…',

    // Gender
    'gender.homme' => 'Male',
    'gender.femme' => 'Female',
    'gender.autre' => 'Other',

    // Registration — account type choice
    'register.choice_title'      => 'Create an account',
    'register.choice_subtitle'   => 'Choose the account type that fits you.',
    'register.particulier_title' => 'Individual',
    'register.particulier_desc'  => 'Buyer and seller — buy and sell locally and internationally.',
    'register.pro_title'         => 'Professional',
    'register.pro_desc'          => 'Shop, restaurant, salon or trade — sell as a business.',
    'register.pro_soon'          => 'Professional sign-up is coming soon. Start as an Individual for now.',
    'register.choose'            => 'Continue',
    'register.particulier_submit'=> 'Create my account',
    'register.back_choice'       => 'Change account type',
    'register.by_email'          => 'By email',
    'register.by_phone'          => 'By phone',

    // Registration
    'auth.register.title'    => 'Create an account',
    'auth.register.subtitle' => 'One account to buy and to sell.',
    'auth.register.submit'   => 'Create my account',
    'auth.password_hint'     => 'At least :min characters.',
    'auth.have_account'      => 'Already have an account?',

    // Login
    'auth.login.title'    => 'Log in',
    'auth.login.submit'   => 'Log in',
    'auth.forgot_link'    => 'Forgot your password?',
    'auth.no_account'     => 'No account yet?',
    'auth.login_required' => 'Please log in to continue.',

    // Forgot password
    'auth.forgot.title'    => 'Reset your password',
    'auth.forgot.subtitle' => 'Enter your email; if an account exists, you’ll get a link.',
    'auth.forgot.submit'   => 'Send the link',

    // Reset
    'auth.reset.title'    => 'Choose a new password',
    'auth.reset.subtitle' => 'Enter your new password below.',
    'auth.reset.submit'   => 'Update',

    // Email verification
    'verify.notice_title' => 'Verify your email',
    'verify.notice_body'  => 'We sent a verification link to :email. Click it to unlock all features.',
    'verify.resend'       => 'Resend the email',
    'verify.go_dashboard' => 'Go to dashboard',

    // Dashboard — Individual space
    'dash.welcome'           => 'Welcome, :name 👋',
    'dash.badge_verified'    => 'Verified',
    'dash.badge_unverified'  => 'To verify',
    'dash.contact_verified'  => 'Verified contact',
    'dash.progress'          => 'Profile :pct % complete',
    'dash.progress_missing'  => 'To complete: ',
    'dash.stat.purchases'    => 'Purchases',
    'dash.stat.sales'        => 'Sales',
    'dash.stat.listings'     => 'Listings',
    'dash.stat.messages'     => 'Messages',
    'dash.phase'             => 'Phase :n — soon',
    'dash.soon'              => 'Soon',
    'dash.action.sell_title'   => 'Sell an item',
    'dash.action.sell_desc'    => 'Put an item up for sale, directly — no shop needed.',
    'dash.action.explore_title'=> 'Explore the marketplace',
    'dash.action.explore_desc' => 'Discover shops, restaurants, salons and services.',
    'dash.buys_title'        => 'My purchases',
    'dash.buys_empty'        => 'No purchases yet.',
    'dash.sales_title'       => 'My sales',
    'dash.sales_empty'       => 'No items for sale yet.',
    'dash.info_title'        => 'My information',
    'dash.complete_profile'  => 'Complete my profile →',
    'dash.edit_profile'      => 'Edit my profile →',
    'dash.action.profile_title' => 'My profile',
    'dash.action.profile_desc'  => 'Edit your information and password.',

    // Profile (account space)
    'profile.title'            => 'My profile',
    'profile.back_dashboard'   => 'Back to dashboard',
    'profile.edit'             => 'Edit',
    'profile.info_title'       => 'Personal information',
    'profile.contact_locked'   => 'contact can’t be changed here',
    'profile.save'             => 'Save',
    'profile.photo_title'      => 'Profile photo',
    'profile.photo_change'     => 'Change my photo',
    'profile.photo_delete'     => 'Remove photo',
    'profile.photo_hint'       => 'JPG, PNG or WebP — automatically cropped to a square.',
    'profile.password_title'   => 'Password',
    'profile.current_password' => 'Current password',
    'profile.new_password'     => 'New password',
    'profile.change_password'  => 'Change password',

    // “Coming soon” pages
    'soon.note'             => 'This feature is coming soon. Thanks for your patience!',
    'soon.vendre.title'     => '🏷️ Sell an item — coming soon',
    'soon.vendre.desc'      => 'Soon you’ll be able to list your items for sale: photos, price, condition, and chat with buyers.',
    'soon.annonces.title'   => '🏷️ My listings — coming soon',
    'soon.annonces.desc'    => 'All your items for sale will appear here once the feature is available.',
    'soon.messages.title'   => '💬 Messaging — coming soon',
    'soon.messages.desc'    => 'Messaging will let you chat with buyers and sellers directly on Afriklink.',

    // Dashboard
    'dashboard.title'           => 'Dashboard',
    'dashboard.welcome'         => 'Welcome, :email.',
    'dashboard.email_unverified'=> 'Your email address is not verified yet.',
    'dashboard.email_verified'  => 'Your email address is verified.',
    'dashboard.role'            => 'Role',
    'dashboard.member_since'    => 'Member since',
    'dashboard.next_steps'      => 'Coming soon: list your items for sale between individuals.',

    // Validation
    'validation.email_invalid'     => 'Invalid email address.',
    'validation.email_taken'       => 'This email address is already in use.',
    'validation.password_short'    => 'Password must be at least :min characters.',
    'validation.password_mismatch' => 'Passwords do not match.',
    'validation.required'          => 'This field is required.',
    'validation.birthdate_invalid' => 'Invalid date of birth (format dd/mm/yyyy).',
    'validation.phone_invalid'     => 'Invalid phone number.',
    'validation.phone_taken'       => 'This phone number is already in use.',
    'validation.current_password_wrong' => 'Current password is incorrect.',
    'validation.avatar_invalid'    => 'Invalid image — use a JPG, PNG or WebP file.',
    'validation.avatar_too_big'    => 'Image too large. Try a smaller photo.',

    // Flash messages
    'flash.registered'          => 'Account created. Check your email to activate it.',
    'flash.registered_phone'    => 'Account created. Welcome to Afriklink!',
    'flash.logged_in'           => 'You are logged in.',
    'flash.logged_out'          => 'You are logged out.',
    'flash.invalid_credentials' => 'Incorrect email or password.',
    'flash.account_suspended'   => 'This account is suspended. Contact support.',
    'flash.reset_sent'          => 'If an account exists for this address, a reset link has been sent.',
    'flash.reset_ok'            => 'Password updated. You can now log in.',
    'flash.invalid_token'       => 'Invalid or expired link.',
    'flash.verify_ok'           => 'Your email is verified. Thank you!',
    'flash.verify_sent'         => 'Verification email sent.',
    'flash.already_verified'    => 'Your email is already verified.',
    'flash.profile_updated'     => 'Profile updated.',
    'flash.password_changed'    => 'Password changed.',
    'flash.avatar_updated'      => 'Profile photo updated.',
    'flash.avatar_deleted'      => 'Profile photo removed.',

    // Emails
    'mail.verify.subject' => 'Verify your email — Afriklink',
    'mail.verify.body'    => 'Welcome to :app. Confirm your email address by clicking the link below.',
    'mail.verify.cta'     => 'Verify my email',
    'mail.reset.subject'  => 'Reset your password — Afriklink',
    'mail.reset.body'     => 'You requested a password reset on :app. This link expires soon.',
    'mail.reset.cta'      => 'Reset my password',

    // Errors
    'error.404_title'         => 'Page not found',
    'error.404_body'          => 'The page you’re looking for doesn’t exist or has moved.',
    'error.403_title'         => 'Access denied',
    'error.403_body'          => 'You don’t have permission to access this resource.',
    'error.405_title'         => 'Method not allowed',
    'error.405_body'          => 'This action isn’t permitted here.',
    'error.429_title'         => 'Too many requests',
    'error.429_body'          => 'You’ve made too many attempts. Try again in a moment.',
    'error.500_title'         => 'Something went wrong',
    'error.500_body'          => 'A problem occurred on our side. Please try again later.',
    'error.back_home'         => 'Back to home',
    'error.too_many_requests' => 'Too many requests. Try again later.',
];
