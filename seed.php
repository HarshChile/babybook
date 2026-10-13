<?php
/**
 * Initial Data Seeder for BabyBook
 */

require_once __DIR__ . '/db.php';

// Seed memories if empty
if (DB::count('memories') === 0) {
    $sampleMemories = [
        [
            'title' => 'First Smile',
            'description' => 'Emma smiled for the first time after morning bath! 💖',
            'date' => '2026-01-12',
            'category' => 'Milestone',
            'photo_path' => 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&auto=format&fit=crop&q=80',
            'is_favorite' => true,
            'created_at' => '2026-01-12 10:30:00'
        ],
        [
            'title' => 'First Roll Over',
            'description' => 'Rolled completely onto her tummy and laughed out loud! ✨',
            'date' => '2026-02-04',
            'category' => 'Milestone',
            'photo_path' => 'https://images.unsplash.com/photo-1544126592-807ade215a0b?w=600&auto=format&fit=crop&q=80',
            'is_favorite' => true,
            'created_at' => '2026-02-04 14:15:00'
        ],
        [
            'title' => 'First Trip to the Park',
            'description' => 'Loved watching the green leaves swaying in the breeze.',
            'date' => '2026-02-20',
            'category' => 'Daily',
            'photo_path' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=600&auto=format&fit=crop&q=80',
            'is_favorite' => false,
            'created_at' => '2026-02-20 16:40:00'
        ],
        [
            'title' => 'Six Months Celebration! 🎂',
            'description' => 'Half a year of boundless love, soft giggles, and sweet hugs.',
            'date' => '2026-03-01',
            'category' => 'Celebration',
            'photo_path' => 'https://images.unsplash.com/photo-1555252333-978fead06c00?w=600&auto=format&fit=crop&q=80',
            'is_favorite' => true,
            'created_at' => '2026-03-01 11:00:00'
        ]
    ];

    foreach ($sampleMemories as $sm) {
        DB::insert('memories', $sm);
    }
}

// Seed milestones if empty
if (DB::count('milestones') === 0) {
    $sampleMilestones = [
        ['title' => 'First Smile', 'category' => 'social', 'date' => '2026-01-12', 'created_at' => '2026-01-12 10:00:00'],
        ['title' => 'Held Head High', 'category' => 'physical', 'date' => '2026-01-25', 'created_at' => '2026-01-25 11:00:00'],
        ['title' => 'Coos and Babbling', 'category' => 'language', 'date' => '2026-02-14', 'created_at' => '2026-02-14 09:30:00'],
        ['title' => 'First Roll Over', 'category' => 'physical', 'date' => '2026-02-20', 'created_at' => '2026-02-20 15:00:00']
    ];
    foreach ($sampleMilestones as $ms) {
        DB::insert('milestones', $ms);
    }
}

// Seed profile if empty
if (DB::count('profile') === 0) {
    DB::insert('profile', [
        'baby_name' => 'Emma',
        'birth_date' => '2025-07-12',
        'parent_names' => 'Sarah & John',
        'bio' => 'Emma is a bright, smiling angel filling our home with pure sunshine and happiness.',
        'avatar_path' => null,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}

// Seed time capsules if empty
if (DB::count('capsules') === 0) {
    DB::insert('capsules', [
        'title' => 'For Emma on Her 18th Birthday',
        'message' => 'Dear Emma, today you smiled for the first time. We love you more than all the stars in the night sky.',
        'unlock_date' => '2043-07-12',
        'media_path' => null,
        'media_type' => null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

echo "Database seeded successfully.\n";
