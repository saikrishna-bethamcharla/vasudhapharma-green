<?php
/**
 * Vasudha Pharma Chem Limited — Real-Time Application Tracking API
 * Securely looks up application reference ID and returns current hiring pipeline step.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$queryId = strtoupper(trim($_GET['id'] ?? ''));
if (!$queryId) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'found' => false,
        'error' => 'Application Reference ID is required.'
    ]);
    exit;
}

// 1. Check live server database first
$dataFile = __DIR__ . '/staff/data/applications.json';
if (file_exists($dataFile)) {
    $apps = json_decode(file_get_contents($dataFile), true);
    if (is_array($apps)) {
        foreach ($apps as $app) {
            if (isset($app['id']) && strtoupper($app['id']) === $queryId) {
                // Public-safe sanitized response
                $step = intval($app['currentStep'] ?? 1);
                $isArchived = !empty($app['archived']) || $step === -1;

                echo json_encode([
                    'ok'          => true,
                    'found'       => true,
                    'source'      => 'live',
                    'id'          => $app['id'],
                    'name'        => $app['name'] ?? 'Candidate',
                    'role'        => $app['job_title'] ?? 'Pharmaceutical Candidate',
                    'location'    => !empty($app['preferred_plant']) ? ('Plant: ' . ucfirst($app['preferred_plant'])) : ($app['location'] ?? 'Vasudha Pharma Plant'),
                    'appliedDate' => $app['date'] ?? date('d M Y'),
                    'currentStep' => $isArchived ? 1 : $step,
                    'statusLabel' => $app['statusLabel'] ?? 'Profile Screening',
                    'statusClass' => $isArchived ? 'rejected' : ($app['statusClass'] ?? 'in-progress'),
                    'reviewerNote'=> $app['reviewerNote'] ?? 'Application received and registered with Vasudha Talent Acquisition.',
                    'archived'    => $isArchived,
                    'updatedAt'   => !empty($app['updated_at']) ? date('d M Y', strtotime($app['updated_at'])) : null
                ]);
                exit;
            }
        }
    }
}

// 2. Demo fallback samples (for prospective visitors & UI preview chips)
$mockSamples = [
    'VP-2026-GET' => [
        'id'          => 'VP-2026-GET',
        'name'        => 'Aarav Sharma',
        'role'        => 'Graduate Executive Trainee (GET) — Synthesis & Scale-Up',
        'location'    => 'Unit-2, Visakhapatnam',
        'appliedDate' => '18 September, 2026',
        'currentStep' => 3,
        'statusLabel' => 'Panel Interview Scheduled',
        'statusClass' => 'in-progress',
        'reviewerNote'=> 'Technical assessment passed with commendation (Score: 92%). Panel interview scheduled with Senior Technical Committee & HR on October 14, 2026.'
    ],
    'VP-2026-QA' => [
        'id'          => 'VP-2026-QA',
        'name'        => 'Priyanka Rao',
        'role'        => 'Assistant Manager — Quality Assurance (Analytical Reviews)',
        'location'    => 'Unit-5, Atchutapuram, Vizag',
        'appliedDate' => '10 September, 2026',
        'currentStep' => 4,
        'statusLabel' => 'Formal Offer Issued',
        'statusClass' => 'passed',
        'reviewerNote'=> 'All technical and executive panel rounds cleared. Formal appointment letter dispatched to registered email. Induction scheduled at Unit-5.'
    ],
    'VP-2026-RND' => [
        'id'          => 'VP-2026-RND',
        'name'        => 'Dr. K. Srinivas',
        'role'        => 'Senior Research Scientist — Process Chemistry & Catalysis',
        'location'    => 'Vikasith R&D Center, Hyderabad',
        'appliedDate' => '21 September, 2026',
        'currentStep' => 2,
        'statusLabel' => 'Technical Evaluation In Progress',
        'statusClass' => 'in-progress',
        'reviewerNote'=> 'Application profile approved by Talent Acquisition. Non-infringing route scouting case study is currently under technical review by Principal Scientist.'
    ]
];

if (isset($mockSamples[$queryId])) {
    $sample = $mockSamples[$queryId];
    $sample['ok'] = true;
    $sample['found'] = true;
    $sample['source'] = 'demo';
    echo json_encode($sample);
    exit;
}

// 3. Not found
echo json_encode([
    'ok'    => false,
    'found' => false,
    'error' => "Application reference \"{$queryId}\" was not found in active records. Please check the reference code or email HR at wisdom@vasudhapharma.com."
]);
