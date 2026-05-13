<?php

declare(strict_types=1);

/**
 * Deterministic generator for the WP11 acceptance fixture
 * `users-1000.csv`.
 *
 * Run manually whenever the fixture's content rules change:
 *
 *   php packages/migration/tests/Fixtures/data/generate-users-1000.php
 *
 * The committed `users-1000.csv` is the canonical artefact — this script
 * is the auditable recipe. Seed is fixed (`WP11_USERS_FIXTURE_SEED`) so a
 * regeneration round-trips byte-identically.
 *
 * Schema (header row): id,username,full_name,bio_html,signup_year,tags
 *
 * Row content rules (locked for reproducibility):
 *  - id          → 1..1000 integers, monotonically increasing.
 *  - username    → "user-0001".."user-1000" zero-padded.
 *  - full_name   → random pick from a 20-entry first+last dictionary.
 *  - bio_html    → mixed allowed tags (<p>, <a>, <strong>); 20 rows
 *                  (ids 50, 100, 150, ... 1000) embed a <script> tag
 *                  to verify HtmlSanitizeProcessor stripping.
 *  - signup_year → integers 2010..2025.
 *  - tags        → pipe-delimited 1..3 tags from a small vocabulary;
 *                  10% of rows emit an empty string so DefaultValueProcessor
 *                  has something to substitute on.
 *
 * @internal — test fixture generator.
 */

const WP11_USERS_FIXTURE_SEED = 20260513;
const WP11_USERS_FIXTURE_ROWS = 1000;

$firstNames = [
    'Aanji', 'Binesi', 'Chebar', 'Doodan', 'Eshkan', 'Fionn', 'Gemma',
    'Halle', 'Inola', 'Jaalia', 'Kael', 'Lira', 'Mahdi', 'Nia', 'Orin',
    'Petra', 'Quill', 'Rohan', 'Sora', 'Talin',
];
$lastNames = [
    'Aubichon', 'Bigetty', 'Cardinal', 'Desjarlais', 'Eaglefeather',
    'Flett', 'Gabriel', 'Houle', 'Iron', 'Janvier', 'Kistabish', 'Lavalee',
    'Morin', 'Nahanee', 'Okemow', 'Paquette', 'Quewezance', 'Roan',
    'Sutherland', 'Tootoosis',
];
$tagVocab = ['ojibwe', 'cree', 'metis', 'haudenosaunee', 'dakota', 'inuit', 'general'];

mt_srand(WP11_USERS_FIXTURE_SEED);

$outputPath = __DIR__ . '/users-1000.csv';
$handle = fopen($outputPath, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Cannot open {$outputPath} for writing.\n");
    exit(1);
}

// Header.
fputcsv($handle, ['id', 'username', 'full_name', 'bio_html', 'signup_year', 'tags'], ',', '"', '\\');

for ($id = 1; $id <= WP11_USERS_FIXTURE_ROWS; $id++) {
    $username = sprintf('user-%04d', $id);
    $fullName = $firstNames[mt_rand(0, count($firstNames) - 1)]
        . ' ' . $lastNames[mt_rand(0, count($lastNames) - 1)];

    // bio_html — mix allowed tags; every 50th row embeds <script> to
    // verify HtmlSanitizeProcessor strips it.
    $bio = '<p>Author bio for ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '.</p>';
    if (mt_rand(0, 9) === 0) {
        $bio .= ' <p>Visit <a href="https://example.test/' . $username . '">profile</a>.</p>';
    }
    if (mt_rand(0, 4) === 0) {
        $bio .= ' <p><strong>Featured author.</strong></p>';
    }
    if ($id % 50 === 0) {
        $bio .= '<script>alert("xss-' . $id . '")</script>';
    }

    $signupYear = (string) mt_rand(2010, 2025);

    // 10% empty tags so DefaultValueProcessor has something to substitute.
    if (mt_rand(0, 9) === 0) {
        $tags = '';
    } else {
        $tagCount = mt_rand(1, 3);
        $picked = [];
        for ($t = 0; $t < $tagCount; $t++) {
            $picked[] = $tagVocab[mt_rand(0, count($tagVocab) - 1)];
        }
        $tags = implode('|', $picked);
    }

    fputcsv($handle, [(string) $id, $username, $fullName, $bio, $signupYear, $tags], ',', '"', '\\');
}

fclose($handle);

$line_count = (int) shell_exec('wc -l < ' . escapeshellarg($outputPath));
fwrite(STDOUT, "Wrote {$outputPath} with {$line_count} lines (1 header + 1000 data rows expected).\n");
