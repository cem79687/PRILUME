<?php
/*=========================================================
    PRILUME — Traitement du formulaire de contact
    A placer a la racine du site, a cote de contact.html
    Necessite un hebergement avec PHP (O2switch, Hostinger, etc.)
=========================================================*/

header('Content-Type: application/json; charset=utf-8');

// L'adresse qui recoit les messages (doit etre une vraie boite mail existante
// sur ton hebergement/nom de domaine)
$destinataire = 'cem79687@gmail.com';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode non autorisee.']);
    exit;
}

// --- Piege a robots : le champ cache "website" doit rester vide ---
// Un vrai visiteur ne le voit ni ne le remplit jamais (display:none en CSS).
if (!empty($_POST['website'])) {
    // On repond "succes" pour ne pas alerter le bot, mais on n'envoie rien.
    echo json_encode(['success' => true]);
    exit;
}

function clean_field($value) {
    $value = trim($value ?? '');
    // anti header-injection : on retire tout retour a la ligne
    $value = str_replace(["\r", "\n", "%0a", "%0d"], '', $value);
    return $value;
}

$name    = clean_field($_POST['name'] ?? '');
$email   = clean_field($_POST['email'] ?? '');
$project = clean_field($_POST['project'] ?? '');
$budget  = clean_field($_POST['budget'] ?? '');
$message = trim($_POST['message'] ?? '');

// --- Validation serveur (ne jamais faire confiance a la validation cote client) ---
$errors = [];
if ($name === '' || strlen($name) > 100) $errors[] = 'name';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'email';
if ($project === '') $errors[] = 'project';
if ($message === '' || strlen($message) > 5000) $errors[] = 'message';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Merci de remplir correctement tous les champs obligatoires.',
        'fields'  => $errors,
    ]);
    exit;
}

$projets_labels = [
    'vitrine' => 'Creation de site vitrine',
    'refonte' => 'Refonte de site existant',
    'uxui'    => 'UX/UI Design',
    'seo'     => 'Performance & SEO',
    'autre'   => 'Autre projet',
];
$projet_libelle = $projets_labels[$project] ?? htmlspecialchars($project, ENT_QUOTES, 'UTF-8');

$name_safe    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$budget_safe  = $budget !== '' ? htmlspecialchars($budget, ENT_QUOTES, 'UTF-8') : 'Non precise';
$message_safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

$subject = "[Site Prilume] Nouvelle demande de " . $name_safe;

$body  = "Nouveau message recu depuis le formulaire de contact du site :\n\n";
$body .= "Nom          : $name_safe\n";
$body .= "Email        : $email\n";
$body .= "Projet       : $projet_libelle\n";
$body .= "Budget       : $budget_safe\n\n";
$body .= "Message :\n$message_safe\n";
$body .= "\n---\nEnvoye depuis le formulaire de contact — " . ($_SERVER['HTTP_HOST'] ?? 'prilume.fr') . "\n";

// From = ton propre domaine (obligatoire sur la plupart des hebergeurs pour
// eviter d'etre bloque comme spam). Reply-To = l'email du visiteur, pour
// pouvoir lui repondre directement depuis ta messagerie.
$headers   = [];
$headers[] = "From: Site Prilume <$destinataire>";
$headers[] = "Reply-To: $email";
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "X-Mailer: PHP/" . phpversion();

$sent = @mail($destinataire, $subject, $body, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => "L'envoi a echoue. Merci de reessayer ou d'ecrire directement a $destinataire.",
    ]);
}
