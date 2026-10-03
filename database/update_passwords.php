<?php
/**
 * Update user passwords to hard/strong passwords
 */
require_once __DIR__ . '/../config/database.php';

$passwords = [
    'superadmin'   => 'Sp!ngf#ld@Adm!n2026#X9',
    'admin'        => 'Adm!n#Secur3$2026*K7',
    'accountant'   => 'Acc0unt$F!nance#2026@M4',
    'teacher'      => 'T3ach#Educ@t!on2026$P8',
    'receptionist' => 'Rec3pt!on#Front$2026*Q2',
    'librarian'    => 'L!brary#B00ks$2026@W5',
    'parent'       => 'Par3nt#P0rtal$2026&R3',
    'student'      => 'Stud3nt#Ac@demy2026!Z6',
];

echo "Updating passwords to hard/strong credentials...\n";
foreach ($passwords as $username => $plainPassword) {
    $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    echo "  [✓] Updated {$username} => {$plainPassword}\n";
}
echo "All passwords updated successfully!\n";
