<?php
// api/seed_cms_policies.php
require_once 'db.php';

echo "CMS Policies Seeder started...\n";

$policies = [
    [
        'slug' => 'privacy-policy',
        'title' => 'Privacy Policy',
        'content' => '
            <h2>1. Information We Collect</h2>
            <p>At our store, we collect information that allows us to provide our services. This includes:</p>
            <ul>
                <li><strong>Personal Details:</strong> Name, email address, phone number, and delivery address.</li>
                <li><strong>Transaction Data:</strong> Details about payments and the products you\'ve purchased from us.</li>
                <li><strong>Technical Data:</strong> IP address, login data, browser type, and version, time zone setting, and location.</li>
            </ul>

            <h2>2. How We Use Your Data</h2>
            <p>We use your data to process orders, manage your account, and, if you agree, to email you about other products and services we think may be of interest to you.</p>

            <h2>3. Data Security</h2>
            <p>We have put in place appropriate security measures to prevent your personal data from being accidentally lost, used, or accessed in an unauthorized way. We limit access to your personal data to those employees, agents, and contractors who have a business need to know.</p>

            <h2>4. Your Legal Rights</h2>
            <p>Under certain circumstances, you have rights under data protection laws in relation to your personal data, including the right to request access, correction, erasure, restriction, and transfer.</p>
        '
    ],
    [
        'slug' => 'terms-of-service',
        'title' => 'Terms of Service',
        'content' => '
            <h2>1. Agreement to Terms</h2>
            <p>By accessing or using our store, you agree to be bound by these Terms of Service and all applicable laws and regulations. If you do not agree with any of these terms, you are prohibited from using or accessing this site.</p>

            <h2>2. Use License</h2>
            <p>Permission is granted to temporarily download one copy of the materials (information or software) on our website for personal, non-commercial transitory viewing only.</p>

            <h2>3. Disclaimer</h2>
            <p>The materials on our website are provided on an \'as is\' basis. We make no warranties, expressed or implied, beyond those required by law. Please verify product specifications and suitability before purchase.</p>

            <h2>4. Governing Law</h2>
            <p>These terms and conditions are governed by and construed in accordance with the laws of Ghana and you irrevocably submit to the exclusive jurisdiction of the courts in that State or location.</p>
        '
    ],
    [
        'slug' => 'return-policy',
        'title' => 'Return Policy',
        'content' => '
            <h2>How to Return an Item</h2>
            <ol>
                <li><strong>Initiate Return:</strong> Log into your account, navigate to Orders, and select \'Return Item\' on the eligible product.</li>
                <li><strong>Pack Securely:</strong> Ensure the item is in its original condition with all tags and original packaging included.</li>
                <li><strong>Ship it Back:</strong> Drop the package off at any of our authorized dispatch locations using the provided label.</li>
                <li><strong>Get Reimbursed:</strong> Once inspected, refunds are processed to your original payment method within 3-5 days.</li>
            </ol>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                <div style="background: rgba(34, 197, 94, 0.05); padding: 20px; border-radius: 12px; border: 1px solid rgba(34, 197, 94, 0.1);">
                    <h3 style="color: #22c55e; margin-top: 0;">Eligible for Return</h3>
                    <ul>
                        <li>Dead-on-arrival (DOA) components</li>
                        <li>Items that are clearly defective or damaged upon delivery</li>
                        <li>Wrong item shipped</li>
                        <li>Unopened STEM kits in original sealed packaging</li>
                        <li>Modules and breakout boards that never powered on</li>
                    </ul>
                </div>
                <div style="background: rgba(239, 68, 68, 0.05); padding: 20px; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.1);">
                    <h3 style="color: #ef4444; margin-top: 0;">Non-Returnable Items</h3>
                    <ul>
                        <li>Opened component packs (resistors, capacitors, etc.)</li>
                        <li>Items damaged by incorrect wiring or ESD mishandling</li>
                        <li>STEM kits with assembled or soldered parts</li>
                        <li>Clearance or final sale components</li>
                        <li>Custom-cut wire spools or bulk orders</li>
                    </ul>
                </div>
            </div>
        '
    ],
    [
        'slug' => 'shipping-info',
        'title' => 'Shipping Information',
        'content' => '
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h3>Delivery Options</h3>
                    <p>We offer standard (3-5 business days) and express (1-2 business days) shipping options nationwide.</p>
                </div>
                <div>
                    <h3>Order Tracking</h3>
                    <p>Once your order is processed, a tracking link will be provided via email or SMS depending on your preferences.</p>
                </div>
                <div>
                    <h3>Coverage Areas</h3>
                    <p>We currently deliver to all primary regions within the country. Remote areas may incur additional delivery times.</p>
                </div>
                <div>
                    <h3>Processing Time</h3>
                    <p>Orders placed before 2 PM local time are processed the same business day.</p>
                </div>
            </div>

            <div style="margin-top: 40px; padding: 25px; background: rgba(59, 130, 246, 0.05); border-radius: 16px; border: 1px solid rgba(59, 130, 246, 0.1);">
                <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    Shipping Protection Guarantee
                </h3>
                <p>All orders are packed with care. Fragile items are protected where appropriate. If your package arrives damaged or items are missing, contact us within 48 hours of delivery with photos — we will work with you on a fair resolution.</p>
            </div>
        '
    ]
];

foreach ($policies as $policy) {
    try {
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ?");
        $stmt->execute([$policy['slug']]);
        $exists = $stmt->fetch();

        if ($exists) {
            echo "Policy '{$policy['slug']}' already exists, skipping.\n";
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO cms_pages (slug, title, content, is_published) VALUES (?, ?, ?, 1)");
        $stmt->execute([
            $policy['slug'],
            $policy['title'],
            trim($policy['content'])
        ]);
        echo "Successfully seeded '{$policy['title']}'\n";
    } catch (Exception $e) {
        echo "Error seeding '{$policy['slug']}': " . $e->getMessage() . "\n";
    }
}

echo "Seeding complete.\n";
