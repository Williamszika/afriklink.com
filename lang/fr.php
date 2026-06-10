<?php
declare(strict_types=1);

/**
 * Traductions françaises. Clé => texte. Aucun texte d'interface en dur dans les vues.
 * Les placeholders :name sont remplacés par t('clé', ['name' => ...]).
 */

return [
    // Navigation
    'nav.home'      => 'Accueil',
    'nav.shops'     => 'Explorer',
    'nav.login'     => 'Connexion',
    'nav.register'  => 'Créer un compte',
    'nav.dashboard' => 'Tableau de bord',
    'nav.logout'    => 'Déconnexion',

    // Accueil
    'home.hero_title'      => 'La marketplace qui relie l’Afrique et le monde',
    'home.hero_subtitle'   => 'Boutiques, restaurants, salons et services — vendez et achetez en local et à l’international, en plusieurs langues et devises.',
    'home.cta_sell'        => 'Devenir vendeur',
    'home.cta_explore'     => 'Explorer les boutiques',
    'home.cta_login'       => 'Connexion',
    'home.cta_register'    => 'Inscription',
    'home.verticals_title' => 'Quatre univers, une seule plateforme',
    'home.vertical.shop.title'        => 'Boutiques',
    'home.vertical.shop.desc'         => 'Vendez des produits physiques avec stock et livraison locale ou internationale.',
    'home.vertical.restaurant.title'  => 'Restaurants',
    'home.vertical.restaurant.desc'   => 'Publiez vos menus, recevez des commandes en retrait ou en livraison.',
    'home.vertical.salon.title'       => 'Salons',
    'home.vertical.salon.desc'        => 'Proposez vos prestations et laissez vos clients réserver un créneau.',
    'home.vertical.service.title'     => 'Métiers & services',
    'home.vertical.service.desc'      => 'Plombier, couturier, coach… présentez vos services et recevez des demandes.',
    'home.trust'           => 'Sécurité intégrée par défaut : paiements protégés, données chiffrées, conformité européenne.',

    // Pied de page
    'footer.impressum' => 'Mentions légales',
    'footer.terms'     => 'Conditions',
    'footer.privacy'   => 'Confidentialité',

    // Champs
    'field.email'            => 'Adresse e-mail',
    'field.password'         => 'Mot de passe',
    'field.password_new'     => 'Nouveau mot de passe',
    'field.password_confirm' => 'Confirmer le mot de passe',
    'field.locale'           => 'Langue',
    'field.country'          => 'Pays',
    'field.full_name'        => 'Nom complet',
    'field.nickname'         => 'Surnom',
    'field.birthdate'        => 'Date de naissance',
    'field.birthdate_hint'   => 'Format : jj/mm/aaaa',
    'field.gender'           => 'Sexe',
    'field.city'             => 'Ville',
    'field.choose'           => 'Choisir…',
    'field.phone'            => 'Téléphone',
    'field.dial_code'        => 'Indicatif',
    'field.phone_placeholder'=> 'Numéro sans l’indicatif',
    'field.phone_hint'       => 'Indicatif détecté automatiquement (modifiable).',
    'field.identifier'       => 'E-mail ou téléphone',
    'field.identifier_placeholder' => 'vous@exemple.com ou +221…',

    // Sexe
    'gender.homme' => 'Homme',
    'gender.femme' => 'Femme',
    'gender.autre' => 'Autre',

    // Inscription — choix du type de compte
    'register.choice_title'      => 'Créer un compte',
    'register.choice_subtitle'   => 'Choisis le type de compte qui te correspond.',
    'register.particulier_title' => 'Particulier',
    'register.particulier_desc'  => 'Acheteur et vendeur — achète et vends en local et à l’international.',
    'register.pro_title'         => 'Professionnel',
    'register.pro_desc'          => 'Boutique, restaurant, salon ou métier — vends en tant qu’entreprise.',
    'register.pro_soon'          => 'L’inscription professionnelle arrive bientôt. Commence en Particulier en attendant.',
    'register.choose'            => 'Continuer',
    'register.particulier_submit'=> 'Créer mon compte',
    'register.back_choice'       => 'Changer de type de compte',
    'register.by_email'          => 'Par e-mail',
    'register.by_phone'          => 'Par téléphone',

    // Inscription
    'auth.register.title'    => 'Créer un compte',
    'auth.register.subtitle' => 'Un seul compte pour acheter et vendre.',
    'auth.register.submit'   => 'Créer mon compte',
    'auth.password_hint'     => 'Au moins :min caractères.',
    'auth.have_account'      => 'Vous avez déjà un compte ?',

    // Connexion
    'auth.login.title'    => 'Connexion',
    'auth.login.submit'   => 'Se connecter',
    'auth.forgot_link'    => 'Mot de passe oublié ?',
    'auth.no_account'     => 'Pas encore de compte ?',
    'auth.login_required' => 'Veuillez vous connecter pour continuer.',

    // Mot de passe oublié
    'auth.forgot.title'    => 'Réinitialiser le mot de passe',
    'auth.forgot.subtitle' => 'Indiquez votre e-mail ; si un compte existe, vous recevrez un lien.',
    'auth.forgot.submit'   => 'Envoyer le lien',

    // Réinitialisation
    'auth.reset.title'    => 'Choisir un nouveau mot de passe',
    'auth.reset.subtitle' => 'Saisissez votre nouveau mot de passe ci-dessous.',
    'auth.reset.submit'   => 'Mettre à jour',

    // Vérification e-mail
    'verify.notice_title' => 'Vérifiez votre e-mail',
    'verify.notice_body'  => 'Nous avons envoyé un lien de vérification à :email. Cliquez dessus pour activer toutes les fonctionnalités.',
    'verify.resend'       => 'Renvoyer l’e-mail',
    'verify.go_dashboard' => 'Aller au tableau de bord',

    // Tableau de bord
    'dashboard.title'           => 'Tableau de bord',
    'dashboard.welcome'         => 'Bienvenue, :email.',
    'dashboard.email_unverified'=> 'Votre adresse e-mail n’est pas encore vérifiée.',
    'dashboard.email_verified'  => 'Votre adresse e-mail est vérifiée.',
    'dashboard.role'            => 'Rôle',
    'dashboard.member_since'    => 'Membre depuis',
    'dashboard.next_steps'      => 'Phase 1 à venir : créez votre profil vendeur (boutique, restaurant, salon ou service).',

    // Validation
    'validation.email_invalid'     => 'Adresse e-mail invalide.',
    'validation.email_taken'       => 'Cette adresse e-mail est déjà utilisée.',
    'validation.password_short'    => 'Le mot de passe doit contenir au moins :min caractères.',
    'validation.password_mismatch' => 'Les mots de passe ne correspondent pas.',
    'validation.required'          => 'Ce champ est obligatoire.',
    'validation.birthdate_invalid' => 'Date de naissance invalide (format jj/mm/aaaa).',
    'validation.phone_invalid'     => 'Numéro de téléphone invalide.',
    'validation.phone_taken'       => 'Ce numéro est déjà utilisé.',

    // Messages flash
    'flash.registered'          => 'Compte créé. Vérifiez votre e-mail pour l’activer.',
    'flash.registered_phone'    => 'Compte créé. Bienvenue sur Afriklink !',
    'flash.logged_in'           => 'Vous êtes connecté.',
    'flash.logged_out'          => 'Vous êtes déconnecté.',
    'flash.invalid_credentials' => 'E-mail ou mot de passe incorrect.',
    'flash.account_suspended'   => 'Ce compte est suspendu. Contactez le support.',
    'flash.reset_sent'          => 'Si un compte existe pour cette adresse, un lien de réinitialisation a été envoyé.',
    'flash.reset_ok'            => 'Mot de passe mis à jour. Vous pouvez vous connecter.',
    'flash.invalid_token'       => 'Lien invalide ou expiré.',
    'flash.verify_ok'           => 'Votre e-mail est vérifié. Merci !',
    'flash.verify_sent'         => 'E-mail de vérification envoyé.',
    'flash.already_verified'    => 'Votre e-mail est déjà vérifié.',

    // E-mails
    'mail.verify.subject' => 'Vérifiez votre e-mail — Afriklink',
    'mail.verify.body'    => 'Bienvenue sur :app. Confirmez votre adresse e-mail en cliquant sur le lien ci-dessous.',
    'mail.verify.cta'     => 'Vérifier mon e-mail',
    'mail.reset.subject'  => 'Réinitialisation de votre mot de passe — Afriklink',
    'mail.reset.body'     => 'Vous avez demandé à réinitialiser votre mot de passe sur :app. Ce lien expire bientôt.',
    'mail.reset.cta'      => 'Réinitialiser mon mot de passe',

    // Erreurs
    'error.404_title'         => 'Page introuvable',
    'error.404_body'          => 'La page que vous cherchez n’existe pas ou a été déplacée.',
    'error.403_title'         => 'Accès refusé',
    'error.403_body'          => 'Vous n’avez pas l’autorisation d’accéder à cette ressource.',
    'error.405_title'         => 'Méthode non autorisée',
    'error.405_body'          => 'Cette action n’est pas permise ici.',
    'error.429_title'         => 'Trop de requêtes',
    'error.429_body'          => 'Vous avez effectué trop de tentatives. Réessayez dans quelques instants.',
    'error.500_title'         => 'Une erreur est survenue',
    'error.500_body'          => 'Un problème est survenu de notre côté. Réessayez plus tard.',
    'error.back_home'         => 'Retour à l’accueil',
    'error.too_many_requests' => 'Trop de requêtes. Réessayez plus tard.',
];
