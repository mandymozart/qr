<?php
// German: sign-in, sign-up, account, impersonation, login mails. English → German.
return [
    // login.php
    'Please enter your email.' => 'Bitte gib deine E-Mail-Adresse ein.',
    'Wrong email or password. No password yet? Use the sign-in link.' => 'E-Mail oder Passwort falsch. Noch kein Passwort? Nutze den Anmelde-Link.',
    'If that address has an account, a link to set a new password is on its way (valid 30 minutes).' => 'Falls es zu dieser Adresse ein Konto gibt, ist ein Link zum Setzen eines neuen Passworts unterwegs (30 Minuten gültig).',
    'If that address has an account, a sign-in link is on its way (valid 30 minutes).' => 'Falls es zu dieser Adresse ein Konto gibt, ist ein Anmelde-Link unterwegs (30 Minuten gültig).',
    'Sign in' => 'Anmelden',
    'Sign in with your password, or get a secret link by mail.' => 'Melde dich mit deinem Passwort an oder hol dir einen geheimen Link per Mail.',
    'Email' => 'E-Mail',
    'Password' => 'Passwort',
    '(if you set one)' => '(falls du eines gesetzt hast)',
    'Email me a sign-in link' => 'Anmelde-Link per Mail schicken',
    'Forgot password?' => 'Passwort vergessen?',
    'No account? {link}.' => 'Noch kein Konto? {link}.',
    'Create one for free' => 'Leg dir gratis eines an',

    // auth.php
    'Welcome, {name}. You\'re signing in as {email}.' => 'Willkommen, {name}. Du meldest dich als {email} an.',
    'Welcome. You\'re signing in as {email}.' => 'Willkommen. Du meldest dich als {email} an.',
    'Continue to qr →' => 'Weiter zu qr →',
    'This link is invalid, already used or expired.' => 'Dieser Link ist ungültig, schon verwendet oder abgelaufen.',
    'Request a new link' => 'Neuen Link anfordern',

    // register.php
    'Please enter a valid email address.' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
    'Create account' => 'Konto erstellen',
    'Check your inbox: we sent a sign-in link to {email}. It\'s valid for 24 hours.' => 'Schau in dein Postfach: Wir haben einen Anmelde-Link an {email} geschickt. Er ist 24 Stunden gültig.',
    'No mail? Look in your spam folder, or {link} in a minute.' => 'Keine Mail bekommen? Schau im Spam-Ordner nach oder {link} in einer Minute.',
    'try again' => 'versuch es nochmal',
    'Free. We\'ll mail you a secret link to sign in, no password needed (you can set one later).' => 'Gratis. Wir schicken dir einen geheimen Anmelde-Link per Mail, kein Passwort nötig (du kannst später eines setzen).',
    'The code you made in this browser will move into your account.' => 'Der QR-Code, den du in diesem Browser erstellt hast, wandert in dein Konto.',
    'The {n} codes you made in this browser will move into your account.' => 'Die {n} QR-Codes, die du in diesem Browser erstellt hast, wandern in dein Konto.',
    'Name' => 'Name',
    '(optional)' => '(optional)',
    'Already have an account? {link}' => 'Schon ein Konto? {link}',

    // account.php
    'Saved.' => 'Gespeichert.',
    'You can\'t change someone else\'s password. Send them a sign-in link instead.' => 'Du kannst nicht das Passwort von jemand anderem ändern. Schick stattdessen einen Anmelde-Link.',
    'Password removed. Sign in with a link from now on.' => 'Passwort entfernt. Ab jetzt meldest du dich mit einem Link an.',
    'Use at least 10 characters.' => 'Verwende mindestens 10 Zeichen.',
    'The passwords don\'t match.' => 'Die Passwörter stimmen nicht überein.',
    'Password set. You can now sign in with it (other devices were signed out).' => 'Passwort gesetzt. Du kannst dich jetzt damit anmelden (andere Geräte wurden abgemeldet).',
    'Account' => 'Konto',
    'master admin' => 'Haupt-Admin',
    'admin' => 'Admin',
    'user' => 'Benutzer',
    'You\'re signed in. Set a new password below.' => 'Du bist angemeldet. Setz unten ein neues Passwort.',
    'Profile' => 'Profil',
    'Save' => 'Speichern',
    'To change your email, ask an admin.' => 'Um deine E-Mail-Adresse zu ändern, wende dich an einen Admin.',
    'You have a password set.' => 'Du hast ein Passwort gesetzt.',
    'Optional. Without one, you sign in with a link by mail.' => 'Optional. Ohne Passwort meldest du dich mit einem Link per Mail an.',
    'The ADMIN_PASSWORD from the deploy secrets also always works for you.' => 'Das ADMIN_PASSWORD aus den Deploy-Secrets funktioniert für dich außerdem immer.',
    'New password' => 'Neues Passwort',
    'Repeat' => 'Wiederholen',
    'Change password' => 'Passwort ändern',
    'Set password' => 'Passwort setzen',
    'Remove your password? You\'ll sign in with mail links only.' => 'Passwort entfernen? Du meldest dich dann nur noch mit Links per Mail an.',
    'Remove password' => 'Passwort entfernen',

    // impersonate.php
    'Back in your own account.' => 'Wieder in deinem eigenen Konto.',
    'Admins only.' => 'Nur für Admins.',
    'You can\'t log in as this user.' => 'Du kannst dich nicht als dieser Benutzer anmelden.',
    'You are now logged in as {email}.' => 'Du bist jetzt als {email} angemeldet.',

    // lib/mail.php
    '7 days' => '7 Tage',
    '30 minutes' => '30 Minuten',
    '24 hours' => '24 Stunden',
    'Hi {name},' => 'Hallo {name},',
    'Hi,' => 'Hallo,',
    "welcome to {host}, the QR code and link tracker.\n\nSign in with this secret link (valid {valid}, works once):\n{link}\n\nLater on, request a fresh link any time at {url}\n"
        => "willkommen bei {host}, dem QR-Code- und Link-Tracker.\n\nMelde dich mit diesem geheimen Link an ({valid} gültig, funktioniert einmal):\n{link}\n\nSpäter kannst du jederzeit einen neuen Link anfordern unter {url}\n",
    "here is your secret sign-in link for {host} (valid {valid}, works once):\n{link}\n\nIf you didn't ask for this, ignore this mail.\n"
        => "hier ist dein geheimer Anmelde-Link für {host} ({valid} gültig, funktioniert einmal):\n{link}\n\nFalls du ihn nicht angefordert hast, ignorier diese Mail einfach.\n",
    'Your access to {host}' => 'Dein Zugang zu {host}',
    'Sign in to {host}' => 'Bei {host} anmelden',
];
